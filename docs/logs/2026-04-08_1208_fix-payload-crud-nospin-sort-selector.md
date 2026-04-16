# 2026-04-08 1208 — Fix: Payload CRUD, sin spin, sort persistente, selector empresa

## Cambios realizados

### 1. Endpoint `GET /rxn-sync/payload` (nuevo)
- **Controller**: `RxnSyncController::getPayload()` — lee `rxn_sync_status` local, retorna `meta` (estado, tango_id, fecha, error) + `snapshot` (JSON completo de la respuesta Tango)
- **Rutas**: registradas en `routes.php` para Tiendas (`/mi-empresa/rxn-sync/payload`) y CRM (`/mi-empresa/crm/rxn-sync/payload`)

### 2. Botón "i" en CRUD Artículos y Clientes
- Nuevo `<button class="btn-payload-info">` con `bi bi-info-circle` en cada fila
- Al hacer click llama al endpoint `/payload` y muestra el snapshot en modal con panel negro/verde expandible
- Mismo `payloadHtml()` helper para Push, Pull e Info

### 3. Sin animación de spin (estándar Clientes → Artículos)
- Eliminados: `spin-icon`, `@keyframes rxn-spin`, animación inline `icon.style.animation`
- Push y Pull solo usan `btn.disabled = true/false`

### 4. Payload artículo minimalista en push
- Eliminados del payload: `PERFIL_ARTICULO`, `ID_STA22`, `COD_BARRA`, `OBSERVACIONES`
- Solo se envía: `ID_STA11`, `COD_STA11`, `DESCRIPCIO` (60 chars max)
- Motivo: esos campos son frecuentemente read-only o tienen restricciones distintas según el perfil de Tango

### 5. Persistencia de sort en RxnSync
- `loadSortState(key)` / `saveSortState(key, ps)` usa `localStorage`
- Clave: `rxnsync_sort_{clientes|articulos}` y `rxnsync_dir_{clientes|articulos}`
- Al recargar el tab, restaura icono ▲/▼ y aplica el sort antes del primer render

### 6. Fix selector empresa en Configuración
- Comparación cambiada de `==` (coercion JS) a `String(x) === String(y)` para evitar falsos negativos entre string y number
- `_syncSearch()` ahora se llama con `setTimeout(0)` para esperar que el wrapper del input esté en el DOM
- Texto de fallback mejorado: `(ID: 351 — sin descripción en catálogo)` en vez del mensaje confuso anterior

## Impacto
- ✅ Push/Pull artículos CRUD Tiendas y CRM: desbloqueado con payload mínimo
- ✅ Push/Pull clientes: sin spin, con payload visible
- ✅ Botón "i" en ambos CRUDs
- ✅ Sort RxnSync persiste al navegar entre menús
- ✅ Selector empresa muestra correctamente el valor guardado
