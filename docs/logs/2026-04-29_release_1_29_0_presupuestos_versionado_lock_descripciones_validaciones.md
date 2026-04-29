# Release 1.29.0 — Presupuestos maduros + bugfix crítico de contraseñas

**Fecha**: 2026-04-29
**Build**: 20260429.2
**Tema**: Iteración grande sobre Presupuestos CRM (versionado, lock post-Tango, descripciones largas con DTO, validaciones P0/P1/P2, Agenda extendida) + bugfix crítico transversal de cambio de contraseñas + persistencia de sort/dir en listados.

---

## Qué se hizo

### 1) Cabecera comercial extendida (8 columnas nuevas)

Sumamos a la cabecera del presupuesto:

- **`cotizacion`** (DECIMAL(15,4) DEFAULT 1) — Cotización del dólar al momento del presupuesto. Viaja a Tango como `COTIZACION` (nombre exacto confirmado por GET de pedido real).
- **`proximo_contacto`** (DATETIME NULL) — Recordatorio comercial. Se proyecta a Agenda CRM como evento independiente.
- **`vigencia`** (DATETIME NULL) — Deadline de validez del presupuesto. También se proyecta a Agenda.
- **`leyenda_1`..`leyenda_5`** (VARCHAR(60) NULL) — Leyendas comerciales. Viajan a Tango como `LEYENDA_1`..`LEYENDA_5`.

UI: cotización en col-1 entre Estado y Depósito (Cliente bajó a col-3). Próximo contacto + Vigencia + 5 Leyendas en fila nueva debajo de Transporte. Las fechas usan el wrapper `rxn-datetime.js` (Flatpickr es-AR con segundos).

Migración: `2026_04_29_02_alter_crm_presupuestos_add_cotizacion_vigencia_leyendas.php`.

### 2) Descripción de renglón con soporte multilínea + DESCRIPCION_ADICIONAL_DTO

**Decisión clave**: descubrimos que en Tango Connect el campo `DESCRIPCION_ADICIONAL_ARTICULO` (DESC_ADIC) tiene un límite de **20 caracteres** — confirmado con error real de Tango: `"El campo 'DESC_ADIC' debe ser menor o igual a 20 caracteres"`. Inútil para descripciones reales.

Pero existe un campo array `DESCRIPCION_ADICIONAL_DTO[]` con `{DESCRIPCION (50 chars), DESCRIPCION_ADICIONAL (20 chars)}` por cada item. Eso sí sirve para texto largo multilínea.

**Implementación**:

- Schema: nueva columna `articulo_descripcion_original` en `crm_presupuesto_items` que conserva el nombre del catálogo al elegir el artículo, NO se pisa al editar. Permite detectar modificación (snapshot ≠ original).
- Helper `TangoOrderMapper::chunkDescripcion()` parte el texto en chunks de 50 chars respetando saltos de línea manuales del textarea + `wordwrap(cut=true)` para palabras solitarias.
- Primer chunk → `DESCRIPCION_ARTICULO`.
- Resto de chunks → `DESCRIPCION_ADICIONAL_DTO[]` como array.
- DESC_ADIC del renglón se descarta totalmente del payload.

UI: textarea de 3 filas (antes 2). Borde naranja + badge "Editada" cuando snapshot ≠ original. Label en vivo "· N líneas a Tango (1 principal + N-1 adicionales)" mientras el operador tipea (mini-replicador del algoritmo PHP en JS).

Migración: `2026_04_29_03_alter_crm_presupuesto_items_add_descripcion_original.php` con backfill (items históricos: original = snapshot, marcados como "no modificados").

### 3) Lock post-Tango (patrón replicado de PDS)

Cuando `nro_comprobante_tango` está poblado o estado='emitido':
- `<fieldset disabled>` blindado.
- Botón Guardar oculto.
- Banner verde: "Enviado a Tango (#XXX) — está en Solo Lectura. Para hacer cambios, usá Nueva versión."
- Botones Copiar / Eliminar / Enviar mail / Imprimir siguen disponibles (fuera del fieldset).

### 4) Versionado de presupuestos

Schema: `version_padre_id INT NULL` (apunta a la **raíz** del grupo, NO al padre directo — árbol "plano") + `version_numero INT NOT NULL DEFAULT 1`.

