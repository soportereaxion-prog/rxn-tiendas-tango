# 2026-04-08_0147 — RXN-Sync: Estabilización CORS + CRUD Masivo

## Qué se hizo

### A — Fixes UX urgentes

**A1 — `confirm()` nativo → `rxnConfirm()` del sistema**
- `Articulos/views/index.php`: El botón Push individual ya no usa `confirm()`. Usa `rxnConfirm()` con modal del sistema.
- `RxnSync/views/index.php`: Todas las confirmaciones migradas a `rxnConfirm()`.

**A2 — `pageSize` dinámico desde `cantidad_articulos_sync`**
- `ArticuloController.php`: Lee `cantidad_articulos_sync` de `EmpresaConfigRepository::forCrm()` y lo pasa como `$syncBatch` al service.
- `RxnSyncService.php`: `pushToTangoByLocalId()` y `resolveTangoIdBySku()` aceptan `$pageSize` como parámetro.
- Antes el pageSize era `200` hardcodeado. Con `4500` artículos nunca encontraba el item. Ahora usa el valor configurado por empresa.

### B — CORS definitivo

**Root cause confirmado**: `fetchCatalog()` usaba `pageSize=100` para maestros de configuración (Empresas, Listas, Depósitos). Con catálogos grandes hacía hasta 30 requests HTTP en cadena = timeout inevitable.

**B1 — `pageSize=1000` para maestros de configuración**
- `TangoApiClient::getMaestroEmpresas()`, `getMaestroListasPrecio()`, `getMaestroDepositos()`: pasan `maxPageSize=1000`.
- `fetchCatalog()` y `fetchRichCatalog()` aceptan `$maxPageSize` como parámetro (default: 100).
- Resultado: 1 sola request HTTP en lugar de 30+ para cargar la pantalla de Configuración.

**B2 — Safety net reducido**
- Loop de paginación reducido de `$page >= 30` a `$page >= 10` en ambos métodos.

**B3 — Logs de diagnóstico**
- `EmpresaConfigController::getConnectTangoMetadata()`: emite `error_log` con timestamps por catálogo.
- Formato: `[Tango-Meta] getMaestroEmpresas: 234ms — 1 items`.
- Permite ver en logs de Apache exactamente cuál catálogo opera diferente.

### C — CRUD Masivo RXN-Sync

**C1 — Auditoría contextual por tab**
- Eliminado el botón global "Auditoría Completa" del header.
- Reemplazado por barra `rxnsync-tab-actions` bajo los tabs con botón "Auditoría Artículos / Auditoría Clientes" que cambia según el tab activo.

**C2 — Checkboxes y acciones masivas**
- `RxnSync/views/index.php`: barra `rxnsync-bulk-bar` que aparece cuando hay ≥ 1 checkbox seleccionado.
  - Botones: Push ↑ Seleccionados, Pull ↓ Seleccionados, Auditoría Tab Activo, Cancelar.
- `tabs/articulos.php` y `tabs/clientes.php`: columna checkbox + Select All en header.

**C3 — Columnas mejoradas en tabs**
- Agregada columna "ID Tango" en ambas vistas.
- Botón Push limpio (solo ícono) para compacidad.

**C4 — Backend de acciones masivas**
- `RxnSyncController`: nuevos métodos `auditarClientes()`, `pushMasivo()`, `pullMasivo()`.
- `RxnSyncService`: nuevos métodos `auditarClientes(int $empresaId)` y `pullFromTangoByLocalId(int $empresaId, int $localId, string $entidad)`.
- `routes.php`: 8 rutas nuevas (`push-masivo`, `pull-masivo`, `auditar-clientes` × 2 contextos + Tiendas).

**Pull ↓ comportamiento**: actualiza `nombre` (artículos) o `razon_social` (clientes) en la tabla local con los datos que trae de Tango. Solo registros que ya tengan vínculo en el pivot.

## Por qué

- El sistema estaba "completamente roto" comparado con la versión anterior.
- Los `alert()` y `confirm()` nativos violaban la política de UX del proyecto.
- El Match Suave fallaba porque buscaba en 200 items cuando el catálogo tiene 4500.
- Los catálogos de maestros de configuración tardaban 30+ requests HTTP causando timeout que se veía como CORS.

## Impacto

- Lectura / escritura en: `rxn_sync_status`, `crm_articulos`, `crm_clientes`
- No hay cambios de schema — sin migración requerida
- Los masivos procesan de a uno con manejo de errores individual (nunca aborta todo el lote)

## Seguridad

- Aislamiento `empresa_id` en todos los métodos del service y controller
- Pull ↓ solo sobreescribe campos no-críticos (nombre/razon_social)
- Validación server-side de los IDs recibidos en masivos
- `in_array($entidad, ['cliente', 'articulo'])` en pushToTango
- `set_time_limit(120/180)` solo en acciones de sync prolongadas
