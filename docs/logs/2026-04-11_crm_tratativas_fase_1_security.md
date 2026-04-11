# CRM Tratativas — Fase 1 (Security & Change Control)

**Fecha:** 2026-04-11
**Módulo nuevo:** `CrmTratativas`
**Módulos tocados quirúrgicamente:** `CrmPedidosServicio`, `CrmPresupuestos`, `Dashboard`, `routes.php`

---

## Resumen ejecutivo

Se implementa el módulo **CrmTratativas**, un agregador de oportunidades comerciales que agrupa PDS y Presupuestos bajo un mismo caso de negociación con un cliente. La tratativa representa un "deal" o "oportunidad" en lenguaje clásico de CRM, adaptado al lenguaje del proyecto.

Como parte de esta fase, se modificaron quirúrgicamente `CrmPedidosServicio` y `CrmPresupuestos` para aceptar un vínculo opcional con una tratativa mediante query param `?tratativa_id=X`, siguiendo el patrón ya existente "Generar PDS desde Llamadas" (`CrmLlamadas → CrmPedidosServicio`).

Esta fase NO toca Agenda ni Google OAuth (fase 2 del plan aprobado por el rey).

---

## Archivos creados

### Módulo nuevo `app/modules/CrmTratativas/`
- `TratativaController.php` — ABM + sub-grillas + endpoints de sugerencias
- `TratativaRepository.php` — Acceso a datos con aislamiento multiempresa estricto
- `MODULE_CONTEXT.md` — Documentación del módulo siguiendo la plantilla del proyecto
- `views/index.php` — Listado con filtros F3, tabs de estado y papelera
- `views/form.php` — Alta/edición con buscador de cliente por AJAX
- `views/detalle.php` — Vista maestra con sub-grillas de PDS y Presupuestos vinculados

### Migraciones `database/migrations/`
- `2026_04_11_create_crm_tratativas.php` — Creación de la tabla principal
- `2026_04_11_add_tratativa_id_to_pds_presupuestos.php` — Agrega columna `tratativa_id INT NULL` con índice a `crm_pedidos_servicio` y `crm_presupuestos` (idempotente)

### Documentación
- `docs/logs/2026-04-11_crm_tratativas_fase_1_security.md` (este archivo)

---

## Archivos modificados quirúrgicamente

- `app/modules/CrmPedidosServicio/PedidoServicioController.php`
    - `validateRequest()`: lee `$_POST['tratativa_id']` (o fallback a `$pedidoActual`), lo valida contra `TratativaRepository::existsActiveForEmpresa()` y lo incluye en el payload. Si no existe o no pertenece a la empresa, se ignora silenciosamente (no bloquea el guardado del PDS por un vínculo opcional).
    - `defaultFormState()`: lee `$_GET['tratativa_id']` al crear desde el detalle de la tratativa.
    - `hydrateFormState()`: incluye `tratativa_id` del registro.
    - `buildFormStateFromPost()`: preserva `tratativa_id` en reintentos por error de validación.
    - `store()` y `update()`: redirect condicional al detalle de la tratativa si hay `tratativa_id`, sino al editar del PDS como siempre. Nuevo helper privado `resolveReturnPath()`.

- `app/modules/CrmPedidosServicio/PedidoServicioRepository.php`
    - `INSERT` incluye la columna `tratativa_id`.
    - `UPDATE` incluye la columna `tratativa_id`.
    - `buildPayload()` agrega el placeholder `:tratativa_id` con casteo a int o null.

- `app/modules/CrmPedidosServicio/views/form.php`
    - Hidden `<input name="tratativa_id">` dentro del form para transportar el vínculo en el POST.
    - Alert informativo en el tope del form indicando que el PDS pertenece a una tratativa, con link al detalle.

- `app/modules/CrmPresupuestos/PresupuestoController.php`
    - Mismos cambios que en PedidoServicioController (`validateRequest`, `defaultFormState`, `hydrateFormState`, `buildFormStateFromPost`, `store`, `update`, `resolveReturnPath`).
    - `defaultFormState` también precarga `cliente_id` desde query param (feature nueva aprovechando el mismo hook).

- `app/modules/CrmPresupuestos/PresupuestoRepository.php`
    - `INSERT` incluye `tratativa_id`.
    - `UPDATE` incluye `tratativa_id`.
    - `buildHeaderPayload()` agrega el placeholder `:tratativa_id`.

- `app/modules/CrmPresupuestos/views/form.php`
    - Hidden `<input name="tratativa_id">` + alert informativo.

- `app/modules/Dashboard/views/crm_dashboard.php`
    - Nueva tarjeta `'tratativas'` en `$defaultCards` apuntando a `/mi-empresa/crm/tratativas`.

