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
- Guardado y carga de "vistas" personalizadas por usuario y dataset (persistidas en `rxn_live_vistas`).
- Vistas de sistema predefinidas (hardcodeadas en `getSystemDefaultViews()`).
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
| `rxn_live_vistas` | Vistas guardadas por usuario y dataset (auto-creada on-the-fly) |
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

## No romper

1. **Nombres de vistas SQL**: los datasets referencian `RXN_LIVE_VW_VENTAS`, `RXN_LIVE_VW_CLIENTES`, `RXN_LIVE_VW_PEDIDOS_SERVICIO`. Renombrar una vista sin actualizar el catálogo rompe el dataset.
2. **Metadata de pivot**: cada dataset tiene `pivot_metadata` que define las columnas disponibles para agrupación y agregación. Cambiar los nombres de columna en las vistas SQL sin actualizar el pivot_metadata rompe la UI de pivot.
3. **Exportación CSV con BOM**: el BOM UTF-8 es necesario para que Excel interprete correctamente los acentos. No eliminarlo.
4. **Formato JSON de `guardarVista()`**: la configuración de vistas se almacena como JSON. Cambiar la estructura sin migrar los registros existentes rompe las vistas guardadas de los usuarios.

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
