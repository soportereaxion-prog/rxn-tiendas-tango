# 2026-04-14 15:49 — RXN Live: Safe Mode, Admin Tool de Vistas, Eliminar Vista, Excel Theme y Defensas

## Contexto

Charly reportó que en producción una vista guardada por otro usuario (`view_id=3`, nombre "filtrón") dejaba el dataset `pedidos_servicio` titilando al abrirlo. No podía entrar ni con Ctrl+F5, ni borrar la vista (porque era de otro usuario), y la URL con la vista rota quedaba cacheada en `$_SESSION['rxn_live_last_url']`, redirigiendo de vuelta.

El bug está EN PROD, no en local — las herramientas tienen que salir sólidas al deploy porque no se puede reproducir acá.

## Entregado (5 piezas)

### Pieza 1 — Safe Mode (`?safe_mode=1`)

**Qué**: escape hatch para entrar a cualquier dataset ignorando vistas/filtros guardados.

**Backend** — `app/modules/RxnLive/RxnLiveController.php` (`dataset()`):
- Detecta `?safe_mode=1` y skippea el redirect por `last_url` (la URL rota cacheada).
- Limpia el `last_url` del dataset en sesión para que futuras cargas sin safe_mode no vuelvan a caer en la trampa.
- Descarta todos los filtros GET.
- Pasa `myViews=[]` al front para que no haya dropdown pre-seleccionado.

**Frontend** — `app/modules/RxnLive/views/dataset.php`:
- Flag `window.rxnSafeMode` inyectado desde PHP.
- Banner amarillo visible con botón "Salir de Safe Mode".
- En `DOMContentLoaded`, si safe mode está activo: borra el sessionStorage del dataset y renderiza directo vista base.

**Index** — `app/modules/RxnLive/views/index.php`:
- Ícono de escudo (`bi-shield-exclamation`) naranja en la esquina superior derecha de cada card de dataset.
- Tooltip explicativo. Link directo a `?safe_mode=1`.

### Pieza 2 — Herramienta Admin de Vistas (cross-user)

**Qué**: `/admin/rxn_live/vistas` — CRUD cross-user + export/import.

**Archivos nuevos**:
- `app/modules/Admin/Controllers/RxnLiveVistasController.php` — acciones `index`, `ver`, `eliminar`, `exportar`, `importar`. Todas con `AuthService::requireRxnAdmin()`.
- `app/modules/Admin/views/rxn_live_vistas.php` — UI con tabla, filtro por dataset, modales de inspección y de importación.

**Service extendido** — `app/modules/RxnLive/RxnLiveService.php`:
- `deleteUserView(userId, viewId)` — con guard de ownership (para Pieza 3).
- `getAllVistasAdmin()` — JOIN con `usuarios` para mostrar dueño.
- `getVistaByIdAdmin(id)` — lookup cross-user.
- `deleteVistaAdmin(id)` — delete sin ownership check.
- `importVistaAdmin(vista, userId)` — valida dataset, nombre, config. Acepta config como array o JSON string.

**Rutas** — `app/config/routes.php`:
- `GET /admin/rxn_live/vistas`
- `GET /admin/rxn_live/vistas/ver`
- `POST /admin/rxn_live/vistas/eliminar`
- `GET /admin/rxn_live/vistas/exportar` (`?ids=1,2,3` o `?dataset=X` o vacío = todo)
- `POST /admin/rxn_live/vistas/importar` (multipart con `archivo` + `owner_id` opcional)

**Dashboard** — nuevo card en `admin_dashboard.php` con link directo.

**Formato de exportación** (JSON descargable):
```json
{
  "version": 1,
  "exported_at": "2026-04-14T...",
  "source": "rxn_suite",
  "count": 3,
  "vistas": [
    { "id": 3, "usuario_id": 5, "usuario_email": "...",
      "dataset": "pedidos_servicio", "nombre": "filtrón",
      "config": {...}, "created_at": "..." }
  ]
}
```

Importar acepta tanto el wrapper como una vista plana (objeto con `dataset`/`nombre`/`config` en la raíz).

### Pieza 3 — Eliminar Vista (user común)

**Backend**:
- `RxnLiveController::eliminarVista()` — endpoint JSON con guard de ownership vía `deleteUserView`.
- Ruta `POST /rxn_live/eliminar-vista`.

**Frontend** (`dataset.php`):
- Botón "Eliminar" (rojo outline) en el btn-group al lado de "Nueva Vista", con `id="btnDeleteView"` y `display:none` inicial.
- Función `toggleDeleteViewButton()` que muestra el botón sólo cuando hay vista de usuario seleccionada (no `default_*`, no Vista Base).
- Se invoca desde `DOMContentLoaded` y desde `loadSelectedView()`.
- `promptDeleteView()` usa `window.rxnConfirm({title:'Atención', type:'danger', okText:'Eliminar', okClass:'btn-danger', onConfirm})`.
- Al confirmar: limpia sessionStorage del dataset, redirige sin `view_id`.

### Pieza 4 — Excel export theme-aware

**Qué**: el export XLSX ahora aplica estilos según el tema del UI.

El frontend ya inyectaba `theme=dark|light` en el form de export (`dataset.php:1026-1047`, detectado por `data-bs-theme` / clases `bg-dark`). El backend lo recibía pero no lo usaba.

**Implementado** en `RxnLiveController::exportar()`:
- Llama a nuevo método privado `buildXlsxThemeStyles($theme)` que devuelve `[headerStyle, rowStyle]`.
- Usa `OpenSpout\Common\Entity\Style\{Style, Border, BorderPart, BorderName, BorderWidth, BorderStyle}`.