- `app/config/routes.php`
    - Bloque nuevo de rutas `--- MODULO CRM TRATATIVAS ---` con 14 rutas, todas protegidas con `$requireCrm`.

---

## Checklist de Política de Seguridad Base

> Validación obligatoria según `AGENTS.md` del proyecto antes de cualquier implementación de módulo nuevo.

### ✅ Aislamiento multiempresa (`Context::getEmpresaId()`)
- **Controller**: todos los métodos del `TratativaController` obtienen `$empresaId = (int) Context::getEmpresaId()` al inicio.
- **Repository**: todos los métodos reciben `$empresaId` como parámetro explícito y lo usan en `WHERE empresa_id = :empresa_id` en cada query (count, find, create, update, delete, restore, forceDelete, findPdsByTratativaId, findPresupuestosByTratativaId, existsActiveForEmpresa).
- **Sub-grillas del detalle**: `findPdsByTratativaId()` y `findPresupuestosByTratativaId()` filtran DOBLE: por `tratativa_id` Y por `empresa_id`. Imposible leer PDS/Presupuestos de otra empresa aunque se manipule el ID.
- **Cross-module hook**: cuando `CrmPedidosServicio`/`CrmPresupuestos` validan el `tratativa_id` recibido por query/POST, llaman a `TratativaRepository::existsActiveForEmpresa($id, $empresaId)`, que verifica pertenencia a la empresa antes de persistir el vínculo.

### ✅ Permisos / Guards estrictos en backend
- Todas las rutas del módulo registradas en `routes.php` usan el wrapper `$action(..., $requireCrm)`, que invoca `EmpresaAccessService::requireCrmAccess()` antes de ejecutar cualquier acción.
- Cada método del controller inicia con `AuthService::requireLogin()` como defensa en profundidad.
- El módulo es exclusivo CRM: los usuarios del entorno Tiendas no tienen acceso (validado por el guard a nivel router).

### ✅ Separación RXN admin (sistema) vs admin tenant
- `CrmTratativas` es un módulo **tenant**. No expone acciones de administración de sistema (nada bajo `/admin/`).
- No modifica configuración global ni tablas del core (`empresas`, `usuarios`, `empresa_config_crm`).

### ✅ No mutación de estado por peticiones GET
- GET únicamente para lectura: `index`, `show`, `edit`, `create`, `suggestions`, `clientSuggestions`.
- Todas las mutaciones (store, update, eliminar, restore, forceDelete, masivos) son POST.
- Enforced a nivel router: las rutas destructivas solo están declaradas como `$router->post(...)`.

### ✅ Validación fuerte server-side
- `TratativaController::validateRequest()` levanta `ValidationException` con array de errores y re-renderiza el form preservando los datos ingresados vía `buildFormStateFromPost()`.
- Validaciones explícitas:
    - `titulo`: obligatorio, máximo 200 chars.
    - `estado`: debe estar en la lista ENUM (`nueva`, `en_curso`, `ganada`, `perdida`, `pausada`). Fallback a `'nueva'` en el buildPayload del repo.
    - `probabilidad`: entero 0-100, capado en el buildPayload del repo.
    - `valor_estimado`: no negativo, float.
    - `cliente_id`: si se envía, debe existir en `crm_clientes` y pertenecer a la misma empresa.
    - `motivo_cierre`: obligatorio si `estado` ∈ {ganada, perdida}.
    - `fecha_apertura`, `fecha_cierre_estimado`, `fecha_cierre_real`: validación de formato con `DateTimeImmutable::createFromFormat`.
- Los cambios quirúrgicos en PDS y Presupuestos: el `tratativa_id` ingresado es validado contra la base ANTES de persistirlo (`existsActiveForEmpresa`). Si no existe o no pertenece a la empresa, se ignora (no se guarda basura).

### ✅ Escape seguro en salida (preventivo ante XSS)
- Todas las salidas dinámicas en las views usan `htmlspecialchars()` de forma sistemática:
    - Títulos, descripciones, nombres de cliente, usuario, motivos de cierre: `htmlspecialchars((string) $item['campo'])`.
    - `descripcion` con saltos de línea: `nl2br(htmlspecialchars(...))` para preservar formato sin abrir vector XSS.
    - Valores de URL (query params) escapados con `htmlspecialchars($buildQuery(...))`.
    - Atributos `value` de inputs escapados.
- Los mensajes de Flash se renderizan con `htmlspecialchars()`.

### ✅ Impacto sobre acceso local del sistema
- Ninguno. El módulo no modifica permisos de filesystem, no escribe a `/uploads/`, no toca la sesión ni el sistema de autenticación.
- No agrega dependencias nuevas de composer.
- No requiere variables de entorno nuevas.

