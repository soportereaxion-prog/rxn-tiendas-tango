# Release 1.21.0 — UX transversal: full-width + tema claro + Escape contextual + compactación de formularios

**Fecha**: 2026-04-23
**Build**: 20260423.3
**Estado**: ⚠️ **SIN OTA** — release bumpeada y commiteada, pero no publicada. Queda pendiente resolver el bug vertical del RxnLive dataset (PDS/Clientes) antes de subirla a prod.

## Resumen

Iteración #31 de correcciones UX transversales (tanda larga de Charly). Release grande de polish. La mayoría de ítems quedaron validados y andan bien; solo el fit vertical del RxnLive dataset PDS/Clientes no se resolvió a pesar de varias iteraciones.

## Qué se hizo

### 1. Flujo contextual Tratativa → PDS/Presupuesto/Nota
Patrón unificado: guardar se queda en el doc, Volver regresa a la tratativa de origen.
- PDS ya lo tenía desde 1.19.0.
- Presupuestos: `resolveReturnPath` siempre a `/editar`. Alert-info corregido.
- Notas: `store()` y `update()` redirigen a `/editar`, `$notaBackHref` contextual.

### 2. Escape = Volver (convención `data-rxn-back`)
El atributo `data-rxn-back` marca el botón canónico de cada vista. El dispatcher central de `rxn-shortcuts.js` prioriza ese href sobre `history.back()`. Los handlers propios de PDS y Presupuestos (con confirm "¿salir sin guardar?") también respetan `data-rxn-back`.

Fix del bug: después de guardar, Escape hacía paso atrás hacia el `/editar` previo en lugar de volver al destino declarado. Root cause: el shortcut `rxn-back` tenía `history.back()` como prioridad 1 cuando el referrer era mismo host. Refactor: `[data-rxn-back]` → prioridad 1; heurística legacy → 2; `history.back()` → last resort.

### 3. Full-width transversal
- `.rxn-form-shell` pasó de `max-width:1100px` a `100%` (con override sobre `container/container-xl/lg/md/sm` para romper el escalonado responsive de Bootstrap).
- ~40 vistas inline-capeadas en 1200-1400 quedaron liberadas.
- El `<main>` del admin_layout perdió el cap de 1400px.
- PDS y Presupuestos también pasaron a full-width (shells custom 1460/1480 → 100%).
- El `<main>` recibió `min-width: 0` para no inflarse por contenido intrínseco (es flex item de `body` con `d-flex flex-column`).

### 4. Responsive general
`html, body { overflow-x: clip; max-width: 100vw }` — cap de último recurso que evita scroll horizontal a nivel de documento sin romper dropdowns/tooltips posicionados con `position: absolute`.

### 5. Tema claro en módulos dark-first
Override CSS acotado por wrapper para módulos que tenían `bg-dark text-light` hardcoded: `.notas-split`, `.crm-agenda-shell`, `.crm-tratativas-shell`, `.crm-llamadas-shell`, `.rxn-live-shell`. Neutraliza `bg-dark/text-light/border-secondary` dentro de esos wrappers sin afectar el resto del sistema (topbar, spotlight, modales dark siguen intactos).

Para Bootstrap `.table-dark` dentro de `.rxn-live-shell`: override via CSS vars `--bs-table-bg/color/striped-bg/hover-bg/border-color` — una regla cubre filas, stripe, hover y headers de forma consistente.

Dropdowns `.dropdown-menu-dark` idem via vars (`--bs-dropdown-bg/color/link-*`).

### 6. Compactación de formularios
- PDS: `rxn-form-section` con margin-top entre secciones reducido (override local en `.crm-service-shell`).
- ~22 vistas: `mt-5 mb-5` → `mt-2 mb-5` + `container` → `container-fluid` (Admin, EmpresaConfig, Help, Usuarios, Empresas, Categorias, CrmClientes, Articulos, ClientesWeb, CrmMailMasivos, etc). Pegadas al header arriba.
- PDS y Presupuestos ya tenían `mt-2`, no requirieron cambio.

### 7. Dashboards a 4 columnas
Tarjetas de módulos en tenant/crm/admin_dashboard: `col-sm-6 col-lg-4` → `col-sm-6 col-lg-4 col-xl-3` (2/3/4 columnas según breakpoint). Aprovecha monitores 1920+.

### 8. Fix tema pegado entre pestañas
`UIHelper::getHtmlAttributes()` ahora lee SIEMPRE de DB (antes usaba `$_SESSION[pref_theme]` como caché). El snapshot de sesión PHP entre pestañas generaba race: pestaña A cambia a dark, pestaña B cierra su request con snapshot viejo y sobrescribe. Ahora la DB es la única fuente de verdad.

`toggleTheme` libera el lock de sesión con `session_write_close` inmediato.