`PresupuestoRepository::createNewVersion()`:
- Resuelve la raíz (si origen ya es versión derivada, hereda raíz).
- Calcula `version_numero = max(grupo) + 1`.
- Clona TODA la cabecera + items con `articulo_descripcion_original` preservado.
- Fuerza `estado='borrador'`, `fecha=ahora`. NO hereda `nro_comprobante_tango`, sync, correos.
- Numero secuencial NUEVO.

UI:
- Botón "Nueva versión" entre Copiar y Eliminar (variantes según estado del original).
- Header del form muestra badge clickeable `v2 · ver origen #X` que lleva al original.
- Listado: badge cyan `vN` al lado del numero cuando es versión derivada.

Migración: `2026_04_29_05_alter_crm_presupuestos_add_versionado.php`.

### 5) Validaciones P0 + P1 + P2 (con feedback claro)

Antes el operador tipeaba en el form, le daba Guardar y "no pasaba nada" (validación server-side rechazaba pero el feedback era pobre). Ahora:

**P0 — Obligatorios server + client + UI**:
- Clasificación obligatoria (sumada al `validateRequest`).
- Banner sticky arriba con lista de errores + ícono.
- Mensajes inline `<div class="invalid-feedback">` debajo de cada campo via helper `$errorMsg($errors, key)`.
- Pre-validación client-side ANTES del submit (capture phase) — bloquea el envío y enfoca el primer campo en falta con scroll smooth.
- `is-invalid` se quita automáticamente cuando el operador empieza a corregir.

**P1 — Dirty check al salir**:
- Snapshot del form al cargar (delay 600ms para que Flatpickr inicialice).
- `beforeunload` nativo del browser.
- Click en `a[data-rxn-back]` y links del menú lateral interceptados con `rxnConfirm` "Salir y perder" / "Seguir editando".
- Submit exitoso invalida el snapshot → no molesta el redirect.

**P2 — Warnings inline en vivo**:
- Cotización en 0 → "puede romper importes en USD".
- Próximo contacto en el pasado → "¿quisiste agendarlo a futuro?".
- Vigencia anterior a la fecha del presupuesto.
- Clasificación sin `id_gva81_tango` → polling 1.5s para detectar cuando el picker resuelve el ID.

### 6) Agenda CRM proyecta 3 eventos por presupuesto

`AgendaProyectorService::onPresupuestoSaved` ahora proyecta hasta 3 eventos:
1. El presupuesto en sí (verde, color existente `#198754`).
2. Próximo contacto (cyan `#0dcaf0`) si está poblado.
3. Vigencia (rojo `#dc3545`) si está poblada.

`onPresupuestoDeleted` borra los 3. Si el operador limpia un campo, el evento previo se borra (delete-or-keep).

UI Agenda: 2 checkboxes nuevos en filtros — "Próx. contacto" y "Vigencia" con sus badges color. Click en cualquiera de los 3 tipos lleva al editar del presupuesto.

Migración: `2026_04_29_04_alter_agenda_eventos_origen_tipo_presupuesto_extra.php` (ALTER MODIFY del ENUM idempotente).

### 7) Bugfix crítico — Cambio de contraseñas por superadmin

**Síntoma**: Charly como superadmin cambiaba la contraseña de un usuario, el form decía "Datos actualizados", pero al loguear con la nueva contraseña fallaba con "Credenciales inválidas". También fallaba con la vieja → el usuario quedaba afuera del sistema.

**Root cause**: El UPDATE de `usuarios` tenía:

```sql
UPDATE usuarios SET ... WHERE id = :id AND empresa_id = :empresa_id
```

El form de superadmin tiene un dropdown "Transferir de Empresa" que se renderiza con `selected` calculado, pero podía mandar un `empresa_id` distinto al real del usuario (race con el `selected`, o simplemente porque el dropdown traía la primera opción cuando la empresa actual no estaba en la lista). Service pisaba `$usuario->empresa_id` con ese valor, UPDATE no matcheaba ninguna fila → retornaba sin error pero `affectedRows=0`.

**Evidencia**: el `updated_at` del usuario `cyaciofani@e-reaxion.com.ar` seguía en marzo aunque Charly lo había editado hoy.

