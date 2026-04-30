# Módulo RxnPwa — PWA mobile (Presupuestos)

> **Iteración 42 — PWA Fase 1 (Bloque A).** Andamiaje del módulo + catálogo offline-readable con versionado server-side. Las fases 2 y 3 extienden este módulo.

## Propósito

Habilitar a los **vendedores en campo** a operar Presupuestos desde un dispositivo móvil con conectividad intermitente. La PWA se instala como app nativa, descarga el catálogo completo de la empresa al dispositivo, y permite (en fases siguientes) crear presupuestos offline que sincronizan al volver online.

**Decisión central**: scope SOLO Presupuestos (PDS más adelante, mismo patrón). El catálogo se baja entero (no delta-sync) porque el tamaño esperado es 100–5000 ítems por empresa (10k excepcional).

## Roadmap (3 fases)

| Fase | Bloque | Estado | Alcance |
|------|--------|--------|---------|
| **1** | A | ✅ Implementada | Andamiaje + manifest + SW + catálogo offline-readable + shell + versionado por hash |
| **2** | B | 🔲 Pendiente | `form_mobile.php` + creación de presupuestos offline con `#TMP-<uuid>` (2 sesiones) |
| **3** | C | 🔲 Pendiente | Sync queue + reconciliación al volver online (1 sesión) |

## Arquitectura

```
[Navegador mobile / instalada como PWA]
        │
        │  /rxnpwa/presupuestos  (shell HTML, scope PWA)
        ▼
   ┌───────────────────────┐
   │  RxnPwaController     │  presupuestosShell()
   │                       │  catalogVersion()
   │                       │  catalogFull()
   └───────────┬───────────┘
               │
               ▼
   ┌───────────────────────┐         ┌─────────────────────────┐
   │  RxnPwaCatalogService │ ◄──────▶│  RxnPwaCatalogVersion   │
   │  (consolida + hash)   │         │  Repository             │
   └───────────┬───────────┘         │  (rxnpwa_catalog_       │
               │                     │   versions, 1/empresa)  │
               ▼                     └─────────────────────────┘
   ┌───────────────────────────────────────────────────────────┐
   │  Tablas fuente (read-only para este módulo):              │
   │  crm_clientes · crm_articulos · crm_articulo_precios ·    │
   │  crm_articulo_stocks · crm_catalogo_comercial_items       │
   └───────────────────────────────────────────────────────────┘
```

## Endpoints

| Método | Ruta | Auth | Devuelve |
|--------|------|------|----------|
| GET | `/rxnpwa/presupuestos` | Login + CRM access | Shell HTML (registra SW + UI estado catálogo) |
| GET | `/api/rxnpwa/catalog/version` | Login + CRM access | `{ok, hash, generated_at, items_count, size_bytes}` |
| GET | `/api/rxnpwa/catalog/full` | Login + CRM access | `{ok, hash, generated_at, ..., data: {clientes, articulos, ...}}` + header `X-Rxnpwa-Catalog-Hash` |

**Auth**: cookie de sesión del backoffice. La PWA reutiliza el login existente — el SW propaga las cookies en cada fetch automáticamente. **No** hay flujo de token mobile separado.

## Catálogo offline — entidades incluidas

Todas filtradas por `empresa_id` (multi-tenant estricto):

- `clientes` — `crm_clientes` activos (`deleted_at IS NULL`).
- `articulos` — `crm_articulos`.
- `precios` — `crm_articulo_precios` (todas las listas).
- `stocks` — `crm_articulo_stocks` (todos los depósitos).
- `condiciones_venta`, `listas_precio`, `vendedores`, `transportes`, `depositos`, `clasificaciones_pds` — `crm_catalogo_comercial_items` por tipo.

## Versionado (hash global por empresa)

Tabla `rxnpwa_catalog_versions` (1 fila por empresa, UNIQUE empresa_id):

