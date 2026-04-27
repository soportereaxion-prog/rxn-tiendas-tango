# Release 1.24.0 — Zoom personal + Llamadas↔PDS + Edición admin de turnos

**Fecha:** 2026-04-27
**Build:** 20260427.1
**Tipo:** Feature release (3 mejoras de UX y trazabilidad)

---

## 1) Zoom personal en Mi Perfil

### Qué se hizo
Selector "Zoom de la Interfaz" en `Mi Perfil → Preferencias visuales` con grilla `[75, 80, 90, 100, 110, 125, 150]`. Persistido en `usuarios.preferencia_zoom` (TINYINT UNSIGNED NOT NULL DEFAULT 100).

### Por qué
Charly pidió la posibilidad de zoom global por usuario (equivalente a la lupa del navegador) para Tiendas y CRM.

### Decisión técnica clave
Probamos 3 técnicas en orden:

1. ❌ `style="zoom: X%"` en `<body>` → escala el contenido pero deja `100vw` fijo. Bandas vacías a la derecha (zoom < 100%) o desbordamiento horizontal (zoom > 100%).
2. ❌ `style="zoom: X%"` en `<html>` → escala TODO incluido el viewport efectivo. Cards quedan flotando chiquitos sin centrar.
3. ✅ `style="font-size: X%"` en `<html>` → exacto comportamiento del Ctrl+/Ctrl- de Chrome. Bootstrap 5 usa `rem` para spacing/dimensiones de componentes, así que reflowea proporcional sin tocar el viewport.

Discovery anotado en Engram (topic_key `ui/zoom-global-usuario`) para no repetir el zigzag.

### Archivos
- `database/migrations/2026_04_27_00_alter_usuarios_add_preferencia_zoom.php`
- `app/core/Helpers/UIHelper.php` (refactor con `loadUserUiPrefs()`, `clampZoom()`, inyección en `<html>`)
- `app/modules/Usuarios/views/mi_perfil.php`
- `app/modules/Usuarios/UsuarioPerfilController.php::guardar`
- `app/shared/views/admin_layout.php` (call site al `getBodyZoomStyle`, hoy no-op)

### Limitación conocida
Imágenes con `width` fijo en `px` (ej: logos) NO se escalan. Si se necesita en el futuro, hay que migrar esos `width: NNpx` a `width: NNrem`.

---

## 2) Vínculo formal Llamadas → PDS

### Qué se hizo
Nueva columna `crm_pedidos_servicio.llamada_id INT NULL` con índice `idx_pds_llamada`. Persistencia automática del vínculo cuando se genera el PDS desde el botón "Generar PDS" del listado de Llamadas. Columna nueva "PDS" en el listado con badge clickable + filtro avanzado `pds_estado` ("Con PDS" / "Sin PDS").

### Por qué
Antes el vínculo era solo el flujo de creación: la llamada generaba la URL prellenada, pero el PDS nacía huérfano de la llamada. No se podía filtrar ni reportar.

### Backfill heurístico
La migración incluye un UPDATE que matchea PDS huérfanos contra llamadas del **mismo cliente** cerradas en la **ventana de 2 horas previas** al inicio del PDS, eligiendo la llamada más cercana en el tiempo. Conservador a propósito (mismo cliente + ventana corta) para evitar falsos positivos. Idempotente (`WHERE llamada_id IS NULL`).

En local matcheó 0/104 PDS porque solo 11 llamadas tienen `cliente_id` seteado en la base de prueba — esperable, hacia adelante el flujo persiste automático.

### Bug fix incluido
El primer commit puso el `<th>` PDS después de Grabación pero el `<td>` antes — los datos quedaban en columnas equivocadas en el render. Reordenado.

### Archivos
- `database/migrations/2026_04_27_01_alter_crm_pds_add_llamada_id.php` (ALTER + índice + backfill)
- `app/modules/CrmPedidosServicio/PedidoServicioRepository.php` (INSERT/UPDATE/buildPayload con `llamada_id`)
- `app/modules/CrmPedidosServicio/PedidoServicioController.php` (validación contra `crm_llamadas WHERE empresa_id` — anti-IDOR)
- `app/modules/CrmPedidosServicio/views/form.php` (hidden input)
- `app/modules/CrmLlamadas/views/index.php` (URL del botón con `&llamada_id=` + columna PDS)
- `app/modules/CrmLlamadas/CrmLlamadaRepository.php` (subselects pds_id/pds_numero + filterMap pds_estado)

---

## 3) Edición admin de turnos + visibilidad del cambio

### Qué se hizo
- Nuevo método `HoraService::editar()` con validación de solapamientos, audit obligatorio (acción `editar`), notify al owner si actor ≠ owner, re-proyección a la agenda.
- Endpoints `/mi-empresa/crm/horas/{id}/editar` (GET form + POST store) gateados por `hasAdminPrivileges` + CSRF.
- Vista `editar.php` con motivo obligatorio.
- Botón lápiz amarillo en cada fila del listado de horas (solo admin).
- Visibilidad inline: cada `<tr>` con audit muestra `bi-pencil-square` al lado del ID con tooltip completo (acción + autor + fecha + motivo). Cargado en una sola query batch (`loadLastAudits`).

### Por qué
Charly necesita corregir turnos de operadores cuando se olvidaron de cerrar o cargaron mal. Sin trazabilidad esto sería opaco — con el audit + notificación al dueño + ícono visible en el listado queda transparente.

### Bonus — selector "Cargar para…" en diferido
El form `/mi-empresa/crm/horas/diferido` ahora suma un selector visible solo para admin con la lista de usuarios activos del tenant. `HoraService::cargarDiferido()` aceptó nuevo param `actorUserId` — si difiere del owner, audit (`cargar_diferido`) + notify. Validación cross-tenant: el `target_user_id` se contrasta con `loadUsuariosActivos($empresaId)` antes de usar (anti-IDOR).

Decisión: el selector está SOLO en diferido, NO en el turnero en vivo (start/stop). Cargar en nombre de otro = operación a posteriori por definición; en vivo no tiene sentido porque el admin no puede iniciar un turno que el otro está físicamente trabajando.

### Archivos
- `app/modules/CrmHoras/HoraService.php` (`editar`, `cargarDiferido` con actorUserId)
- `app/modules/CrmHoras/HoraController.php` (`editarForm`, `editarStore`, `loadLastAudits`, `loadUsuariosActivos`)
- `app/modules/CrmHoras/views/editar.php` NUEVO
- `app/modules/CrmHoras/views/index.php` (columna Acciones + ícono audit)
- `app/modules/CrmHoras/views/diferido.php` (selector "Cargar para…")
- `app/config/routes.php` (rutas GET/POST de `/horas/{id}/editar`)

---

## Validación

- ✅ `php -l` limpio en todos los archivos modificados.
- ✅ Migraciones aplicadas en local sin error.
- ✅ Charly validó visualmente las 3 features en su entorno local (zoom comportándose como Chrome, columnas Llamadas correctas, edición admin funcional).

## Pendiente (si surge)

- Visualización del logo escalado con zoom (limitación conocida — requiere migrar a `rem`).
- Backfill manual masivo de Llamadas↔PDS si en prod resulta poco efectivo el heurístico — habría que hacer una herramienta para vincular manualmente desde la UI.

---

## Migraciones que viajan en este OTA

1. `2026_04_27_00_alter_usuarios_add_preferencia_zoom.php`
2. `2026_04_27_01_alter_crm_pds_add_llamada_id.php` (incluye backfill)
3. `2026_04_27_02_seed_customer_notes_release_1_24_0.php`