**Paletas**:
- **Oscuro** (default): header `#343A40` bg + blanco bold; filas `#2C3034` bg + `#F8F9FA`; bordes `#495057`.
- **Claro**: header `#E7F1FF` bg + `#0D6EFD` bold; filas blanco + `#212529`; bordes `#DEE2E6`.

### Pieza 5 — Validación defensiva + watchdog de resize

**Qué**: asegurar que un config corrupto nunca más tumbe la UI.

**En `applyViewConfig(config, isVolatile)`** (`dataset.php`):
- Guard inicial: si `config` no es objeto (o es array), se reemplaza por `{}`.
- `flatFilters`/`flatDiscreteFilters` requieren ser objetos NO-array.
- `hiddenCols`/`orderedCols` filtran elementos no-string.
- `urlFilters` requiere objeto no-array + valida que keys y values sean strings (evita inyectar objetos en la URL).
- `chartConfig` se merge sobre defaults en vez de pisar.
- `pivotState.rows/cols/vals` fallback a array vacío si no son arrays.

**En `loadSelectedView()`**:
- Cada llamada a `applyViewConfig` envuelta en try/catch.
- Si falla `applyViewConfig(vState, true)`: limpia sessionStorage + intenta fallback a `data-config` del dropdown.
- Si también falla: llama `showViewConfigError()` y renderiza al menos la tabla plana.

**Nueva función `showViewConfigError(err, viewId)`**:
- Banner rojo en top de página con link directo a Safe Mode.
- Idempotente (no duplica banners).

**Watchdog de resize** (IIFE al final del `<script>`):
- Wrap de `window.dispatchEvent`.
- Si detecta >40 eventos de tipo `resize` en <1000ms, entra en modo "supresión" durante 3s.
- Loggea warning en consola.
- Protege contra ResizeObserver loops que tiran Chart.js en cascada.

## Deploy

**Sin migraciones** — todo es código PHP + JS. La tabla `rxn_live_vistas` ya existe.

**Archivos nuevos**:
- `app/modules/Admin/Controllers/RxnLiveVistasController.php`
- `app/modules/Admin/views/rxn_live_vistas.php`

**Archivos modificados**:
- `app/modules/RxnLive/RxnLiveController.php` — safe mode, eliminarVista, buildXlsxThemeStyles
- `app/modules/RxnLive/RxnLiveService.php` — 5 métodos admin + deleteUserView
- `app/modules/RxnLive/views/index.php` — ícono safe mode en cards
- `app/modules/RxnLive/views/dataset.php` — banner safe mode, flag JS, botón Eliminar, hardening, watchdog
- `app/modules/Dashboard/views/admin_dashboard.php` — card nuevo
- `app/config/routes.php` — 1 ruta user + 5 rutas admin

## Flujo recomendado para el Rey cuando entre a prod

1. **Destrabar el dataset roto**:
   - Ir a `/rxn_live` → clickear el ícono de escudo 🛡️ en la card "Pedidos de Servicio (Tiempos)".
   - O entrar directo: `/rxn_live/dataset?dataset=pedidos_servicio&safe_mode=1`.

2. **Analizar y borrar "filtrón"**:
   - Ir a `/admin/rxn_live/vistas` (también accesible desde el admin dashboard).
   - Filtrar por dataset "Pedidos de Servicio (Tiempos)".
   - Click en el ojo 👁️ de la vista `id=3` "filtrón" para ver el JSON → entender qué la rompió (posible hipótesis: operador `contiene` inválido sobre campo `fecha`).
   - Opcional: click en descarga 📥 de esa vista para guardar el JSON como post-mortem.
   - Click en la papelera 🗑️ → confirmar → borrada.

3. **Post-mortem opcional**: pasarme el JSON de "filtrón" y refino `applyViewConfig` con la validación específica para que el patrón exacto que la rompió sea detectado y rechazado de raíz.

## Decisiones tomadas

- **Admin tool bajo `/admin/rxn_live/vistas`** en lugar de `/rxn_live/admin/vistas` — unificado con el resto del panel admin, según pedido de Charly.
- **Permiso `rxn_admin`** (el existente) en lugar de crear `rxn_live_admin` granular — coherente con mantenimiento y config global.
- **Safe mode como query param** en lugar de ruta separada — mínima invasión, mismo endpoint. El front lee un flag inyectado por PHP.
- **Watchdog global** vía wrap de `dispatchEvent` en lugar de debouncing localizado — protege cualquier futuro dispatch, no sólo los dos conocidos.
- **Export formato wrapped** (`{version, exported_at, vistas:[]}`) en lugar de array plano — permite extender con metadata en el futuro sin romper el schema.
- **Ownership guard en delete user común** vía `WHERE id=? AND usuario_id=?` — previene que un user borre vistas ajenas por adivinar IDs.

## Aprendizajes

- Engram se desconectó a mitad de sesión (el MCP server cerró). No pude consultar el histórico del bug original ni guardar memoria final — por eso este log extenso queda como registro primario.
- El patrón de "URL cacheada en `$_SESSION['rxn_live_last_url']`" explicaba por qué Ctrl+F5 no destrababa nada. El safe mode tuvo que limpiar explícitamente ese cache.
- OpenSpout `Border` requiere instanciar 4 `BorderPart` separados (uno por lado) — no hay atajo "all sides".
