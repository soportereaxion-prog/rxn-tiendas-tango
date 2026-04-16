# 2026-04-07 22:03 — RXN-Sync: Fix CORS/Timeout + Push Individual Artículos + Auditoría Pull

## ¿Qué se hizo?

Se resolvieron tres problemas interconectados del módulo de sincronización con Tango:

### 1. Fix CORS/Timeout (falso positivo)
- El error `Failed to fetch (TypeError)` en el navegador era causado por el servidor PHP al alcanzar el límite de `max_execution_time` (30s) durante la llamada a 3 catálogos en cadena en `getConnectTangoMetadata()`.
- Se agregó `set_time_limit(120)` al inicio del método en `EmpresaConfigController`.
- El fix previo de paginación con `$seenFirstIds` (sin md5) permanece activo y es el correcto.

### 2. Push Individual de Artículo (nuevo flujo desacoplado)
- Se refactorizó `RxnSyncService::pushToTango()` para delegar en el nuevo método `pushToTangoByLocalId()`.
- `pushToTangoByLocalId()` funciona directamente con el ID local del artículo sin requerir que `rxn_sync_status` esté pre-poblada.
- Resuelve el ID en Tango en este orden: pivot existente → Match Suave por `codigo_externo` (búsqueda en primera página de process 87).
- Se agregó `ArticuloController::pushToTango(string $id)`: endpoint AJAX JSON, solo CRM, con `set_time_limit(60)`.
- Se agregó método privado `resolveTangoIdBySku()` en RxnSyncService.

### 3. Botón Push en CRUD de Artículos CRM
- Se agregó el botón `<i class="bi bi-cloud-upload">` en las acciones de cada fila del listado.
- Solo visible si `$showSyncActions === true` (CRM) y el artículo tiene `codigo_externo` no vacío.
- Feedback visual: spinner durante la operación, ícono de check al completar exitosamente.
- Usa `rxnAlert` si está disponible, `confirm()` nativo si no.

### 4. Auditoría Pull Completa (desbloqueada)
- Se habilitó `RxnSyncController::auditarArticulos()` con llamada real a `RxnSyncService::auditarArticulos()`.
- El botón "Auditoría Completa" en la consola RXN-Sync ahora hace `fetch()` real al endpoint.
- Popula `rxn_sync_status` con todos los artículos locales matcheados contra Tango.
- Recarga el tab activo de la consola con los resultados.

## ¿Por qué?
El flujo de sincronización no tenía un punto de entrada viable para probar sin la tabla de auditoría pre-poblada. El error de CORS impedía validar la conectividad desde la UI.

## Archivos modificados
- `app/modules/EmpresaConfig/EmpresaConfigController.php` — `set_time_limit(120)` en metadata
- `app/modules/RxnSync/RxnSyncService.php` — `pushToTangoByLocalId()`, `resolveTangoIdBySku()`, refactor `pushToTango()`
- `app/modules/RxnSync/RxnSyncController.php` — `auditarArticulos()` real
- `app/modules/Articulos/ArticuloController.php` — `pushToTango(string $id)` AJAX action
- `app/modules/Articulos/views/index.php` — botón Push en fila + script AJAX + CSS spinner
- `app/modules/RxnSync/views/index.php` — botón Auditoría desprotegido con fetch real
- `app/config/routes.php` — 2 rutas nuevas

## Decisiones técnicas
- Match Suave por SKU usa `pageSize=200` de la primera página. Si el artículo está en páginas posteriores (catálogos grandes), el match fallará con mensaje claro al usuario.
- Solución escalable futura: cachear el índice SKU→ID en `rxn_sync_status` con la auditoría Pull.
- No se modificó el flujo de sincronización de Tiendas (read-only intacto).
- Sin migraciones involucradas (tablas ya existentes).
