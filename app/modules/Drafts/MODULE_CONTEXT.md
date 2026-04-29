# MODULE_CONTEXT — Drafts (autoguardado de borradores)

## Propósito

Permite que cualquier formulario largo (PDS, Presupuestos, en el futuro otros) **se autoguarde server-side cada pocos segundos** mientras el operador tipea. Si la sesión muere, el browser se cierra, hay un corte de luz, o el operador navega afuera sin querer, al volver al form se ofrece **retomar el borrador** desde el punto exacto donde lo dejó.

Complementa al esquema de **Return-URL post-login** (en `AuthService::requireLogin` + `AuthController`): el return-URL recupera el lugar; el draft recupera la data.

## Diseño

### Capa de datos
- Tabla **`drafts`** (creada en `database/migrations/2026_04_29_00_create_drafts_table.php`).
- Schema:
  - `(user_id, empresa_id, modulo, ref_key)` con UNIQUE — un único draft por usuario+empresa+form+registro.
  - `payload_json` LONGTEXT con el snapshot completo del formulario.
  - `created_at` / `updated_at` para mostrar antigüedad y ordenar.
- **Aislamiento multi-tenant**: el filtro siempre es por `user_id + empresa_id`. Un usuario nunca ve drafts de otra empresa, ni siquiera los suyos si cambió de empresa.
- **Concurrencia**: `last-write-wins`. Si abrís el mismo PDS en 2 tabs, gana el último guardado. No vale la pena complicarla con merge de campos.

### Capa de aplicación
- **`DraftsRepository`** — `find / upsert / delete / findAllByUser`. Solo CRUD, sin lógica de negocio.
- **`DraftsController`** — endpoints REST + panel HTML:
  - `GET /api/internal/drafts/get?modulo=X&ref=Y` — devuelve `{ok, draft: {modulo, ref, payload, updated_at}}` o `{ok, draft: null}`.
  - `POST /api/internal/drafts/save` — upsert. Body form-urlencoded con `csrf_token`, `modulo`, `ref`, `payload` (string JSON).
  - `POST /api/internal/drafts/discard` — delete. Body form-urlencoded con `csrf_token`, `modulo`, `ref`.
  - `GET /mi-perfil/borradores` — vista HTML "Mis borradores" en Mi Perfil.
- **Whitelist de módulos**: `ALLOWED_MODULOS = ['pds', 'presupuesto']`. Sumar uno nuevo es **una sola línea** + agregar el módulo a `moduloLabel/moduloIcon/resumeUrl` para que aparezca lindo en el panel.
- **Validación de `ref`**: regex `/^[A-Za-z0-9_\-]+$/`, max 64 chars. `'new'` para creación, ID numérico para edición.
- **Cap de payload**: 1 MB (`MAX_PAYLOAD_BYTES`). Si alguien tira más, el controller responde 400 — pista de que el form está mal diseñado o tiene blobs que no deberían estar.

### Capa front
- **`public/js/rxn-draft-autosave.js`** — cargado global en `admin_layout.php`. Auto-init al `DOMContentLoaded`:
  - Busca `<form data-rxn-draft="modulo:ref">`.
  - Hace `GET /api/internal/drafts/get` al cargar — si hay draft, muestra banner "Tenés un borrador del [fecha], ¿lo retomamos?" con botones [Retomar] / [Descartar].
  - Listeners `input` y `change` con **debounce 5s** → `POST /api/internal/drafts/save` con todos los campos serializados a JSON.
  - Al `submit` del form → `POST /api/internal/drafts/discard` via `navigator.sendBeacon` (sobrevive a la navegación post-submit).
- **Campos excluidos del serializado**: `<input type="file">` (no se puede serializar binario en JSON razonable) y `<input type="password">` (seguridad). Todo el resto entra.
- **Restauración compatible con Flatpickr**: si `window.RxnDateTime.setValue` está disponible, lo usa para `datetime-local` (sino el wrapper Flatpickr no se entera y queda visualmente desincronizado). Para el resto dispara `input` y `change` para que pickers/calculadores enganchados se enteren del cambio.

### Cómo enchufar un formulario nuevo
1. Sumar el módulo a `DraftsController::ALLOWED_MODULOS` y a los 3 helpers (`moduloLabel`, `moduloIcon`, `resumeUrl`).
2. En la view del form, agregar al `<form>` el atributo `data-rxn-draft="<modulo>:<ref>"`.
   - `ref` = `'new'` en formularios de creación, o el ID del registro al editar.
3. Listo. No hace falta tocar JS ni backend.

## Relación con otros módulos

- **Auth**: `AuthService::requireLogin()` redirige con `?next=<url-actual>` (whitelist `isSafeNext`). Combinado con drafts: si la sesión muere mientras tipeás, login te lleva de vuelta al mismo PDS, y al cargar el form se ofrece el draft. **El usuario no pierde ni el lugar ni la data**.
- **Auth `/api/internal/session/heartbeat`**: el JS `rxn-session-keeper.js` pollea cada 60s, avisa con banner amarillo a 15min de la expiración idle y ofrece "Extender ahora". Reduce la frecuencia con que la sesión muere realmente. Drafts es la red de seguridad cuando el aviso falla (browser cerrado, tab dormida, etc.).
- **CrmPedidosServicio**: primer cliente. `app/modules/CrmPedidosServicio/views/form.php` tiene `data-rxn-draft="pds:<id-o-new>"`.
- **CrmPresupuestos**: pendiente — sumar `data-rxn-draft="presupuesto:<id-o-new>"` al `<form>` cuando se entre a iterar presupuestos.

