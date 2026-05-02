# Módulo RxnPwa — PWA mobile (multi-app)

> **Iteración 45 — PWA Horas + Hub launcher.** Sumamos la **PWA de Horas** (turnero offline) como segunda app mobile, después de Presupuestos. Charly creó el hub `/rxnpwa` (launcher) que centraliza el acceso a todas las PWAs disponibles. Manifest `start_url` apunta al launcher. Release 1.43.0 (hub) + 1.43.1 (Horas e2e). El módulo ahora es **multi-app**, no solo Presupuestos.
>
> **Iteraciones 42 y 43 (releases 1.31.0 → 1.38.0)**: PWA Presupuestos completa — andamiaje + catálogo offline + form mobile + sync queue + envío a Tango + defaults comerciales del cliente + gate GPS bloqueante + acceso desde dashboard CRM (12 bumps).
>
> **Diferencial competitivo clave**: la PWA OBLIGA al operador a tener GPS activo. La trazabilidad geográfica de cada presupuesto / turno emitido en campo es parte central del producto, no opcional. Cualquier feature futura debe respetar este gate.

## Apps disponibles

| App | URL | Tabla server | Tango | Adjuntos |
|-----|-----|--------------|-------|----------|
| **Presupuestos** | `/rxnpwa/presupuestos` | `crm_presupuestos` | ✅ envío | ✅ |
| **Horas (turnero)** | `/rxnpwa/horas` | `crm_horas` | ❌ no aplica | ✅ |

El **launcher** `/rxnpwa` lista todas las apps disponibles como cards. Para sumar una app nueva: editar `$pwaApps` en `app/modules/RxnPwa/views/launcher.php` + agregar las rutas + el shell + el form.

## PWA Horas (release 1.43.0 + 1.43.1)

Replica del desktop turnero (`/mi-empresa/crm/horas`) en mobile-first. Diferencias clave vs Presupuestos:

- **SIN Tango**: las horas no se envían a Tango. Solo sincronizar offline → server.
- **SIN renglones**: 1 turno = 1 fichaje puntual.
- **Cronómetro vivo**: total trabajado del día actualiza cada 1s, sumando turnos cerrados + el cronómetro abierto.
- **Botón único contextual**: "Iniciar turno" o "Cerrar turno" según haya draft abierto.
- **Concepto como textarea** (paridad web/PWA — release 1.43.1).
- **Descuento HH:MM:SS + motivo textarea** opcionales (si descuento > 0, motivo obligatorio).
- **Adjuntos**: cámara `capture=environment` para certificados médicos / planillas. Compresión client-side de imágenes.

### Endpoints (Horas)

| Método | Ruta | Devuelve |
|--------|------|----------|
| GET | `/rxnpwa/horas` | Shell mobile con total + botón Iniciar/Cerrar + lista del día |
| GET | `/rxnpwa/horas/nuevo` | Form de turno diferido vacío |
| GET | `/rxnpwa/horas/editar/{tmpUuid}` | Form cargando draft de IndexedDB |
| POST | `/api/rxnpwa/horas/sync` | `{ok, id_server, tmp_uuid, created}`. Idempotente por tmp_uuid_pwa. |
| POST | `/api/rxnpwa/horas/{id}/attachments` | `{ok, attachment}`. Multipart con campo `file`. |

### Server-side (Horas)

- **`RxnPwaHorasSyncService::syncDraft`**: mapea draft IndexedDB → `HoraService::cargarDiferido` con idempotencia por `tmp_uuid_pwa`. Doble check anti race condition (doble tap del usuario).
- **`HoraRepository::findByTmpUuidPwa`**: helper de idempotencia.
- **`HoraService` extendido**: `iniciar`, `cargarDiferido`, `editar` aceptan `descuento_segundos` + `motivo_descuento`. Validación cruzada server-side.

### Storage cliente (IndexedDB v4)

