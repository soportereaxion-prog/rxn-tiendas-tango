# MODULE_CONTEXT — RxnLive

## Nivel de criticidad
MEDIO

Este módulo impacta en:
- Visibilidad analítica transversal sobre datos operativos del sistema (ventas, clientes, PDS).
- Exportación masiva de datos a CSV/XLSX.
- Vistas SQL (`VIEW`) que cruzan múltiples tablas del negocio.

No muta datos de negocio, pero errores en las vistas SQL o en la construcción de queries pueden exponer datos cruzados entre empresas si se pierde el filtrado multitenant.

---

## Propósito

Módulo de análisis operativo (BI liviano) que expone datasets predefinidos basados en vistas SQL (`RXN_LIVE_VW_*`) con funcionalidad de:
- Exploración tabular con filtros avanzados, ordenamiento y paginación.
- Pivot tables configurables por el usuario.
- Gráficos dinámicos (Chart.js) por agrupación y métricas.
- Exportación a CSV y XLSX (via OpenSpout).
- Vistas personalizadas guardadas por usuario.

Accesible desde cualquier área operativa (CRM, Tiendas, Admin) mediante parámetro `?from=` que configura el botón de retorno.

---

## Alcance

### Qué hace
- Listado de datasets disponibles en pantalla de selección (index).
- Exploración de dataset con tabla plana, filtros avanzados (`AdvancedQueryFilter`), ordenamiento por columna y paginación (50 registros/página).
- Pivot table interactivo con agrupaciones por filas/columnas y funciones de agregación (SUM, COUNT, AVG, etc.).
- Gráficos dinámicos configurables (bar, doughnut, line, etc.).
- Filtros discretos por columna (checkbox de valores únicos).
- **Resize de columnas persistente** (desde v1.9.0): drag en el borde derecho de cada `<th>` ajusta el ancho. Se persiste en `sessionStorage` (`rxn_live_volatile_<dataset>`) y en el config JSON de la vista guardada. Rango defensivo `[40, 800]` px.
- **Switch global "Ajustar"** (desde v1.9.0): alterna toda la tabla entre modo truncar con ellipsis + tooltip al hover (default) y modo wrap que ajusta al ancho y crece en alto (estilo celda Excel). Misma persistencia que los widths.
- Exportación a CSV (con BOM UTF-8) y XLSX (OpenSpout). **El XLSX usa paleta fija tipo "Tabla azul" de Excel** — independiente del tema de la UI. Tres niveles visuales:
  - Header: fondo `#4472C4` + texto blanco bold.
  - Body: fondo blanco + texto negro.
  - Footer totals (solo si hay columnas numéricas): fondo `#D9E1F2` + texto negro bold, con label "TOTAL" en la primera columna no numérica visible.
- **El export respeta los anchos de columna configurados por el user** (desde v1.9.1): `colWidths` del frontend se convierte a Excel width units (`px / 7`) y se aplica con `OpenSpout\Writer\XLSX\Options::setColumnWidth` antes de escribir filas.
- **El export incluye fila de totales** (desde v1.9.1): sumatorias de columnas numéricas exportadas como valor precalculado (NO como fórmula Excel). Aplican a ambos formatos CSV y XLSX; en XLSX con estilo destacado.
- **Los filtros client-side (flat + discrete) se envían al export** (desde v1.9.0): el JS empuja `flat_filters`, `discrete_filters` y `global_date_format` al form, y el controller los aplica en memoria replicando el formato visual de fechas.
- Guardado y carga de "vistas" personalizadas por dataset (persistidas en `rxn_live_vistas`). **Desde v1.16.2** el scope de lectura es por empresa: todos los usuarios de la misma empresa ven las mismas vistas, pero solo el dueño (usuario_id) puede sobrescribir o eliminar. En el dropdown, las vistas ajenas se muestran con el nombre del dueño al costado y los botones "Guardar" / "Eliminar" se ocultan (el user ajeno puede duplicarla con "Nueva Vista").
- Vistas de sistema predefinidas (hardcodeadas en `getSystemDefaultViews()`).
- **Formato numérico**: los valores de columnas `type: numeric` se muestran con 4 decimales fijos (en español-AR) en la vista plana, subtotales, totales del tfoot, pivot y tooltips de chart (desde v1.16.2).
- Memoria de último estado de navegación por dataset en sesión.

