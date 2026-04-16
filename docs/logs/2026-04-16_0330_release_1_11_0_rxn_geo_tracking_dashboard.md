# Release 1.11.0 — RxnGeoTracking Dashboard (Sprint 2)

**Fecha:** 2026-04-16
**Build:** 20260416.2
**Iteración:** Sprint 2 del módulo RxnGeoTracking (fase 4)

---

## Qué se hizo

Sprint 2 agrega el **dashboard admin** del módulo RxnGeoTracking. Ahora los administradores de cada empresa pueden ver los eventos tracked en un mapa + listado + filtros, exportarlos a CSV y configurar el comportamiento del módulo para su tenant.

### Nuevos archivos

**Backend** (3):
- `app/modules/RxnGeoTracking/RxnGeoTrackingController.php` — 3 actions (index, mapPoints AJAX, export CSV).
- `app/modules/RxnGeoTracking/RxnGeoTrackingConfigController.php` — show + update (config por empresa).
- `app/modules/RxnGeoTracking/views/dashboard.php` — página completa con filtros + mapa + tabla + paginación.
- `app/modules/RxnGeoTracking/views/config.php` — formulario de configuración.

**Frontend** (1):
- `public/js/rxn-geo-tracking-dashboard.js` — inicializa Google Maps, pobla markers via AJAX, InfoWindow con detalle al click.

### Archivos modificados

- `app/modules/RxnGeoTracking/GeoEventRepository.php` — 6 métodos nuevos: `countAll()`, `findPaginated()`, `findForMap()`, `findForExport()`, `findById()`, `findDistinctUsersInRange()`, más helper privado `applyFilters()` con whitelist para prevenir SQL injection.
- `app/config/routes.php` — 5 rutas nuevas bajo `/mi-empresa/geo-tracking/`.
- `app/config/version.php` — bump a 1.11.0.

### Rutas nuevas

| Método | Path | Controller |
|--------|------|-----------|
| GET | `/mi-empresa/geo-tracking` | Dashboard (mapa + listado + filtros) |
| GET | `/mi-empresa/geo-tracking/map-points` | JSON para el mapa (AJAX) |
| GET | `/mi-empresa/geo-tracking/export` | Descarga CSV |
| GET | `/mi-empresa/geo-tracking/config` | Formulario de configuración |
| POST | `/mi-empresa/geo-tracking/config` | Actualización de configuración |

Todas validan `AuthService::requireBackofficeAdmin()` dentro del controller (admin de empresa o rxn_admin).

---

## Decisiones de diseño

### 1. Google Maps API key via env var

La key se lee de `GOOGLE_MAPS_API_KEY` en `.env` del servidor (fallback opcional a `GMAPS_API_KEY` para compat legacy). Razones:
- No requiere migración ni tabla nueva.
- Misma key para todos los tenants (razonable: todos los admins usan el mismo mapa genérico, no hay branding).
- Si falta la key, el dashboard renderiza igual con un placeholder que explica cómo configurarla. El listado y export funcionan sin mapa.

**Para producción**: setear `GOOGLE_MAPS_API_KEY=AIza...` en el `.env` del server y restringir la key por HTTP referrer al dominio `suite.reaxionsoluciones.com.ar`.

### 2. Límites duros del dashboard

- **Mapa**: máx 500 puntos renderizados. Si los filtros matchean más, el status del mapa muestra "Mostrando 500 de X — acotá filtros". Evita tirar el browser con 10k markers.
- **Export**: máx 10.000 filas. Si hay más, el admin ajusta el rango de fechas.
- **Paginación**: 25/50/100 filas. Default 25.

### 3. Rango default: últimos 7 días

Si el admin entra sin filtros, `date_from` arranca en hoy − 7 días. Evita cargas pesadas iniciales.

### 4. Color-coding por tipo de evento

- 🔘 `login` → gris `#6c757d`
- 🔵 `presupuesto.created` → azul `#0d6efd`
- 🟢 `tratativa.created` → verde `#198754`
- 🟠 `pds.created` → naranja `#fd7e14`

### 5. Whitelist dura de filtros SQL

`applyFilters()` del repository valida:
- `event_type` contra `GeoTrackingService::VALID_EVENT_TYPES`.
- `entidad_tipo` contra `['presupuesto', 'tratativa', 'pds']`.
- `date_from` / `date_to` contra regex `/^\d{4}-\d{2}-\d{2}$/`.

Todos los demás campos usan prepared statements con placeholders nombrados. Cero chance de SQL injection.

### 6. Guard: `requireBackofficeAdmin()` en el controller

No se usa wrapper `$action()` en routes.php porque este guard ya existe en `AuthService` y es más simple invocarlo dentro de cada action. Valida `es_admin=1` o `es_rxn_admin=1`.

---

## Variables de entorno nuevas

Agregar al `.env` del servidor (prod y local si se quiere probar con mapa):

```
GOOGLE_MAPS_API_KEY=AIzaSyC...
```

Si esta key no existe el dashboard sigue funcionando pero sin mapa — listado y export operan con normalidad.

---

## Validación

- ✅ `php -l` OK en los 4 archivos PHP nuevos + 2 modificados.
- ✅ El sprint no tocó DB — las 3 tablas del sprint 1 soportan todo el dashboard.
- 🔲 **Pendiente manual**: entrar como admin a `/mi-empresa/geo-tracking` y verificar (1) que el mapa cargue si hay key configurada, (2) que los filtros funcionen, (3) que el export CSV descargue con BOM UTF-8 y los acentos se vean bien en Excel, (4) que `/mi-empresa/geo-tracking/config` guarde cambios y los valide.

---

## Pendiente (últimos 2 sprints)

- **Fase 5 — Integración en transaccionales**: 1 línea en `CrmPresupuestos\PresupuestoController::store()`, `CrmTratativas\TratativaController::store()`, `CrmPedidosServicio\PedidoServicioController::store()` para invocar `GeoTrackingService::registrar()` al crear. Con esto el dashboard pasa de mostrar solo logins a mostrar toda la actividad comercial.
- **Fase 6 — Job de purga periódica**: CLI script que itera empresas y borra eventos más viejos que `retention_days`. Schedule vía cron.

---

## Archivos

### Nuevos
- `app/modules/RxnGeoTracking/RxnGeoTrackingController.php`
- `app/modules/RxnGeoTracking/RxnGeoTrackingConfigController.php`
- `app/modules/RxnGeoTracking/views/dashboard.php`
- `app/modules/RxnGeoTracking/views/config.php`
- `public/js/rxn-geo-tracking-dashboard.js`

### Modificados
- `app/modules/RxnGeoTracking/GeoEventRepository.php` — queries del dashboard.
- `app/config/routes.php` — 5 rutas nuevas.
- `app/config/version.php` — bump a 1.11.0.