```
rxnpwa (DB v4)
├── ... (10 stores catálogo + tratativas_activas)
├── presupuestos_drafts (Presupuestos PWA)
├── presupuesto_attachments (Presupuestos PWA)
├── horas_drafts                 keyPath: tmp_uuid
│   { tmp_uuid, empresa_id, created_at, updated_at,
│     cabecera: { fecha_inicio, fecha_finalizado, concepto,
│                 tratativa_id, tratativa_data,
│                 descuento_segundos, motivo_descuento },
│     status: 'draft' | 'pending_sync' | 'syncing' | 'synced' | 'error',
│     server_id, retry_count, last_error, next_retry_at, geo }
└── horas_attachments            keyPath: id (autoIncrement), index: by_tmp_uuid
    { id, tmp_uuid, name, mime, size, blob, compressed, created_at,
      sync_status: 'pending' | 'uploaded' | 'failed', server_attachment_id }
```

### Catálogo offline extendido

- **`tratativas_activas`** sumado al payload (`fetchTratativasActivas`): top 100 estados nueva/en_curso/pausada para alimentar el selector "Vincular a tratativa" del turnero PWA. CATALOG_SCHEMA_VERSION → `v3`.

### CRÍTICO — `HoraService::cargarDiferido` con `tmp_uuid_pwa`

La firma de `HoraService::cargarDiferido` ahora acepta 2 params nuevos al final: `descuentoSegundos`, `motivoDescuento`, `tmpUuidPwa`. La PWA pasa los 3. El form web desktop solo pasa los 2 primeros (sin tmp_uuid_pwa) — el server lo deja como NULL. Si modificás la firma, validá que ambos paths sigan funcionando.

### CRÍTICO — IndexedDB defensiva

`loadAll`, `saveCatalog`, `clearCatalogOnly`, `clear` filtran stores que NO existen en la DB del cliente (con `db.objectStoreNames.contains(name)`). Esto evita que un upgrade pendiente o un cliente desactualizado crashee el boot con `NotFoundError`. **Si sumás una store nueva, bumpeá `DB_VERSION` SIEMPRE** — sin bump el `onupgradeneeded` no corre y los browsers existentes quedan en la versión vieja sin la store nueva.

## PWA Presupuestos (sin cambios estructurales esta iteración)

## Propósito

Habilitar a los **vendedores en campo** a operar Presupuestos desde un dispositivo móvil con conectividad intermitente. La PWA se instala como app nativa, descarga el catálogo completo de la empresa al dispositivo, y permite (en fases siguientes) crear presupuestos offline que sincronizan al volver online.

**Decisión central**: scope SOLO Presupuestos (PDS más adelante, mismo patrón). El catálogo se baja entero (no delta-sync) porque el tamaño esperado es 100–5000 ítems por empresa (10k excepcional).

## Roadmap (3 fases)

| Fase | Bloque | Estado | Alcance |
|------|--------|--------|---------|
| **1** | A | ✅ Implementada | Andamiaje + manifest + SW + catálogo offline-readable + shell + versionado por hash |
| **2** | B | ✅ Implementada | Form mobile completo + creación offline con `TMP-<uuid>` + adjuntos con cámara + auto-save |
| **3** | C | ✅ Implementada | Sync queue 2-step (header → adjuntos) + retry con backoff + envío a Tango desde mobile |

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
| GET | `/rxnpwa/presupuestos` | Login + CRM access | Shell HTML (registra SW + UI estado catálogo + listado de drafts) |
| GET | `/rxnpwa/presupuestos/nuevo` | Login + CRM access | Form mobile vacío. JS crea draft local al primer cambio. |
| GET | `/rxnpwa/presupuestos/editar/{tmpUuid}` | Login + CRM access | Form mobile cargando un draft existente desde IndexedDB. |
| GET | `/api/rxnpwa/catalog/version` | Login + CRM access | `{ok, hash, generated_at, items_count, size_bytes}` |
| GET | `/api/rxnpwa/catalog/full` | Login + CRM access | `{ok, hash, generated_at, ..., data: {clientes, articulos, ...}}` + header `X-Rxnpwa-Catalog-Hash` |
| POST | `/api/rxnpwa/presupuestos/sync` | Login + CRM access | `{ok, id_server, numero, tmp_uuid, created}`. Idempotente por tmp_uuid_pwa. |
| POST | `/api/rxnpwa/presupuestos/{id}/attachments` | Login + CRM access | `{ok, attachment:{id,original_name,size_bytes,mime}}`. Multipart con campo `file`. |
| POST | `/api/rxnpwa/presupuestos/{id}/emit-tango` | Login + CRM access | `{ok, type, message}` — reusa `PresupuestoTangoService::send()`. |

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