### Qué NO hace
- No muta datos de negocio. Es estrictamente de lectura.
- No genera alertas, notificaciones ni dashboards persistentes.
- No implementa control de acceso granular por dataset (cualquier usuario autenticado accede a todo).
- No sincroniza datos con fuentes externas.
- No crea ni administra las vistas SQL (`RXN_LIVE_VW_*`); estas se gestionan vía migraciones versionadas.

---

## Piezas principales

### Controlador
- `RxnLiveController.php` — 243 líneas
  - `index()`: pantalla de selección de datasets.
  - `dataset()`: exploración de dataset con filtros, paginación y vistas guardadas. Gestiona memoria de último URL por dataset en sesión.
  - `exportar()`: exportación POST a CSV o XLSX con filtros discretos, columnas ocultas y orden personalizado.
  - `guardarVista()`: endpoint AJAX para persistir/actualizar vistas del usuario.

### Servicio
- `RxnLiveService.php` — 300 líneas
  - Catálogo de datasets con metadata: nombre, vista SQL, descripción, configuración de chart y pivot.
  - `getDatasetData()`: query parametrizada sobre vista SQL con filtros, ordenamiento y paginación.
  - `getDatasetCount()`: conteo para paginación.
  - `buildQuery()`: constructor de WHERE con sanitización de nombres de columna (`preg_replace`) + soporte para filtros avanzados vía `AdvancedQueryFilter`.
  - `getUserViews()` / `saveUserView()`: CRUD de vistas personalizadas con tabla auto-creada on-the-fly.
  - `getSystemDefaultViews()`: vistas de sistema hardcodeadas por dataset.
  - `ensureViewsExist()`: método vacío — la recreación de vistas SQL se maneja exclusivamente vía migraciones.

### Vistas
- `views/index.php` — Selector de datasets.
- `views/dataset.php` — Explorador principal con tabs (tabla, pivot, chart), filtros y acciones.

---

## Rutas / Pantallas

| Método | URI | Acción |
|--------|-----|--------|
| GET | `/rxn_live` | `index` |
| GET | `/rxn_live/dataset?dataset={key}` | `dataset` |
| POST | `/rxn_live/exportar` | `exportar` |
| POST | `/rxn_live/guardar-vista` | `guardarVista` (JSON) |

---

## Datasets registrados

| Key | Nombre | Vista SQL | Chart tipo |
|-----|--------|-----------|------------|
| `ventas_historico` | Ventas Histórico | `RXN_LIVE_VW_VENTAS` | bar |
| `ventas_estados` | Integración Tango | `RXN_LIVE_VW_VENTAS` | doughnut |
| `clientes` | Análisis de Clientes | `RXN_LIVE_VW_CLIENTES` | doughnut |
| `pedidos_servicio` | Pedidos de Servicio (Tiempos) | `RXN_LIVE_VW_PEDIDOS_SERVICIO` | bar |

---

## Tablas / Persistencia

| Tabla | Rol |
|-------|-----|
| `rxn_live_vistas` | Vistas guardadas por dataset (scope lectura por `empresa_id`, ownership por `usuario_id`) |
| `RXN_LIVE_VW_VENTAS` | Vista SQL sobre datos de ventas |
| `RXN_LIVE_VW_CLIENTES` | Vista SQL sobre datos de clientes CRM |
| `RXN_LIVE_VW_PEDIDOS_SERVICIO` | Vista SQL sobre pedidos de servicio con tiempos |

> `rxn_live_vistas` se crea automáticamente en `saveUserView()` con `CREATE TABLE IF NOT EXISTS`.
> Las vistas SQL (`RXN_LIVE_VW_*`) se administran exclusivamente vía migraciones versionadas en `database/migrations/`.

---

## Dependencias directas