## Panel "Mis borradores" (`/mi-perfil/borradores`)

- Listado server-rendered de todos los drafts del usuario en la empresa actual, ordenados por `updated_at` DESC.
- Cada fila: módulo + label / referencia (badge "Nuevo" o `#id`) / fecha última edición / tamaño / botones Retomar + Descartar.
- **Retomar** = link al form correspondiente (resuelto por `DraftsController::resumeUrl`). El JS de autosave detecta el draft existente y muestra el banner inline.
- **Descartar** = `fetch` a `/api/internal/drafts/discard` con confirmación.
- Acceso desde el botón "Mis borradores" en el header de Mi Perfil B2B.

## Convenciones de seguridad (checklist `docs/seguridad/convenciones.md`)

- **Aislamiento multi-tenant**: ✅ todos los endpoints filtran por `user_id + empresa_id` desde sesión, nunca desde request.
- **CSRF**: ✅ `save` y `discard` usan `verifyCsrfOrAbort()`. `get` es read-only sin side effects, no necesita.
- **IDOR**: ✅ no se acepta `user_id` ni `empresa_id` por request — solo desde sesión.
- **Open redirect**: N/A — el panel no redirige.
- **XSS**: ✅ la vista escapa todo con `htmlspecialchars`.
- **Validación**: ✅ `modulo` whitelisted, `ref` regex, `payload` cap 1MB + parse JSON.
- **Rate limiting**: pendiente. Por ahora un usuario malicioso podría llenar la tabla con drafts ficticios — el cap de 1MB y el UNIQUE por `(user, empresa, modulo, ref)` limitan el daño práctico (refs válidas son finitas). Si en el futuro vemos abuso, agregar `RateLimiter` por `user_id` en `save`.
- **Logs**: ningún PII se loguea por defecto. El payload solo vive en DB.

## Ciclo de vida del borrador

1. **Creación**: primer cambio en el form dispara `save` después de 5s.
2. **Vigencia**: se actualiza con cada cambio (debounce 5s, no más de un POST cada 5s).
3. **Recuperación**: al recargar el form, banner inline.
4. **Cierre por submit OK**: el JS dispara `discard` en el `submit` del form. Si el server rechaza el guardado real (validación), el draft queda descartado prematuramente — se prefirió eso a que quede un draft viejo conviviendo con el form actual.
5. **Cierre manual**: botón "Descartar" en el banner inline o en el panel.
6. **Sin TTL automático**: los drafts no expiran solos. Ver sección "Operaciones / Performance / GC" más abajo para el plan de garbage collection.

## Operaciones / Performance / GC

### Carga estimada
- **Por operador con 1 form abierto y tipeando activamente**: ~12 saves/min (debounce 5s) + 1 heartbeat/min = **~13 req/min**.
- **Por tab idle (sin tipear)**: solo 1 heartbeat/min — los saves no se disparan sin `input`/`change`.
- **Cada save**: 1 `INSERT ... ON DUPLICATE KEY UPDATE` sobre `drafts` (UNIQUE index, O(log n)). Payload típico 5–30 KB, cap 1 MB.
- **Cada heartbeat**: cero queries propias. Solo lee `$_SESSION` en memoria y devuelve JSON. El UPDATE de `usuarios.ultimo_acceso` que dispara `App.php` ya existía con throttle de 60s — el session-keeper NO sumó queries, monta sobre la presencia que ya estaba.

**Escenario 20 operadores concurrentes, 2 tabs cada uno, 1 tipeando activamente:**
- Saves: 240/min (~4/s).
- Heartbeats: 40/min (~0.7/s).
- Total: ~5 req/s. Despreciable comparado con el tráfico normal del CRM (búsquedas, pickers, Tango sync, mail masivos).

### Aislamiento multi-tenant
Cada save filtra por `(user_id, empresa_id)` desde sesión, nunca desde request. Un usuario que cambia de empresa NO ve los drafts de otra empresa. Los UNIQUE keys los aislan a nivel DB. La whitelist de módulos + regex de `ref` limitan el espacio total a unas pocas filas por usuario por empresa.

### Crecimiento de la tabla
Sin TTL automático, los drafts abandonados (no submiteados ni descartados manualmente) quedan para siempre. Acumulación estimada: ~50 operadores × ~1 draft fantasma/semana × 52 semanas = ~2600 filas/año. **No es crítico para performance** (la tabla escala fina con índices), pero es deuda técnica de higiene.

### Plan de GC (pendiente — agregar cuando se vea acumulación)
Cuando se decida sumarlo:
1. Endpoint nuevo `POST /api/internal/drafts/gc` con auth por `X-RXN-Token` (mismo patrón que `/api/internal/notifications/tick`).
2. Lógica: `DELETE FROM drafts WHERE updated_at < NOW() - INTERVAL 30 DAY`.
3. Sumar un node al workflow tick global de n8n (frecuencia diaria a las 03:00, no necesita más).

No es bloqueante. Charly va a estar atento al crecimiento y lo accionamos cuando lo veamos amerite.

### Mitigaciones opcionales (si se ve abuso)
- **Rate limiting en `save`**: `RateLimiter::allow($key, 60, 60)` (60 saves/min por user). Permite el flujo normal de 12/min con mucho headroom y corta cualquier abuso. No agregado por ahora — el cap de 1MB + UNIQUE keys ya limitan el daño práctico.
- **Backoff de saves cuando la red está flaky**: condicionar el siguiente save a que el anterior haya respondido. No urgente — el `fetch` actual es fire-and-forget y para volúmenes normales no genera problema.