**Fix**:
1. WHERE del UPDATE pasa a ser solo `WHERE id = :id`. El aislamiento de tenant ya está en `getByIdForContext()` que es el portero de carga.
2. `empresa_id` se mueve al SET (así un superadmin puede transferir empresa legítimamente cuando lo intenta).
3. Guard `if ($stmt->rowCount() === 0) throw RuntimeException` — nunca más un UPDATE silencioso.

**Mejoras adicionales**:
- Si rxn_admin cambia password, auto-marca `email_verificado=1` + limpia tokens (decisión "sin nada trambóliko" — el rey está validando manualmente al cambiar la pass).
- Guard contra autofill: si el value recibido parece un hash bcrypt (`$2y$...`), no rehashear (probable autofill del browser).
- `autocomplete="new-password"` + `data-rxn-no-autofill` en los inputs de contraseña de crear y editar.
- Redirect post-update al `/editar` (no al listado), coherente con PDS / Presupuestos.

### 8) Persistencia transversal de sort/dir

Antes el `rxn-filter-persistence.js` excluía explícitamente `sort` y `dir` "por diseño" (decisión vieja: ordenamiento es estado de vista efímero). Pero el operador esperaba que se persista — al ordenar Numero DESC e ir a otra parte y volver, esperaba que el orden se mantenga.

Sumamos `sort` y `dir` al array `FILTER_KEYS`. Aplica a TODOS los listados de la suite.

---

## Por qué

1. Presupuestos era el módulo CRM más usado pero le faltaban features de cotización maduras: versionado para iterar precios, lock para no romper presupuestos enviados a Tango, descripciones libres del operador. Esta iteración los cierra.
2. Las descripciones largas requirieron descubrir el shape exacto que espera Tango Connect (DESCRIPCION_ADICIONAL_DTO[] vs DESC_ADIC), lo cual implicó dos vueltas de payload y confirmación contra GET de pedido real.
3. El bug de contraseñas era silencioso y peligroso — un superadmin no podía cambiar passwords pero el sistema fingía éxito. Fix de raíz movió empresa_id al SET y agregó guard de rowCount.
4. Validaciones eran flacas — el operador podía guardar un presupuesto sin cliente / sin lista / sin clasificación y el form rebotaba sin feedback claro. Las tres capas (server + client pre-submit + UI inline) cubren todos los casos.
5. La persistencia de sort/dir era contraintuitiva — Charly lo notó y nos dimos cuenta que la decisión vieja era equivocada para el flujo real.

---

## Impacto

- **Base de datos**: 5 migraciones nuevas, todas idempotentes:
  - `2026_04_29_02_alter_crm_presupuestos_add_cotizacion_vigencia_leyendas.php` (8 columnas).
  - `2026_04_29_03_alter_crm_presupuesto_items_add_descripcion_original.php` (1 columna + backfill).
  - `2026_04_29_04_alter_agenda_eventos_origen_tipo_presupuesto_extra.php` (ALTER MODIFY ENUM).
  - `2026_04_29_05_alter_crm_presupuestos_add_versionado.php` (2 columnas + índice).
- **Tabla `usuarios`**: WHERE del UPDATE cambió. Sin migración pero requiere atención al hacer code review futuro (no volver al WHERE viejo).
- **Tabla `crm_presupuestos`**: 10 columnas nuevas en total entre cabecera + versionado.
- **Tabla `crm_presupuesto_items`**: 1 columna nueva (`articulo_descripcion_original`).
- **Tabla `crm_agenda_eventos`**: ENUM origen_tipo extendido con 2 valores.
- **JS transversal**: `rxn-filter-persistence.js` ahora persiste sort/dir en TODOS los listados.
- **UI**: el form de Presupuestos creció considerablemente — fila extra en cabecera + textarea de descripción más alto + warnings inline + banner sticky de errores. Sigue cabiendo en el grid de 12 cols.

---

## Decisiones tomadas

1. **DESC_ADIC se descarta para descripciones reales** — Tango lo limita a 20 chars. El array DESCRIPCION_ADICIONAL_DTO[] sí soporta texto largo.
2. **Versionado con árbol plano (todas las versiones apuntan a la raíz, no al padre directo)** — más simple de queryar.
3. **Numero secuencial nuevo en cada versión** — no reusar el numero del padre (rompería UNIQUE de DB).
4. **Auto-marca email_verificado=1 al cambiar pass como rxn_admin** — el superadmin ya validó al usuario fuera del sistema.
5. **`autocomplete="new-password"` en inputs de password** — evita autofill malicioso del browser.
6. **Lock post-Tango replica patrón de PDS** — `<fieldset disabled>` cuando hay nro_comprobante.
7. **WHERE del UPDATE de usuarios solo por PK** — el aislamiento de tenant ya está en getByIdForContext.
8. **Persistencia de sort/dir en filter-persistence global** — afecta a todos los listados.