- ✅ **Aislamiento multi-tenant**: todas las queries filtran por `Context::getEmpresaId()`. `tmp_uuid_pwa` es UNIQUE global, pero el sync siempre cruza con `empresa_id` antes de devolver el id existente. El upload de adjuntos verifica que el presupuesto pertenezca a la empresa del usuario antes de aceptar el archivo.
- ✅ **Auth**: `requireLogin()` + `requireCrmAccess()` en TODOS los endpoints (incluyendo POST de Fase 3). Sin sesión → 401/redirect; sin acceso CRM → 403.
- ⚠️ **CSRF**: los POST de Fase 3 (`/sync`, `/attachments`, `/emit-tango`) hoy NO validan CSRF token explícito. El cookie de sesión `SameSite=Lax` (default Laravel-style) cubre el caso típico de cross-site, pero conviene sumar el meta `csrf-token` del shell al header de cada fetch para hardening. **Pendiente** post-Fase 3.
- ✅ **Uploads**: delegado a `AttachmentService::attach()` que valida MIME por `finfo` contra whitelist + blacklist por extensión + tope de tamaño y cantidad por owner. El archivo se persiste fuera del docroot navegable directo (carpeta con `.htaccess Require all denied`).
- ⚠️ **Rate limiting**: hoy sin rate-limit en `/sync` y `/attachments`. Un cliente malicioso autenticado podría flood-ear creando drafts. **Pendiente**: throttling razonable (ej: 60 syncs/min/usuario).
- ✅ **IDOR**: el `empresa_id` viene de `Context`, nunca del cliente. El controller verifica que el `presupuesto_id` del path pertenezca a la empresa antes de aceptar el upload o el emit-tango.
- ✅ **XSS**: las views escapan con `htmlspecialchars`. El JS escapa explícitamente con `escapeHtml` antes de inyectar HTML del listado de drafts.
- ✅ **Tokens**: no se emiten tokens propios. La idempotencia es por `tmp_uuid_pwa` que el cliente genera con `crypto.randomUUID` — no permite predicción cross-cliente porque la unicidad es global y empresa_id se valida server-side.

## Fase 2 (Bloque B) — Form mobile + creación offline

**Vista**: `views/presupuesto_form.php` mobile-first (no admin_layout). Cabecera + renglones + comentarios/observaciones + adjuntos + sección "Enviar al servidor".

**Storage cliente** — IndexedDB v2 suma 2 stores nuevas:

```
rxnpwa (DB v2)
├── ... (10 stores de catálogo de Fase 1)
├── presupuestos_drafts        keyPath: tmp_uuid
│   { tmp_uuid, empresa_id, created_at, updated_at,
│     cabecera: {...}, renglones: [...], total,
│     status: 'draft' | 'pending_sync' | 'syncing' | 'synced' | 'emitted' | 'error',
│     server_id: int|null, numero_server: int|null,
│     retry_count, last_error, next_retry_at, tango_message }
└── presupuesto_attachments    keyPath: id (autoIncrement), index: by_tmp_uuid
    { id, tmp_uuid, name, mime, size, blob, compressed, created_at,
      sync_status: 'pending' | 'uploaded' | 'failed', server_attachment_id }
```

**UUID local**: `TMP-<crypto.randomUUID>` generado client-side al primer save. Ese mismo UUID viaja al server en el sync (Fase 3) y se persiste en `crm_presupuestos.tmp_uuid_pwa` (UNIQUE) — eso da idempotencia ante retries.

