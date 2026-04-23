# Release 1.20.0 — CRM Notas: rework a split view master-detail

**Fecha**: 2026-04-23
**Build**: 20260423.1
**Iteración**: #30 (pos-1.19.0 UX unificado)

---

## Qué se hizo

Rework completo del listado de **CRM Notas** (`/mi-empresa/crm/notas`). Se pasó de una tabla clásica con ir/volver a cada nota a un **layout split master-detail** al estilo GroupOffice/Explorer:

- **Columna izquierda** (col-lg-4): lista de notas con búsqueda, tabs Activos/Papelera, bulk actions, paginación.
- **Columna derecha** (col-lg-8): detalle de la nota seleccionada en vivo, sin recargar la página.

### Fases implementadas

**Fase 1 — Backend de partials**
- Nuevo endpoint `GET /notas/panel/{id}` → devuelve HTML parcial del detalle (sin `admin_layout`), con `http_response_code(404)` si la nota no existe o está fuera de la empresa.
- Nuevo endpoint `GET /notas/lista` → devuelve HTML parcial de los items de la columna izquierda. Aplica los mismos filtros que `index()` (search, sort, dir, status, tratativa_id, filtros avanzados BD).
- Ambos registrados en `routes.php` **antes** de `/notas/{id}` para evitar que el catch-all se los coma.

