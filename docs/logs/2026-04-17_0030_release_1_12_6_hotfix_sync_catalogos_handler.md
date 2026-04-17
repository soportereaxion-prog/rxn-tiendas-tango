# Release 1.12.6 — Hotfix handler de "Sync Catálogos" en RxnSync

**Fecha y tema**: 2026-04-17 00:30 — Hotfix al botón de Sync Catálogos introducido en 1.12.5.

## Síntoma

Charly reportó que el botón "Sync Catálogos" en `/mi-empresa/crm/rxn-sync` "aparece pero no hace nada": click, se abre el confirm modal, acepta "Sincronizar Catálogos", y la pantalla no cambia visiblemente.

## Análisis

Dos causas superpuestas, ambas atacadas:

### 1. URL hardcoded en el fetch
El handler original usaba `fetch('/mi-empresa/crm/rxn-sync/sync-catalogos', ...)` con path absoluto hardcoded. El resto de los handlers del mismo archivo (runSyncFullTab, runOnlyAuditTab, etc.) usan `basePath + '/' + endpoint` donde `basePath` viene del `data-base-path` del `#syncTabs`. Si por alguna razón el server local requiere un prefijo distinto (ej: multi-tenant con prefijo de slug, rewriting custom de `.htaccess`, etc.), mi URL hardcoded puede 404ear mientras las otras funcionan.

### 2. Reload silencioso sin tiempo de lectura
Si el sync corre correctamente pero las precondiciones del circuito YA estaban cumplidas (caso del tenant crm-y-tiendas donde el auto-trigger defensivo de `PresupuestoController::loadCatalogData()` ya había poblado el catálogo), el `window.location.reload()` dejaba la pantalla visualmente idéntica. El alert de éxito aparecía pero desaparecía con el reload antes de que el operador lo leyera → sensación de "no pasó nada".

## Qué se hizo

- `app/modules/RxnSync/views/index.php` — handler de `#btn-sync-catalogos` reescrito:

1. **URL derivada de basePath** en lugar de hardcoded:
   ```js
   fetch(basePath + '/sync-catalogos', { ... })
   ```

2. **try/catch sincrónico** que envuelve todo el callback del `showConfirm`. Si hay excepción JS antes del fetch, se atrapa y se reporta con `showAlert` — no muere silencioso.

3. **Parseo de respuesta en dos steps** — primer `.then` lee `r.text()` + status HTTP; segundo `.then` intenta `JSON.parse`. Si falla, muestra alert con HTTP status + primeros 200 chars del body (más diagnóstico que la promise rejection silenciosa de `r.json()`).

4. **console.log en cada paso**:
   - `[SyncCatalogos] click detected, opening confirm`
   - `[SyncCatalogos] confirm OK, disparando fetch a {basePath}/sync-catalogos`
   - `[SyncCatalogos] response recibida, status={N}`
   - `[SyncCatalogos] success` + stats
   - `[SyncCatalogos] server reportó fracaso` + data
   - `[SyncCatalogos] fetch rejected` + err
   - `[SyncCatalogos] excepción sincrónica en el handler` + err

5. **setTimeout 1500ms** antes del `window.location.reload()` para que el operador lea el alert de éxito con los stats por tipo.

## Por qué

Aplica la regla defensiva del proyecto: **"Diagnóstico persistente > DevTools"**. Si mañana el handler vuelve a fallar por cualquier causa (cambio de server, cambio de routing, excepción en otro framework del stack), los `console.log` quedan en consola sin necesidad de agregar debug ad-hoc. Y los alerts explican al operador qué pasó en lugar de quedar silencioso.

## Impacto

- El botón "Sync Catálogos" ahora funciona con el mismo mecanismo que el resto de botones del módulo (basePath-derived URL).
- Si falla, el operador ve un alert explicando por qué (HTTP status, parsing error, network error, excepción sincrónica).
- Si tiene éxito, el alert queda 1.5s antes del reload → el operador ve los stats.
- No se tocó backend ni rutas. Scope contenido a 1 archivo JS embebido.

## Validación

- Pendiente que Charly pruebe con Ctrl+Shift+R y abra DevTools. Los `console.log` deberían aparecer en orden, y cualquier error queda visible en la consola + alert.

## Pendiente

- No aplica. Iteración cerrada como hotfix puntual.