**Adjuntos**:
- Cámara directa: `<input type=file accept=image/* capture=environment>`.
- Selector multi-archivo: PDF / Word / Excel / fotos.
- Compresión cliente para imágenes: max 1600px + canvas `toBlob` quality 0.80. Se preservan PNG con transparencia (sample 5 puntos del canvas) y se mantiene formato original si ya está optimizado.
- Auto-save: debounce 1.5s. Botón "Guardar" manual también disponible (Alt+S aún no, pendiente).

## Fase 3 (Bloque C) — Sync queue + envío a Tango

### Flujo 2-step

1. **Header + items**: `POST /api/rxnpwa/presupuestos/sync` con `{tmp_uuid, cabecera, renglones}`. Server resuelve los catálogos (lista, depósito, clasificación) por `codigo` reusando `CommercialCatalogRepository`, calcula totales, llama `PresupuestoRepository::create()` y devuelve `{id_server, numero, created}`. Idempotente por `tmp_uuid_pwa` UNIQUE — si llega 2 veces el mismo UUID, devuelve el id existente.
2. **Adjuntos**: por cada attachment con `sync_status='pending'`, `POST /api/rxnpwa/presupuestos/{id}/attachments` (multipart, campo `file`). Backend reusa `AttachmentService::attach($empresaId, 'crm_presupuesto', $id, $file, $userId)` — el `owner_type='crm_presupuesto'` ya está whitelisteado en `app/config/attachments.php`.

### Estados del draft

| Estado | Cuándo |
|--------|--------|
| `draft` | Recién creado offline. NO se sincroniza solo. |
| `pending_sync` | El usuario apretó "Sincronizar" y está en cola. |
| `syncing` | El runner de cola está procesándolo. |
| `synced` | Header + items + adjuntos subidos al server. `server_id` poblado. |
| `emitted` | Además del sync, el usuario tocó "Enviar a Tango" online y volvió OK. |
| `error` | Agotó los reintentos (5). Necesita acción manual del usuario. |

### Retry / backoff

- Máximo **5 reintentos automáticos** por draft.
- Backoff exponencial: 1s, 2s, 4s, 8s, 16s.
- Tras agotarlos → `status='error'` permanente. UI muestra botón "Reintentar" que vuelve a encolarlo desde 0.

### Auto-arranque de la cola

Triggers que disparan `RxnPwaSyncQueue.kick()`:
1. `DOMContentLoaded` del shell o del form (drenar al abrir).
2. `online` event en `window` (al recuperar red).
3. **Background Sync API** via SW: `event.tag === 'rxnpwa-sync-queue'` → SW `postMessage({type: 'rxnpwa-sync-queue-fire'})` → cliente lo intercepta y dispara `kick()`. Funciona en Chrome/Edge desktop y Android. iOS Safari NO lo soporta — pero el `online` event lo cubre.

### Envío a Tango desde mobile

Botón "Enviar a Tango" en el form mobile y en cada card del shell. Visible siempre, **deshabilitado si offline o si el draft no está `synced`**. Al clickear → `POST /api/rxnpwa/presupuestos/{id}/emit-tango` que internamente llama `PresupuestoTangoService::send()` (mismo path que el form web).

**Política**: el draft tiene que estar sincronizado al server PRIMERO (sin id server no hay Tango). El usuario decide manualmente cuándo emitir — NO se hace automático tras el sync. Tras emisión OK, el draft pasa a `status='emitted'`.

## Defaults comerciales del cliente (release 1.35.0+)

Cuando el operador selecciona un cliente en el form mobile, se autocompletan **lista de precios, condición de venta, vendedor y transporte** desde los códigos configurados en la fila del cliente offline. Replica el comportamiento de `clientContext` del form web (`PresupuestoController::clientContext`).

**Cadena de fallback por campo** (mismo orden que el web):

| Campo PWA | Fuente primaria | Fallback |
|-----------|-----------------|----------|
| `lista_codigo`     | `id_gva10_lista_precios`     | `id_gva10_tango` |
| `condicion_codigo` | `id_gva01_condicion_venta`   | `id_gva23_tango` |
| `vendedor_codigo`  | `id_gva23_vendedor`          | `id_gva01_tango` |
| `transporte_codigo`| `id_gva24_transporte`        | `id_gva24_tango` |