**Fase 2 — Nueva vista index.php split**
- Layout con `.notas-split.row.g-3` y dos columnas (aside + section).
- El card de la columna izquierda usa `d-flex flex-column`, header de búsqueda en `<div>` plano (NO `.card-body` — ver bug #1 más abajo), tabs + bulk + `.notas-list-scroll` con `flex:1 1 auto; min-height:260px; max-height: calc(100vh - 260px); overflow-y:auto`.
- Primer render del panel derecho: server-side inline del partial `detail_panel.php` con la `$activeNota` (deep link `?n={id}` > primera del listado).
- `data-attrs` en el `.notas-split` para que el JS scopee persistencia: `empresa-id`, `status`, `active-nota-id`, `explicit-n`, etc.

**Fase 3 — JS controller** (`public/js/crm-notas-split.js`)
- Click en item → `loadPanel(id)` con fetch a `/panel/{id}` + update de URL via `history.replaceState('?n={id}')`.
- Búsqueda en vivo con debounce 250ms → recarga solo la lista, preserva el panel si la nota sigue visible; si no, carga la primera del nuevo resultado.
- Paginación AJAX prev/next.
- Helpers de persistencia: `saveLastActive` / `readLastActive` / `clearLastActive` con key `rxn_crm_notas_active::{empresaId}::{status}`.
- IIFE `restoreLastActive` al final del init: si no hay `?n=` explícito y el storage tiene una nota distinta a la elegida por el server, intenta cargarla; si falla con 404, limpia storage y cae a la del server.

**Fase 4 — Hotkeys** (registradas en `RxnShortcuts`)
- `↓` / `j` → siguiente nota (alias vim para quien los prefiera, ↓ como canal canónico por coherencia con el resto del sistema).
- `↑` / `k` → nota anterior.
- `Enter` (foco fuera de inputs) → editar la nota activa.
- **Enter desde el input de búsqueda**: flushea el debounce, activa la primera nota del resultado, hace `searchInput.blur()` + `row.focus()` para habilitar navegación con flechas.
- **ArrowDown desde el input de búsqueda**: patrón combobox, baja al primer item sin refrescar.

**Fase 5 — Redirects post-mutación**
- `store()` / `update()` / `copy()` redirigen a `indexPath?n={id}` en vez de `indexPath` pelado. Así al crear/editar/copiar una nota, el split vuelve parado en ella.
- Se respeta la regla previa de `store()`: si la nota tiene `tratativa_id`, vuelve a la tratativa origen (prioridad mayor).

**Fase 6 — Preservado sin tocar**
- Import/Export XLSX.
- Filtros avanzados BD (`handleCrudFilters('crm_notas')`).
- Bulk actions (eliminar-masivo, restore-masivo, force-delete-masivo).
- Filtro por `?tratativa_id=` desde detalle de tratativa.
- Adjuntos polimórficos (`attachments-panel.php` embebido en el partial del detalle).

### Bug #1 encontrado durante validación: `.card-body` flex-grow

**Síntoma** (reportado por Charly con screenshot): al seleccionar notas con mucho contenido a la derecha, aparecía un espacio enorme vacío entre la búsqueda y los tabs/lista en la columna izquierda. Parecía intermitente porque "dependía de qué nota estuviera mirando".

**Root cause**: Bootstrap le aplica `flex: 1 1 auto` a `.card-body` por default. Con un `.card.h-100` en layout split, cuando el row iguala las alturas de ambas columnas (porque la derecha es larga), el `.card-body` de la izquierda **se estira para absorber todo el espacio vertical sobrante** — empujando a los elementos que vengan después en el DOM (tabs, toolbars, lista) hacia el fondo del card con un gap gigante visualmente inexplicable.

**Fix**:
1. Sacar la clase `.card-body` del wrapper de búsqueda: usar un `<div class="p-2 border-bottom ...">` plano.
2. Agregar `d-flex flex-column` explícito al `.card` (por claridad).
3. Hacer que `.notas-list-scroll` tenga `flex: 1 1 auto` + `min-height` + `max-height: calc(100vh - 260px)` → crece para ocupar el espacio disponible hasta el tope del viewport, es él quien absorbe el espacio extra.

**Regla general aplicable a futuros splits**: en cualquier layout master-detail con cards de Bootstrap, el elemento que tiene que crecer para llenar el espacio debe tener `flex: 1 1 auto` (típicamente la zona scrollable). Los headers y toolbars deben ir en `<div>` planos (sin `.card-body`). Nunca confiar en que "el orden del DOM respeta la posición visual" dentro de un `.card` — el card es flex-column y sus hijos compiten por el espacio.

### Bug #2 detectado y tapado (latente)

Al registrar los endpoints nuevos me di cuenta de que las rutas `POST /notas/{id}/restore` y `POST /notas/{id}/force-delete` **individuales** no estaban en `routes.php` — sólo las versiones `-masivo` existían. Sin embargo, la vista vieja (`index.php` pre-1.20.0) tenía forms que hacían POST a esos endpoints para restaurar/eliminar una nota individual desde la papelera. Eran calls al vacío (404 silencioso). Se sumaron las rutas al router para que funcionen desde el detail_panel del split.

---

## Por qué

Charly lo pidió porque el flujo actual de "buscar una nota → entrar → leer → volver a la lista → buscar otra → entrar → leer" cortaba el ritmo de revisión. Quería algo estilo GroupOffice donde la lista queda siempre visible y el detalle cambia en vivo.

**Decisión clave — Opción B para edición**: el botón Editar del panel derecho navega al form existente en pantalla completa, **NO** edita inline. Tradeoff aceptado: se pierde el split al editar, pero el trabajo cae de "reescribir form + duplicar autocompletes de cliente/tratativa/tags" a "un redirect". MVP con 30% del trabajo para 80% del beneficio. Si en el futuro Charly quiere edición inline, es una fase 2 fácilmente sumable.

---

## Impacto

- **UX**: el listado de notas pasa a ser un entorno de revisión fluido. Útil especialmente cuando se importan muchas notas de una vez y hay que recorrerlas.
- **Teclado**: la navegación con flechas es coherente con el resto del sistema. Power users pueden operar el módulo sin tocar el mouse.
- **Persistencia**: al volver al listado desde cualquier lado, la nota que estaban mirando queda seleccionada. Coherente con el comportamiento de filtros del resto de la suite (`rxn-filter-persistence.js`).
- **Deuda tapada**: rutas individuales de restore/force-delete que no funcionaban en la vista vieja ahora sí.

---

## Decisiones tomadas

1. **Split view, no página aparte**: la alternativa era una pantalla nueva "Leer notas" separada del listado. Se descartó porque duplicaba navegación y no tapaba el problema raíz (ir/volver).
2. **Edición en full-page, no inline** (opción B). Documentado en `MODULE_CONTEXT.md` con la fundamentación y el camino para migrar a opción A si se necesita.
3. **localStorage scopeado por `empresaId + status`**: ni más ni menos. No se scopea por `search` ni por `tratativa_id` filter porque la nota activa es un concepto ortogonal a esos filtros (si sale del scope de la búsqueda, el JS cae a la primera del listado filtrado).
4. **HTML parcial en el server, no JSON + templating en cliente**: menos deuda, el escaping PHP ya lo maneja, y reutilizar el partial en el primer render (server-side) es trivial. El costo: transmitir un poco más de bytes.
5. **Hotkeys ↓/↑ canónico, j/k como alias**: coherencia con el resto de los CRUDs. `j`/`k` quedan para nostálgicos de vim.

---

## Validación

- ✅ Split carga correctamente con primera nota seleccionada al entrar al listado.
- ✅ Click en nota → panel se actualiza sin flicker de full-page reload.
- ✅ Búsqueda en vivo filtra la lista sin tocar el panel.
- ✅ Enter en search salta a la primera nota del resultado y el foco baja a la lista.
- ✅ Flechas ↑/↓ y j/k navegan la lista, con `e.preventDefault()` para no hacer scroll del viewport.
- ✅ F5 preserva la nota activa vía `?n={id}` + localStorage.
- ✅ Crear/editar/copiar redirigen con ?n={id}.
- ✅ Volver al listado sin query recupera la última vista del storage.
- ✅ Bug del `.card-body` flex-grow arreglado — ya no hay espacio fantasma entre búsqueda y tabs.
- ⏳ Validación en prod post-deploy.

---

## Pendiente

- **Fase 2 (futuro, si Charly lo pide)**: edición inline en el panel derecho. Requiere extraer el form a un partial que no envuelva `admin_layout`, y duplicar los autocompletes de cliente/tratativa/tags con init on-demand. No está en el backlog inmediato — hoy la opción B cubre el caso de uso.
- **Patrón replicable**: si en Presupuestos / Tratativas / Llamadas / Agenda se quiere el mismo split, se puede tomar el trio `index.php` + `detail_panel.php` + `list_items.php` + `crm-notas-split.js` como molde.
- **Próxima iteración** (Charly dixit): ajustes en Artículos y Clientes antes de ir a Presupuestos.

---

## Relevant files

- `app/modules/CrmNotas/CrmNotasController.php` — endpoints `panel()` y `listPartial()`, ajustes de redirects en `store`/`update`/`copy`, precarga de `activeNota` en `index`.
- `app/modules/CrmNotas/views/index.php` — layout split completo.
- `app/modules/CrmNotas/views/partials/detail_panel.php` — detalle reusable.
- `app/modules/CrmNotas/views/partials/list_items.php` — lista reusable.
- `public/js/crm-notas-split.js` — controlador del split, persistencia, hotkeys.
- `app/config/routes.php` — nuevas rutas `/lista`, `/panel/{id}`, `/{id}/restore`, `/{id}/force-delete`.
- `app/modules/CrmNotas/MODULE_CONTEXT.md` — reescrita con el patrón split.
- `database/migrations/2026_04_23_00_seed_customer_notes_release_1_20_0.php` — novedad en lenguaje de cliente final.
- `app/config/version.php` — bump a 1.20.0.