| Dependencia | Tipo | Motivo |
|-------------|------|--------|
| `App\Core\Database` | Core | Conexión PDO |
| `App\Core\View` | Core | Render de vistas |
| `App\Core\AdvancedQueryFilter` | Core | Filtros avanzados parametrizados |
| `OpenSpout\Writer\XLSX\Writer` | Vendor | Exportación XLSX (opcional, falla gracefully si no está instalado) |

---

## Dependencias indirectas / Impacto lateral

- Las vistas SQL (`RXN_LIVE_VW_*`) dependen de tablas de negocio de múltiples módulos. Un cambio de esquema en tablas como `ventas`, `crm_clientes`, `crm_pedidos_servicio` puede romper las vistas.
- El módulo no depende de `AuthService::requireLogin()` explícitamente en el controlador (la protección de acceso debe estar en el router).

---

## Seguridad

### Aislamiento multiempresa
**IMPORTANTE**: El filtrado multitenant depende **exclusivamente de las vistas SQL** (`RXN_LIVE_VW_*`). El módulo RxnLive no inyecta `empresa_id` en las queries. Si una vista SQL no filtra por `empresa_id` del usuario en sesión, se expondrán datos cruzados entre empresas. Las vistas deben incorporar filtrado por `empresa_id` internamente.

### Permisos / Guards
No hay guard explícito de autenticación en `RxnLiveController`. La protección de acceso depende del router. Tampoco hay diferenciación Admin Sistema vs Admin Tenant.

### Mutación por método
- `guardarVista()` es el único endpoint que muta estado (INSERT/UPDATE en `rxn_live_vistas`), y opera por **POST**.
- `dataset()` muta `$_SESSION` (último URL visitado) por **GET**. Esto es benigno (memoria de navegación).
- `exportar()` opera por **POST**. No muta datos de negocio.

### Validación server-side
- Los nombres de columna para filtros y ordenamiento se sanitizan con `preg_replace('/[^a-zA-Z0-9_]/', '')`.
- Los filtros avanzados se procesan vía `AdvancedQueryFilter::build()` con parametrización.

### Escape / XSS
- Los datos de las vistas SQL se renderizan directamente en la tabla HTML del frontend. El escape depende de la vista (`dataset.php`).
- Los nombres de dataset se escapan con `htmlspecialchars` en el header de exportación CSV/XLSX.

### CSRF
- `guardarVista()` no valida token CSRF.
- `exportar()` no valida token CSRF.

### Acceso local
- Sin impacto significativo. Los datos exportados se generan on-the-fly vía streaming; no se persisten en disco.

---

## Layout / Viewport — sizer dinámico del card de la tabla

**Regla global de RxnLive** (aplica a todos los datasets: Ventas Histórico, Integración Tango, Clientes, Pedidos de Servicio, y cualquier dataset futuro).

Los tab-panes (`#plana` y `#pivotResultContainer`) **no deben tener `max-height` hardcodeado**. El alto se controla sobre el **card completo del table-section-col** (no sobre el tab-pane interno), usando layout flex para que el pane ocupe sin hueco lo que sobra entre header y footer.

### Setup CSS (en el `<style>` de `views/dataset.php`)

```css
.rxn-live-shell #tableSectionCol > .card {
    display: flex;
    flex-direction: column;
}
.rxn-live-shell #tableSectionCol > .card > .card-body.tab-content {
    flex: 1 1 auto;
    min-height: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.rxn-live-shell #tableSectionCol > .card > .card-body.tab-content > .tab-pane.active {
    flex: 1 1 auto;
    min-height: 0;
}
.rxn-live-shell .rxn-live-pane {
    overflow-y: auto;
}
```

Con esto el card funciona como un contenedor flex-column donde el tab-pane activo rellena exactamente el espacio que queda entre los tabs y la paginación; nunca hay hueco muerto, independientemente de si el chart-card del col vecino fuerza más alto al row.

### Sizer JS (`installRxnLivePaneSizer`, dos pases)