| Columna | Tipo | Uso |
|---------|------|-----|
| `empresa_id` | INT (UQ) | Tenant. |
| `hash` | CHAR(40) NULL | SHA-1 del JSON serializado del catálogo. NULL = stale, recalcular al próximo GET. |
| `generated_at` | DATETIME NULL | Cuándo se calculó. Lo mostramos en el badge del shell. |
| `payload_size_bytes` | INT | Tamaño del JSON (para mostrar al usuario y planificar GC). |
| `payload_items_count` | INT | Total de filas. Idem. |

### Cuándo se invalida

`RxnPwaCatalogVersionRepository::invalidate($empresaId)` se llama **inmediatamente después** de un sync exitoso en estos 5 puntos (Fase 1):

1. `RxnSync\RxnSyncController::syncPullArticulos()` — luego del audit.
2. `RxnSync\RxnSyncController::syncPullClientes()` — luego del audit.
3. `RxnSync\RxnSyncController::syncCatalogos()` — luego del sync de catálogos comerciales.
4. `Tango\Controllers\TangoSyncController::syncPrecios()` — luego del syncPrecios exitoso.
5. `Tango\Controllers\TangoSyncController::syncStock()` — luego del syncStock exitoso.

**Decisión P0** (con Charly, Iteración 42): **invalidación on-write** en lugar de recálculo on-read. Razón: el endpoint `/version` lo va a pegar el cliente varias veces por sesión mobile; recalcular el SHA-1 sobre 5000 artículos × N listas × M depósitos cada vez es desperdicio.

### Decisión pendiente de validar en producción

Cuando se vea el comportamiento real (¿cada cuánto cambia el hash en una empresa típica? ¿hay ráfagas de syncs que generan flapping?), evaluar:

- Si los syncs son frecuentes pero las descargas mobile son baratas → mantener invalidación on-write tal cual.
- Si los syncs son frecuentes y descargar es caro para el vendedor → sumar throttling (no invalidar más de N veces por hora) o un mecanismo de "minor change" (solo precios → no invalida estructura, solo precios).

El badge "su app no se sincroniza hace Xh" del shell (parametrizado en `STALE_HOURS_THRESHOLD` en `rxnpwa-register.js`) es el primer indicador para decidir el threshold óptimo.

## Service Worker (`public/sw.js`)

**Coexistencia con Web Push**: el SW estaba operativo desde release 1.27.0 para Web Push (`push` + `notificationclick`). En esta iteración se extendió **sin tocar** ese flujo. La sección PWA solo intercepta requests dentro de `/rxnpwa/`.

Estrategias:

| Path | Estrategia | Cache |
|------|-----------|-------|
| `/rxnpwa/*` (navegación) | network-first | `rxnpwa-v1-…-shell` |
| `/js/pwa/*`, `/icons/*`, `/manifest.webmanifest` | stale-while-revalidate | `rxnpwa-v1-…-assets` |
| `/api/rxnpwa/*` | red siempre (cache local en IndexedDB) | — |
| Resto | passthrough (no intercepta) | — |

`RXNPWA_VERSION` en el SW debe **bumpearse cuando cambien los assets cacheables** (manifest, íconos, `rxnpwa-register.js`, etc). Al activar la nueva versión, el SW elimina caches con prefijo `rxnpwa-` que no coincidan con la actual.

## Storage cliente (IndexedDB)

DB: `rxnpwa`, version 1. Stores (1 por entidad del catálogo) + `__meta`:

```
rxnpwa
├── clientes              (rows tal cual de crm_clientes)
├── articulos
├── precios
├── stocks
├── condiciones_venta
├── listas_precio
├── vendedores
├── transportes
├── depositos
├── clasificaciones_pds
└── __meta                {hash, generated_at, items_count, size_bytes, empresa_id, synced_at}
```

Cada `saveCatalog()` clear-and-fill por store (no upserts). Costo: barato para 5k ítems, predecible.

API: `window.RxnPwaCatalogStore` — ver [public/js/pwa/rxnpwa-catalog-store.js](../../public/js/pwa/rxnpwa-catalog-store.js).

## UI shell — estados visibles

`rxnpwa-register.js` renderiza un badge según estado:

| Estado | Badge | Cuándo |
|--------|-------|--------|
| 🟢 Catálogo al día | `success` | hash local == hash server, edad < threshold |
| 🟡 Catálogo desactualizado (tiempo) | `warning` | edad ≥ `STALE_HOURS_THRESHOLD` (6 hs por default) |
| 🟡 Hay versión nueva | `warning` | hash local ≠ hash server |
| 🔴 Sin catálogo offline | `danger` | IndexedDB vacía |
| 📡 Modo offline | `info` | `navigator.onLine === false` y hay catálogo |
| ⚠️ Sin red ni catálogo | `danger` | offline y vacío |
| ⚠️ Error | `danger` | falló sync |

## Convenciones del módulo

- **Naming endpoint**: prefijo `/api/rxnpwa/` para todo lo que use la PWA.
- **Naming asset**: `/js/pwa/rxnpwa-*.js`, `/icons/rxnpwa-*.png`. El prefijo `rxnpwa-` evita pisar el namespace `rxn-` global existente.
- **Cookie de sesión**: lo único requerido para auth. Si la sesión expira mientras la PWA está abierta, los fetches a `/api/rxnpwa/*` van a recibir el redirect de Auth → manejar en Fase 3 con un "tu sesión expiró, reabrí desde el browser".

## Seguridad transversal (checklist `docs/seguridad/convenciones.md`)

- ✅ **Aislamiento multi-tenant**: todas las queries del CatalogService filtran por `Context::getEmpresaId()`. La tabla `rxnpwa_catalog_versions` usa UNIQUE empresa_id.
- ✅ **Auth**: `requireLogin()` + `requireCrmAccess()` en los 3 endpoints. Sin sesión → redirect a login (cookie); sin acceso CRM → 403.
- ✅ **CSRF**: endpoints son GET puros, no muta nada server-side. La invalidación del hash la dispara el flujo de sync, no el cliente PWA.
- N/A **Uploads**: este módulo no recibe archivos.
- N/A **Rate limiting**: la frecuencia razonable de `/version` (≤ 1 cada 30s) no justifica rate-limit dedicado en Fase 1. Reevaluar si vemos abuso.
- ✅ **IDOR**: el `empresa_id` viene de `Context`, nunca del cliente. Imposible pedir catálogo de otra empresa.
- ✅ **XSS**: la única salida HTML del shell escapa `empresaId` y `pageTitle` con `htmlspecialchars`. El JS `renderBadge` escapa con `escapeHtml`.
- N/A **Tokens**: no se emiten tokens propios.

## Files

- `app/modules/RxnPwa/RxnPwaController.php` — entry + endpoints.
- `app/modules/RxnPwa/RxnPwaCatalogService.php` — consolida payload + hash.
- `app/modules/RxnPwa/RxnPwaCatalogVersionRepository.php` — UPSERT + invalidate.
- `app/modules/RxnPwa/views/presupuestos_shell.php` — shell HTML mobile.
- `database/migrations/2026_04_30_01_create_rxnpwa_catalog_versions.php` — tabla.
- `public/manifest.webmanifest` — manifest PWA.
- `public/sw.js` — Service Worker (extendido sin romper Web Push).
- `public/icons/rxnpwa-{192,512}.png` — íconos placeholder (regenerables vía `tools/generate_rxnpwa_icons.php`).
- `public/js/pwa/rxnpwa-register.js` — registro SW + UI estado.
- `public/js/pwa/rxnpwa-catalog-store.js` — wrapper IndexedDB.

**Hooks de invalidación** (importante mantener si se mueven):

- `app/modules/RxnSync/RxnSyncController.php::syncPullArticulos`, `syncPullClientes`, `syncCatalogos`.
- `app/modules/Tango/Controllers/TangoSyncController.php::syncPrecios`, `syncStock`.

## Pendiente

- Generar íconos finales (placeholder en negro #0f172a por ahora).
- Throttling de invalidación si se observa flapping en prod.
- Fase 2: `form_mobile.php` + creación offline.
- Fase 3: cola de envío + reconciliación.