El JS del toggle broadcast via `localStorage.setItem('rxn_theme', ...)` y escucha `storage event` → sincroniza pestañas abiertas sin reload.

## ⚠️ Pendiente — bug del scroll vertical RxnLive (PDS/Clientes)

**Síntoma**: en el dataset `pedidos_servicio` y `analisis_clientes`, la tabla se derrama hacia abajo del viewport cuando hay muchas filas. En `ventas_historico` (pocas filas) no se nota.

**Iteraciones probadas**:
1. `min-width: 0` en toda la cadena Flex del shell (col, card, card-body, tab-content, tab-pane, table-responsive) — no resolvió.
2. `min-width: 0` al `<main>` del admin_layout (flex item de body) — ayudó al horizontal pero no resolvió el vertical.
3. `overflow-y: auto` en `.rxn-live-shell .tab-pane.table-responsive` via CSS externo — Charly reportó que seguía igual.
4. `overflow-y: auto` **inline** en los dos tab-panes del dataset.php (`id="plana"` y `id="pivotResultContainer"`) — el inline debería ganar sobre cualquier external, pero Charly reportó que sigue saliendo mocho.

**Hipótesis pendiente de testear en próxima sesión**:
- El `<style>` inline del shell puede estar siendo eclipsado por un `<style>` externo cargado después.
- El `.rxn-scrollbar` (aunque no está definido en CSS conocido) podría estar overriding.
- El padre `#datasetTabsContent` (card-body) puede tener algún estilo que impida que el tab-pane clipee internamente.
- Ventas Histórico puede estar renderizando diferente — vale la pena leer su HTML completo con DevTools y compararlo con PDS.

**Plan próxima sesión**:
1. Abrir DevTools en PDS, inspeccionar el tab-pane `#plana`, ver qué `computed styles` gana y cuáles están overrideados.
2. Comparar con Ventas Histórico (que funciona) para aislar la diferencia.
3. Considerar mover el scroll vertical a un nivel más arriba (card o card-body) en lugar de el tab-pane.

## Archivos tocados

### Código
- `app/modules/CrmNotas/CrmNotasController.php`
- `app/modules/CrmNotas/views/form.php`
- `app/modules/CrmPresupuestos/PresupuestoController.php`
- `app/modules/CrmPresupuestos/views/form.php`
- `app/modules/CrmPedidosServicio/views/form.php`
- `app/modules/CrmTratativas/views/{index,detalle,form}.php`
- `app/modules/CrmLlamadas/views/index.php`
- `app/modules/CrmAgenda/views/{index,form}.php`
- `app/modules/CrmMonitoreoUsuarios/views/index.php`
- `app/modules/RxnLive/views/dataset.php`
- `app/modules/Dashboard/views/{tenant_dashboard,admin_dashboard,crm_dashboard,home}.php`
- `app/modules/Usuarios/views/{mi_perfil,index}.php`
- `app/modules/EmpresaConfig/views/index.php`
- `app/modules/Admin/views/{mantenimiento,rxn_live_vistas,module_notes_index,smtp_global}.php`
- `app/modules/Help/views/operational_help.php`
- `app/modules/CrmMailMasivos/views/{dashboard,plantillas/index,reportes/index,envios/*}.php`
- `app/modules/{Empresas,Categorias,CrmClientes,Articulos,ClientesWeb,Pedidos,PrintForms}/views/*.php`
- `app/modules/Usuarios/UsuarioPerfilController.php`
- `app/core/Helpers/UIHelper.php`
- `app/shared/views/admin_layout.php`
- `app/shared/views/components/backoffice_user_banner.php`
- `public/js/rxn-shortcuts.js`
- `public/js/crm-presupuestos-form.js`
- `public/js/crm-pedidos-servicio-form.js`
- `public/css/rxn-theming.css`

### Docs
- `app/modules/CrmNotas/MODULE_CONTEXT.md`
- `app/modules/CrmTratativas/MODULE_CONTEXT.md`
- `app/modules/CrmPedidosServicio/MODULE_CONTEXT.md`
- `app/modules/CrmPresupuestos/MODULE_CONTEXT.md`

### Versión
- `app/config/version.php` (1.20.1 → 1.21.0 / build 20260423.3)
- `database/migrations/2026_04_23_02_seed_customer_notes_release_1_21_0.php` (placeholder vacío hasta que se publique OTA)

## Por qué no OTA

Regla del 2026-04-20 sobre cierre de sesión: **"cerrar sesión SIEMPRE dispara Factory OTA, excepto si la sesión terminó con algo roto o sin validar"**. El bug del scroll vertical de RxnLive (PDS/Clientes) quedó sin resolver después de 5 iteraciones; Charly prefirió cerrar y atacarlo con contexto fresco en la próxima sesión.

Los cambios se commitean para preservar el trabajo, pero el ZIP de release no se genera hasta que el bug esté fixeado y validado.
