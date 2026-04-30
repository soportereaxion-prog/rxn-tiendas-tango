# Iteración 42 — RXN PWA Fase 1: andamiaje mobile + catálogo offline

**Fecha:** 2026-04-30
**Release:** 1.31.0
**Build:** 20260430.2

---

## Qué se hizo

Arrancamos la PWA mobile de Presupuestos para que los vendedores trabajen en
campo con conectividad intermitente. Esta release entrega el **Bloque A** del
roadmap (Fase 1 de 3). Las fases 2 y 3 se mantienen pendientes.

### Módulo nuevo `App\Modules\RxnPwa`

Controller + Service + Repository + view shell + MODULE_CONTEXT con la doc completa.

### 3 endpoints

| Método | Ruta | Devuelve |
|--------|------|----------|
| GET | `/rxnpwa/presupuestos` | Shell HTML mobile (registra SW + UI) |
| GET | `/api/rxnpwa/catalog/version` | `{ok, hash, generated_at, items_count, size_bytes}` |
| GET | `/api/rxnpwa/catalog/full` | catálogo completo + hash en header |

Auth: cookie de sesión + acceso CRM. Multi-tenant estricto via `Context::getEmpresaId()`.

### Catálogo offline — 10 entidades por empresa

`crm_clientes` activos · `crm_articulos` · `crm_articulo_precios` (todas las listas) ·
`crm_articulo_stocks` (todos los depósitos) · `crm_catalogo_comercial_items` por tipo
(condicion_venta, lista_precio, vendedor, transporte, deposito, clasificacion_pds).

### Versionado por hash global por empresa

Tabla nueva `rxnpwa_catalog_versions` (1 fila por empresa, UNIQUE empresa_id):
hash SHA-1, generated_at, payload_size_bytes, payload_items_count.

**Invalidación on-write** (decisión P0 con Charly): los 5 syncs existentes llaman
`invalidate()` inmediatamente después del éxito, marcando el hash como NULL. El
próximo GET `/version` recalcula on-the-fly. Hooks puestos en:

1. `RxnSync::syncPullArticulos`
2. `RxnSync::syncPullClientes`
3. `RxnSync::syncCatalogos`
4. `Tango::syncPrecios`
5. `Tango::syncStock`

### Service Worker extendido (sin romper Web Push)

El SW de release 1.27.0 (Web Push) se mantuvo 100% intacto. Le sumamos sección
PWA con `RXNPWA_VERSION` versionada, install/activate con caches separados y
fetch handler que solo intercepta paths `/rxnpwa/`, `/js/pwa/`, `/icons/` y
`/manifest.webmanifest`. Todo lo demás del backoffice clásico es passthrough.

Estrategias:
- Shell HTML → network-first con fallback a cache.
- Assets PWA (íconos, manifest, JS de PWA) → stale-while-revalidate.
- `/api/rxnpwa/*` → siempre red. El cache "real" es IndexedDB.

### Storage cliente: IndexedDB wrapper vanilla

`/js/pwa/rxnpwa-catalog-store.js` — DB `rxnpwa` con 10 stores + `__meta`.
`saveCatalog()` hace clear-and-fill por store (predecible para 5k ítems). Sin
dependencias externas.

### UI shell — badge dinámico de estado

`/js/pwa/rxnpwa-register.js`:

| Estado | Cuándo |
|--------|--------|
| 🟢 Catálogo al día | hash local == server, edad < 6h |
| 🟡 Desactualizado por tiempo | edad ≥ `STALE_HOURS_THRESHOLD` (6h por default) |
| 🟡 Hay versión nueva | hash local ≠ server |
| 🔴 Sin catálogo offline | IndexedDB vacía |
| 📡 Modo offline | sin red, hay catálogo cacheado |
| ⚠️ Sin red ni catálogo | sin red, IndexedDB vacía |
| ⚠️ Error | falló sync |

El threshold de 6h es inicial — afinarlo viendo el comportamiento real en
producción es la primera tarea de la próxima sesión PWA.

### Manifest + íconos

`public/manifest.webmanifest` con `start_url=/rxnpwa/presupuestos`,
`scope=/rxnpwa/`, `display=standalone`, theme color `#0f172a`. 2 íconos PNG
placeholder (`/icons/rxnpwa-192.png`, `rxnpwa-512.png`) regenerables vía
`tools/generate_rxnpwa_icons.php`.

---