Para que esto funcione, `RxnPwaCatalogService::fetchClientes` baja esos 8 campos al cliente. El JS (`applyClienteDefaults` en `rxnpwa-form.js`) sólo escribe si el campo del draft está VACÍO — no pisa elecciones manuales del operador.

**Si el código del cliente no existe en el catálogo offline** (cliente desincronizado): se conserva la selección con etiqueta "(no encontrado en catálogo)" para no perder la data, y el server intenta resolverlo en el sync.

## CRÍTICO — `id_interno` vs `id` en catálogo comercial

El campo `id_interno` de `crm_catalogo_comercial_items` es lo que mapea contra **ID_GVA01/10/23/24** de Tango. **NUNCA** usar `$row['id']` (PK auto-increment local) como id_interno — Tango rechaza con "No existe condición de venta para el ID_GVA01 ingresado: <N>".

El bug existió en la release 1.35.0 dentro de `RxnPwaSyncService::resolveCatalogItem`. Fix en 1.35.2. **Al replicar este patrón para PDS u otro módulo PWA, leer `$row['id_interno']`.**

## CRÍTICO — `tmp_uuid_pwa` UNIQUE: respetar el unset al copiar/versionar

Tabla `crm_presupuestos` tiene `tmp_uuid_pwa VARCHAR(50) NULL` con UNIQUE KEY. Es el ID idempotencia del draft mobile origen. **Cualquier flujo que cree un presupuesto basado en otro existente DEBE hacer `unset($data['tmp_uuid_pwa'])`** antes del INSERT — sino choca con el UNIQUE y falla.

Casos cubiertos hoy:
- `PresupuestoController::copy()` — unset explícito.
- `PresupuestoRepository::createNewVersion()` — arma `$data` manualmente sin incluir el campo (OK accidentalmente, pero documentar).

Si en el futuro se agrega otro flujo (ej: "duplicar última versión", import de Excel), respetar la regla.

## Schema versioning del catálogo offline

`rxnpwa-catalog-store.js` define `CATALOG_SCHEMA_VERSION` (hoy `'v2'`). Cada `saveCatalog` persiste ese valor en la meta de IndexedDB. Cuando el shell o el form cargan, comparan el valor cacheado contra el actual; si difieren, llaman a `clearCatalogOnly()` (limpia stores del catálogo + meta, NO toca drafts/attachments) y obligan a resincronizar.

**Bumpear `CATALOG_SCHEMA_VERSION` cuando**:
- `RxnPwaCatalogService` agrega columnas a alguna entidad (caso v1→v2: defaults comerciales del cliente).
- Se renombra un campo en una entidad.
- Cambia la estructura/forma del payload de manera que el JS viejo no pueda interpretarla.

**NO bumpear cuando**:
- Solo cambian valores (más artículos, precios actualizados): el `hash` server-side ya lo cubre.

## Detección de cambio de empresa (release 1.34.0)

`rxnpwa-register.js::ensureCatalogConsistency()` lee `<meta name="rxn-empresa-id">` y compara con `meta.empresa_id` del catálogo offline. Si difieren → wipe + badge específico "Cambiaste de empresa, descargá el nuevo". El form mobile aplica el mismo chequeo y redirige al shell si detecta mismatch.

Es complementario al schema versioning: maneja un caso distinto (mismo schema, distinta empresa).

## Acceso desde el backoffice CRM

`app/modules/Dashboard/views/crm_dashboard.php` suma:

1. **Card "PWA — Presupuestos Mobile"** (icono `bi-phone`, link `/rxnpwa/presupuestos`) en el grid de módulos CRM, visible siempre.

2. **Banner inteligente** que aparece SOLO si `navigator.userAgent` matchea Android/iPhone/iPad/Mobile. Ofrece "Abrir PWA" + botón X que descarta para esa sesión (`sessionStorage.rxn_dismiss_pwa_banner`). NO redirige automático — el usuario decide.