### ✅ Necesidad o no de token CSRF
- El proyecto base no usa CSRF tokens en el resto de los módulos CRM (PDS, Presupuestos, Llamadas, Notas, Clientes). Se mantiene la consistencia: **no se agrega CSRF a Tratativas** para no introducir una asimetría en el proyecto.
- Mitigación actual: todas las mutaciones requieren `AuthService::requireLogin()` + `requireCrmAccess()` + SameSite cookies del framework base.
- **Recomendación futura para TODO el CRM**: introducir un middleware CSRF global. No es responsabilidad de esta PR resolverlo a nivel de Tratativas aisladamente.

### ✅ Prepared statements (defensa anti-SQL-Injection)
- 100% de las queries usan PDO con placeholders (`:name`) o posicionales (`?`).
- Los placeholders son bindeados con `bindValue()` tipado explícitamente donde corresponde (PDO::PARAM_INT para `$limit` en `findSuggestions`).
- Los nombres de columnas en ORDER BY son validados contra una lista blanca (`$allowedColumns`) antes de concatenarse al SQL, para evitar inyección vía parámetro `sort`.

### ✅ Integridad al borrado permanente (forceDelete)
- `TratativaRepository::forceDeleteByIds()` ejecuta PRIMERO un `UPDATE crm_pedidos_servicio SET tratativa_id = NULL WHERE ...` y `UPDATE crm_presupuestos SET tratativa_id = NULL WHERE ...` antes del `DELETE crm_tratativas`, evitando dejar FKs soft colgantes.
- Confirmación JS requerida en la UI con mensaje explícito: *"Se desvincularán PDS y Presupuestos asociados"*.

---

## Flujo del usuario (aprobado por el rey)

1. Click en **"Nueva Tratativa"** desde el dashboard CRM → form de alta.
2. Se carga cliente (opcional), título, estado inicial, valor estimado, probabilidad y fechas.
3. Al guardar, redirige al **detalle** de la tratativa.
4. Desde el detalle, el usuario puede:
    - **"Nuevo PDS"** → `/mi-empresa/crm/pedidos-servicio/crear?tratativa_id=X&cliente_id=Y`
    - **"Nuevo Presupuesto"** → `/mi-empresa/crm/presupuestos/crear?tratativa_id=X&cliente_id=Y`
5. Al guardar el PDS o Presupuesto, redirect automático de vuelta al detalle de la tratativa (no al editar del propio PDS/Presupuesto).
6. Las sub-grillas del detalle muestran todos los PDS y Presupuestos vinculados, con link directo al editar de cada uno.
7. Cambios de estado (`ganada`, `perdida`) requieren completar `motivo_cierre`.

---

## Pendiente para Fase 2 (Agenda)

- Proyección de eventos desde Tratativas hacia `CrmAgenda` cuando se implemente (hook explícito en `TratativaRepository::save()` hacia `AgendaProyectorService::onTratativaSaved()`).
- Campo `proxima_accion` + `proxima_accion_fecha` en `crm_tratativas` (pendiente de decisión del rey sobre si va en esta tabla o en `crm_agenda_eventos` con `origen_tipo='tratativa_accion'`).
- Integración con Google Calendar OAuth (módulo separado `CrmAgenda`).

---

## Testing manual sugerido post-deploy

- [ ] Correr las dos migraciones `2026_04_11_*.php` en desarrollo y verificar que las tablas y columnas quedan creadas.
- [ ] Acceder a `/mi-empresa/crm/dashboard` → ver tarjeta "Tratativas".
- [ ] Acceder a `/mi-empresa/crm/tratativas` → listado vacío con tabs.
- [ ] Crear una tratativa con cliente asignado.
- [ ] Desde el detalle, click "Nuevo PDS" → verificar query param en URL, crear PDS, confirmar que vuelve al detalle de la tratativa.
- [ ] Idem con "Nuevo Presupuesto".
- [ ] Editar un PDS/Presupuesto ya vinculado: el alert informativo debe aparecer y al guardar debe volver al detalle de la tratativa.
- [ ] Enviar una tratativa a papelera, restaurarla, borrarla definitivamente y verificar que los PDS/Presupuestos asociados quedan con `tratativa_id = NULL` (no se borran).
- [ ] Probar F3 / `/` para abrir el search y tipear dos caracteres del título → debe aparecer en el spotlight.
- [ ] Cambiar estado a "Ganada" sin motivo → debe fallar la validación.
- [ ] Cargar probabilidad 150 → debe ser capada a 100 (o rechazada según validador).
- [ ] Desde otra empresa (multi-tenant): verificar que NO se ven las tratativas de la primera empresa.
