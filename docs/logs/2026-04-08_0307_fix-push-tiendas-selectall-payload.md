# 2026-04-08 0307 — Fix: Push bloqueado en Tiendas, Select-All filtro, Payload Sync

## Bugs resueltos

### 1. Push Artículos siempre pedaleaba en Tiendas (y CRM desde CRUD)
`ArticuloController::pushToTango()` tenía `if ($area !== 'crm') return 403`.
Como el botón en Tiendas llama a `/mi-empresa/articulos/{id}/push-tango`,
el controller recibía la llamada, veía que el área era "tiendas" y devolvía 403.
El frontend intentaba parsear 403 como JSON de error, y el JS del CRUD
(en Artículos/views/index.php) no manejaba el `.success === false` correctamente,
dejando el spinner girando.

**Fix**: se eliminó el guard CRM, ahora `forArea($area)` resuelve la config correcta.

### 2. `pushToTangoByLocalId` y `pullFromTangoByLocalId` retornaban bool
El controller no podía exponer el payload de la operación Tango.
Ahora retornan `array` con `tango_id`, `payload_enviado` y `snapshot_tango`.

### 3. Select-All seleccionaba filas ocultas (filtradas/paginadas)
El handler del `#rxnsync-select-all` hacía `querySelectorAll('.rxnsync-row-check')`
sin filtrar por visibilidad, marcando registros que no estaban en la página/filtro actual.

**Fix**: ahora filtra `.filter(c => c.closest('tr').style.display !== 'none')`.
También se clona el nodo para prevenir acumulación de listeners entre recargas de tab.

### 4. Payload no visible en frontend
El modal `rxnConfirm` usaba `textContent` estrictamente, descartando HTML.
Se agregó detección de tags HTML en el mensaje; si hay tags → `innerHTML`, si no → `textContent`.
El `doSingleSync` ahora construye un `<details>/<pre>` expandible con el payload JSON.

## Impacto
- ✅ Push/Pull Artículos Tiendas: operativo
- ✅ Push/Pull Artículos CRM CRUD: operativo
- ✅ Push/Pull Clientes CRM CRUD: operativo (mismo patrón, RxnSync controller)
- ✅ Select-All respeta filtro y paginación
- ✅ Payload Tango visible en modal post-sync con `<details>` expandible
- ✅ rxnAlert acepta HTML controlado (solo si el mensaje contiene tags)