## CRÍTICO — Gate GPS bloqueante (release 1.38.0)

**El GPS es OBLIGATORIO para usar la PWA.** Es un diferencial competitivo central del producto: cada presupuesto emitido en campo se rastrea geográficamente. La trazabilidad no es opcional.

### Implementación

`public/js/pwa/rxnpwa-geo-gate.js` se carga **PRIMERO** (antes que catalog-store, drafts-store, sync-queue, form, register, shell-drafts). Si se cambia el orden de carga en las views, el gate se rompe.

Flujo:
1. Al `DOMContentLoaded`, dispara `getCurrentPosition` con `enableHighAccuracy: true, timeout: 10s`.
2. **Éxito** (`source='gps'/'wifi'`) → guarda en memoria + expone vía `RxnPwaGeoGate.getCurrentGeo()`.
3. **Fallo** (`denied/timeout/error/unsupported`) → renderiza overlay bloqueante a pantalla completa con instrucciones para activar el GPS y botón "Reintentar".
4. Refresh automático cada 5 minutos en background — detecta si el operador desactivó el GPS durante el uso.

### Integración con otros módulos PWA

- `rxnpwa-form.js::captureGeoIfMissing` lee `RxnPwaGeoGate.getCurrentGeo()` y la copia al draft. **No pide permisos por su cuenta** — confía en el gate.
- `rxnpwa-sync-queue.js::emitToTango` valida que `draft.geo_source` sea `'gps'` o `'wifi'` antes de mandar. Si no, fuerza `RxnPwaGeoGate.retry()` y, si sigue inválida, tira excepción.

### Server-side (RxnGeoTracking)

`RxnPwaController::syncPresupuesto` llama a `recordGeoEvent()` post-create:
1. `GeoTrackingService::registrar(EVENT_PRESUPUESTO_CREATED, $presupuestoId, 'presupuesto')` → crea evento con fallback IP.
2. Si el cliente mandó `geo.lat/lng/source` válidos → `reportarPosicionBrowser($eventoId, $lat, $lng, $accuracy, $source)` actualiza con la posición precisa del celu.

El módulo Geo Tracking del backoffice ya muestra estos eventos con accuracy en metros y source para distinguir captura precisa vs degraded.

### Reglas para nuevos módulos PWA (PDS, etc)

Cualquier futuro módulo PWA (PDS, Tratativas, etc) **debe** cargar `rxnpwa-geo-gate.js` PRIMERO en su shell/form, igual que Presupuestos. La trazabilidad geográfica es un requisito transversal del producto PWA, no específico de Presupuestos.

## Files (actualizado tras release 1.38.0)

### Backend

- `app/modules/RxnPwa/RxnPwaController.php` — entry + endpoints + recordGeoEvent.
- `app/modules/RxnPwa/RxnPwaCatalogService.php` — consolida payload + hash. fetchClientes trae defaults comerciales.
- `app/modules/RxnPwa/RxnPwaCatalogVersionRepository.php` — UPSERT + invalidate.
- `app/modules/RxnPwa/RxnPwaSyncService.php` — mapea draft → payload del repo. CRÍTICO: usar id_interno NO id.
- `app/modules/RxnPwa/MODULE_CONTEXT.md` — este archivo.

### DB / Migrations

- `database/migrations/2026_04_30_01_create_rxnpwa_catalog_versions.php` — tabla versiones catálogo.
- `database/migrations/2026_04_30_02_alter_crm_presupuestos_add_tmp_uuid_pwa.php` — columna idempotencia.

### Vistas mobile

- `app/modules/RxnPwa/views/_brand_icon.php` — partial del ícono RXN (img si existe rxnpwa-source.png, sino SVG inline).
- `app/modules/RxnPwa/views/presupuestos_shell.php` — shell con badges + drafts + cola + banner.
- `app/modules/RxnPwa/views/presupuesto_form.php` — form mobile completo con cabecera + renglones + adjuntos + sync + Tango.

### Frontend (`public/js/pwa/`) — orden de carga importante