1. **Pase estimativo**: `maxHeight = innerHeight − card.top − BOTTOM_RESERVE (12px)`. Aplicado al `#tableSectionCol > .card` **y** al `#chartSectionCol > .card` con el mismo valor (si el chart fuera más alto que el table-card, el row `align-items:stretch` de Bootstrap estiraría el table-card y el max-height del card interno generaría hueco en el col — sincronizar ambos evita eso).
2. **Pase correctivo** (doble `requestAnimationFrame`): si `documentElement.scrollHeight − clientHeight > 0`, descuenta ese overflow al max-height de ambos cards. Captura footers globales ("Re@xion Soluciones"), copyrights, márgenes del `<main>` — cualquier cosa debajo del row, sin enumerar.

El doble rAF es obligatorio: el primero deja que el browser aplique el max-height nuevo, el segundo mide el overflow ya sobre el layout actualizado. Sin eso, la corrección se calcula contra el layout previo y sobre-corrige.

Parámetros: `MIN_CARD_HEIGHT = 320px`, `SAFETY_MARGIN = 4px` (colchón anti-scroll por sub-pixel).

### Marcadores HTML

Los dos panes deben llevar la clase **`.rxn-live-pane`** (selector del `overflow-y: auto`) y un `min-height` inline de **260px** para que el empty-state se renderice centrado cuando no hay datos.

### Triggers

- `DOMContentLoaded` / load (según readyState) + `window.load` (altos de imágenes/fuentes tardías).
- `resize` de `window` (debounced 60ms).
- `shown.bs.tab` (al cambiar entre Vista Plana y Tabla Dinámica).
- Indirecto en toggle chart/tabla: `applyViewVisibility` ya hace `window.dispatchEvent(new Event('resize'))` con `setTimeout(50)`.

### Evolución (por qué este approach y no los previos)

1. **v0 (hardcoded)**: `max-height: 70vh` inline en los panes. Derramaba en datasets densos porque el 30vh restante no alcanzaba para topbar + filtros + tabs + paginación + footer global.
2. **v1 (sizer sobre el pane, pase único)**: medía `innerHeight − pane.top − cardFooter.offsetHeight`. Mejoraba pero seguía derramando: no consideraba el footer global de la app, y el body scrolleaba.
3. **v2 (sizer sobre el pane, doble pase)**: agregó corrección por overflow del `<html>`. Quedaba bien el viewport, pero al achicar el pane dejaba hueco enorme dentro del card cuando el chart-card del col vecino forzaba más altura (Bootstrap `align-items:stretch`).
4. **v3 (actual — sizer sobre el card, flex-column)**: max-height al card completo + layout flex interno. El tab-pane ocupa sin hueco el espacio entre header y footer, y la sincronización del max-height con el chart-card evita que el row se estire más allá de lo permitido.

**Forzar re-sizing manual** (ej: tras un render async que cambie el layout interno): `window.rxnLiveResizePanes()`.

**Interacción con el watchdog de resize**: el sizer se suscribe como listener normal (`addEventListener('resize', ...)`) — no dispatcha resize nunca, así que no hay riesgo de loop con `installResizeWatchdog`.

---

## No romper

1. **Nombres de vistas SQL**: los datasets referencian `RXN_LIVE_VW_VENTAS`, `RXN_LIVE_VW_CLIENTES`, `RXN_LIVE_VW_PEDIDOS_SERVICIO`. Renombrar una vista sin actualizar el catálogo rompe el dataset.
2. **Metadata de pivot**: cada dataset tiene `pivot_metadata` que define las columnas disponibles para agrupación y agregación. Cambiar los nombres de columna en las vistas SQL sin actualizar el pivot_metadata rompe la UI de pivot.
3. **Exportación CSV con BOM**: el BOM UTF-8 es necesario para que Excel interprete correctamente los acentos. No eliminarlo.
4. **Formato JSON de `guardarVista()`**: la configuración de vistas se almacena como JSON. Cambiar la estructura sin migrar los registros existentes rompe las vistas guardadas de los usuarios.
5. **Clase `.rxn-live-pane` en tab-panes scrolleables**: `#plana` y `#pivotResultContainer` la necesitan para que `installRxnLivePaneSizer` los encuentre. Si se agrega un tab-pane scrolleable nuevo al dataset.php, sumarle la clase; caso contrario no se va a redimensionar al viewport.
6. **No volver a hardcodear `max-height: Nvh`** en los tab-panes de RxnLive — ver sección "Layout / Viewport".