---

## Validación

- Todas las migraciones corridas en local con `php tools/run_migrations.php`. 5 OK, 0 error.
- `php -l` sobre todos los archivos PHP tocados, sin errores.
- `node -c` sobre los JS, sin errores.
- Test de createNewVersion con datos reales (presupuesto #34 → versión #36 con `version_padre_id=34`, `version_numero=2`) ✅.
- Test del helper `chunkDescripcion()` con 7 casos (corto, justo 50, largo con palabras, multilínea, vacío, espacios, palabra extremadamente larga) ✅.
- Test del payload completo del mapper con descripción multilínea — output idéntico al ejemplo de Tango que pasó Charly ✅.
- Logs de `[AuthService::attempt]` confirman password_verify=true tras el fix del UPDATE ✅.
- Rastreo en logs: `[UsuarioRepository::save] UPDATE usuario #N affectedRows=1` aparece en cada cambio post-fix.

---

## Pendiente / follow-ups

- **P3 de la pulida UX**: queda para la próxima sesión.
  1. Auto-save draft cada 30s en localStorage para Presupuestos (aprovechando la tabla `drafts` que ya existe — solo falta el wiring).
  2. Indicador "no guardado" tipo dot rojo en el badge del header cuando el form está dirty.
  3. Hotkey Ctrl+S = Guardar.
- **Restaurar descripción al original** desde la UI: hoy si el operador edita la descripción no hay un botón "restaurar" — tiene que hacerlo a mano. Si Charly lo pide, sumar un mini-botón al lado del textarea.
- **Diseño del sync asincrónico de catálogos via n8n** (release 1.29.x sigue pendiente, ya guardado en Engram con `topic_key: presupuestos/sync-asincrono-n8n` desde el 2026-04-29).
- **Cliente sin id_gva14_tango**: hoy el warning del form está preparado pero no se llena automáticamente porque el endpoint `/clientes/contexto` no devuelve el flag. Sumarlo cuando se modifique ese endpoint.

---

## Relevant Files

### Migraciones
- `database/migrations/2026_04_29_02_alter_crm_presupuestos_add_cotizacion_vigencia_leyendas.php`
- `database/migrations/2026_04_29_03_alter_crm_presupuesto_items_add_descripcion_original.php`
- `database/migrations/2026_04_29_04_alter_agenda_eventos_origen_tipo_presupuesto_extra.php`
- `database/migrations/2026_04_29_05_alter_crm_presupuestos_add_versionado.php`

### Presupuestos
- `app/modules/CrmPresupuestos/PresupuestoController.php`
- `app/modules/CrmPresupuestos/PresupuestoRepository.php`
- `app/modules/CrmPresupuestos/PresupuestoTangoService.php`
- `app/modules/CrmPresupuestos/CrmPresupuestoPrintContextBuilder.php`
- `app/modules/CrmPresupuestos/views/form.php`
- `app/modules/CrmPresupuestos/views/index.php`
- `app/modules/CrmPresupuestos/MODULE_CONTEXT.md`
- `public/js/crm-presupuestos-form.js`

### Tango
- `app/modules/Tango/Mappers/TangoOrderMapper.php`

### Agenda
- `app/modules/CrmAgenda/AgendaProyectorService.php`
- `app/modules/CrmAgenda/AgendaRepository.php`
- `app/modules/CrmAgenda/views/index.php`

### Usuarios + Auth (bugfix crítico)
- `app/modules/Auth/UsuarioRepository.php`
- `app/modules/Auth/AuthService.php`
- `app/modules/Usuarios/UsuarioController.php`
- `app/modules/Usuarios/UsuarioService.php`
- `app/modules/Usuarios/views/editar.php`
- `app/modules/Usuarios/views/crear.php`

### JS transversal
- `public/js/rxn-filter-persistence.js`

### Routing
- `app/config/routes.php`

### Versión
- `app/config/version.php`