```
1. rxnpwa-geo-gate.js       ← PRIMERO. Bloquea PWA si no hay GPS.
2. rxnpwa-catalog-store.js  ← Wrapper IndexedDB v2 + CATALOG_SCHEMA_VERSION.
3. rxnpwa-drafts-store.js   ← Drafts + attachments offline.
4. rxnpwa-image-compressor.js (sólo en form)
5. rxnpwa-sync-queue.js     ← Cola de sync con backoff + Background Sync + emit Tango.
6. rxnpwa-register.js       ← Sólo en shell. Registro SW + UI estado catálogo.
7. rxnpwa-form.js           ← Sólo en form. Lógica del form (1800+ líneas). Expone window.RxnPwaForm.
8. rxnpwa-form-sync.js      ← Sólo en form. Wire-up de la sección "Enviar al servidor".
9. rxnpwa-shell-drafts.js   ← Sólo en shell. Listado de drafts + cola.
```

### Assets

- `public/manifest.webmanifest`, `public/sw.js` (Web Push + Background Sync), `public/css/rxnpwa.css`.
- `public/icons/rxnpwa-{192,512}.png` — íconos PWA (placeholder por ahora; reemplazables vía `tools/generate_rxnpwa_icons_from_source.php` cuando se drope `rxnpwa-source.png`).

### Backoffice (acceso al PWA)

- `app/modules/Dashboard/views/crm_dashboard.php` — card "PWA — Presupuestos Mobile" + banner mobile detectivo de UA.

### Hooks de invalidación del catálogo

- `app/modules/RxnSync/RxnSyncController.php::syncPullArticulos`, `syncPullClientes`, `syncCatalogos`.
- `app/modules/Tango/Controllers/TangoSyncController.php::syncPrecios`, `syncStock`.

## Pendiente

- **Íconos finales**: Charly tiene que dejar `public/icons/rxnpwa-source.png` (la estrella RXN) y correr `tools/generate_rxnpwa_icons_from_source.php`. Mientras tanto siguen los placeholder + el SVG inline en el header.
- **HTTPS local o port forwarding USB**: el SW no se registra en LAN plana — sin SW no hay Background Sync ni "Add to home screen" prolijo.
- **Hardening server-side**: sumar CSRF token explícito + rate limiting a los POST de `/api/rxnpwa/*` (hoy cubre `SameSite` cookie + auth, suficiente para v1).
- **Throttling de invalidación del hash** si se observa flapping en prod.
- **Eliminación de drafts ya sincronizados** — hoy quedan en IndexedDB con `status='synced'/'emitted'`. ¿Auto-borrar tras N días? ¿Botón "Limpiar sincronizados"?
- **Updates de drafts ya sincronizados**: el form se puede editar pero no hay flujo "re-sync con cambios" — el draft sólo viaja una vez. Charlar cuando aparezca el caso real.
- **Persistir response del primer envío Tango antes del retry** (heredado de 1.30.0 — no urgente).
- **PDS mobile** y otros módulos PWA — replicar el patrón de Presupuestos. Reusar el geo-gate, catalog-store, sync-queue. Cambiar solo controllers + vistas + lógica específica.

## Releases relevantes

| Release | Tema |
|---------|------|
| 1.31.0  | Fase 1 — andamiaje + catálogo offline |
| 1.32.0  | Fase 2 — form mobile + creación offline + adjuntos comprimidos |
| 1.33.0  | Fase 3 — sync queue + envío a Tango |
| 1.34.0  | Cambio de empresa + acceso desde dashboard CRM + banner mobile |
| 1.35.0  | Defaults comerciales del cliente auto-completados |
| 1.35.1  | Hotfix: tmp_uuid_pwa duplicado en copy + schema versioning catálogo |
| 1.35.2  | Hotfix: id_interno mal mapeado a Tango |
| 1.36.0  | UX: shell compacto + pickers con vistazo + stock visible |
| 1.37.0  | Ícono RXN + título Presupuestos + geolocalización + fix modal |
| 1.37.1  | Hotfix: filemtime warning rompía form mobile |
| 1.38.0  | Gate GPS bloqueante (parte crítica del producto) |