---

## Riesgos conocidos

1. **Multitenant en vistas SQL**: el aislamiento depende 100% de que cada `RXN_LIVE_VW_*` filtre por `empresa_id` de sesión. No hay segunda barrera en el código PHP. Si una vista pierde el filtro, se exponen datos de otras empresas.
2. **DDL on-the-fly en `saveUserView()`**: ejecuta `CREATE TABLE IF NOT EXISTS` en cada guardado de vista. Costo menor pero patrón inconsistente.
3. **Sin autenticación explícita en controlador**: la seguridad depende del router. Si alguien registra las rutas `/rxn_live/*` sin guard, el módulo queda expuesto.
4. **Exportación sin límite real**: la exportación carga hasta 10.000 registros en memoria. Con datasets grandes, esto puede causar problemas de memoria.
5. **Filtros discretos en memoria**: los filtros discretos de exportación se aplican en PHP (`array_filter`) sobre datos ya cargados. Ineficiente con datasets grandes.
6. **SQL injection mitigado pero no blindado**: la sanitización de nombres de columna usa regex, no whitelist. Un nombre de columna que pase el regex pero sea un keyword SQL podría causar comportamiento inesperado.
7. **Formateo de fechas en filtros backend vs JS**: el controller replica el formateo visual usando `DateTime::format($globalDateFormat)` directo. El JS usa la función propia `formatRxnDate()` con string replace. Los tokens soportados matchean (`Y-m-d`, `d/m/Y`, `d-m-Y`, `d/m/y`, `d-m-y`) — si se agrega un formato nuevo, hay que validar que ambos lados devuelvan el mismo string para que los filtros del export sigan matcheando lo que ve el usuario en pantalla.

---

## Checklist post-cambio

- [ ] La pantalla de selección de datasets carga en `/rxn_live`.
- [ ] Cada dataset carga su explorador con datos en tabla plana.
- [ ] Los filtros avanzados funcionan correctamente.
- [ ] La exportación CSV genera archivo descargable con acentos correctos.
- [ ] La exportación XLSX genera archivo válido (si OpenSpout está instalado).
- [ ] El guardado de vista retorna JSON `success: true` y la vista aparece al recargar.
- [ ] Si se tocaron vistas SQL: verificar que el filtrado por `empresa_id` sigue activo.
- [ ] Abrir un dataset denso (PDS — 45+ filas) y uno liviano (Ventas — 14 filas). En ambos, el card-footer con la paginación debe pegar contra el borde inferior del viewport, sin desborde. Redimensionar la ventana y togglear chart/tabla para verificar que el sizer re-mide.

---

## Tipo de cambios permitidos

- Agregar nuevos datasets al catálogo (con su vista SQL correspondiente vía migración).
- Ajustes de UI en las vistas PHP (tabs, estilos, labels).
- Agregar nuevos formatos de exportación.
- Agregar vistas de sistema predefinidas.

## Tipo de cambios sensibles

- Modificar vistas SQL existentes (`RXN_LIVE_VW_*`) — puede romper datasets y pivot metadata.
- Cambiar la lógica de `buildQuery()` — afecta filtros de todos los datasets.
- Modificar `pivot_metadata` — rompe configuración de pivot guardada por usuarios.
- Eliminar o renombrar columnas en vistas SQL.

---

## Regla de mantenimiento

Este archivo debe actualizarse si cambian:
- Los datasets registrados en `RxnLiveService::$datasets`.
- Las vistas SQL referenciadas (`RXN_LIVE_VW_*`).
- La estructura de `pivot_metadata`.
- Las rutas del módulo.
- La lógica de filtrado, exportación o guardado de vistas.