## Por qué

Charly viene a competir con otra app de presupuestos mobile. La PWA permite:
- Instalación nativa sin pasar por App Store.
- Operación offline en zonas de mala señal.
- Reutilización del backoffice y el modelo de datos existente.

Sin la PWA los vendedores no pueden cotizar en campo. Es el bloqueante #1 del
crecimiento del módulo de Presupuestos.

---

## Validación local

Smoke backend contra empresa #1:
- 475 clientes + 4500 artículos + 32 listas → **6423 ítems**, payload ~877 KB.
- Hash determinístico estable entre 2 calls consecutivas.
- `invalidate()` + recálculo da el mismo hash (payload determinístico).
- `getFullCatalog()` devuelve `data.clientes (475)`, `data.articulos (4500)`, `data.listas_precio (32)`.
- Routing: las 3 rutas registradas ok.
- Lint: PHP de los 7 archivos modificados sin errores.

Smoke browser pendiente (lo hace Charly):
1. Abrir `/rxnpwa/presupuestos` en mobile o Chrome desktop.
2. Verificar registro del SW en DevTools → Application.
3. Click "Sincronizar catálogo ahora" → ver fetch a `/api/rxnpwa/catalog/full`.
4. Refresh → badge debe pasar a 🟢 con `synced_at`.
5. Verificar persistencia: cerrar tab, reabrir, badge sigue verde.
6. Disparar Sync Artículos desde el backoffice → próximo `/version` debe
   devolver hash distinto → badge a 🟡.

---

## Decisiones tomadas

1. **Scope solo Presupuestos** (PDS más adelante con el mismo patrón).
2. **Sync completo, no delta-sync** — tamaño esperado 100–5000 ítems.
3. **Auth por cookie de sesión reusada** — decisión Charly P0: priorizar
   compatibilidad y simplicidad sobre granularidad de revocación.
4. **URL prefijo `/rxnpwa/`** (no `/pwa/`) — el rey quiere que el branding RXN
   esté presente en este tipo de cosas.
5. **Invalidación on-write** del hash, no recálculo on-read. Pendiente afinar
   con telemetría real.
6. **Vista mobile dedicada** (`presupuestos_shell.php`) — no responsive del form
   actual. Justifica un layout propio.
7. **IndexedDB sin Dexie** — wrapper vanilla, sin sumar dependencias front.
8. **SW extendido** (no reemplazado) — preservar Web Push intacto.
9. **Íconos placeholder** — fondo #0f172a + texto RXN, regenerables vía script.
   Reemplazar por arte final cuando esté listo.

---

## Pendiente próxima sesión

- 🔲 **Fase 2 (Bloque B)**: `form_mobile.php` — formulario mobile dedicado para
  crear presupuestos offline con `#TMP-<uuid>` mientras no haya conectividad.
  Estimado: 2 sesiones.
- 🔲 **Fase 3 (Bloque C)**: cola de envío + reconciliación al volver online.
  Estimado: 1 sesión.
- 🔲 Reemplazar íconos placeholder por arte final.
- 🔲 Afinar `STALE_HOURS_THRESHOLD` cuando se vea uso real.
- 🔲 Evaluar throttling de invalidación si hay flapping en prod.

---

## Files

### Nuevos
- `app/modules/RxnPwa/RxnPwaController.php`
- `app/modules/RxnPwa/RxnPwaCatalogService.php`
- `app/modules/RxnPwa/RxnPwaCatalogVersionRepository.php`
- `app/modules/RxnPwa/views/presupuestos_shell.php`
- `app/modules/RxnPwa/MODULE_CONTEXT.md`
- `database/migrations/2026_04_30_01_create_rxnpwa_catalog_versions.php`
- `public/manifest.webmanifest`
- `public/icons/rxnpwa-192.png`
- `public/icons/rxnpwa-512.png`
- `public/js/pwa/rxnpwa-catalog-store.js`
- `public/js/pwa/rxnpwa-register.js`
- `tools/generate_rxnpwa_icons.php`

### Modificados
- `public/sw.js` — sumamos sección PWA preservando Web Push.
- `app/config/routes.php` — 3 rutas nuevas.
- `app/config/version.php` — bump 1.30.0 → 1.31.0 + history.
- `app/modules/RxnSync/RxnSyncController.php` — invalidate() x3.
- `app/modules/Tango/Controllers/TangoSyncController.php` — invalidate() x2.
