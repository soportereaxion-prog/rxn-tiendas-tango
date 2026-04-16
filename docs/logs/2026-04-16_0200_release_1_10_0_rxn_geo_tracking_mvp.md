# Release 1.10.0 — RxnGeoTracking MVP (Sprint 1)

**Fecha:** 2026-04-16
**Build:** 20260416.1
**Iteración:** Sprint 1 del módulo RxnGeoTracking (fases 1+2+3)

---

## Qué se hizo

Se implementó el MVP end-to-end del módulo nuevo `RxnGeoTracking` para trackear ubicación + IP de usuarios en acciones críticas de la suite. Este sprint cubre tres fases del MODULE_CONTEXT:

### Fase 1 — Infraestructura

- **Migración `2026_04_16_create_rxn_geo_tracking_tables.php`** con las 3 tablas del módulo (idempotente, usa `CREATE TABLE IF NOT EXISTS`).
- **Módulo `app/modules/RxnGeoTracking/`** con el stack completo:
  - `Dto/GeoLocation.php` — value object con lat/lng/city/region/countryCode.
  - `IpGeolocationResolver.php` — interface.
  - `IpApiResolver.php` — implementación default usando `ip-api.com` (free tier, HTTP, 45 req/min, timeout 2s).
  - `GeoEventRepository.php` — `create()`, `updatePositionFromBrowser()`, `purgeOlderThan()`.
  - `GeoTrackingConfigRepository.php` — getters por empresa con defaults (habilitado=1, retention=90d, requires_gps=0, consent_version='v1'), método `upsert()` para el futuro dashboard admin.
  - `GeoConsentRepository.php` — `record()`, `findDecision()`, `hasAnsweredCurrentVersion()`.
  - `GeoTrackingService.php` — **fachada pública** con 4 métodos: `registrar()`, `reportarPosicionBrowser()`, `tieneConsentimientoVigente()`, `registrarConsentimiento()`. Invariante dura: **nunca lanza excepción**.

### Fase 2 — Consentimiento (Ley 25.326)

- **Tabla `rxn_geo_consent`** con índice único `(user_id, empresa_id, consent_version)` para evitar duplicados por versión pero permitir histórico cuando sube la versión.
- **Partial `views/_consent_banner.php`** con decisión de render server-side (chequea si el user ya respondió la `consent_version_current` antes de emitir HTML).
- **Controller `RxnGeoTrackingConsentController`** para `POST /geo-tracking/consent`. Acepta JSON body o POST form. Valida decisión contra whitelist (`accepted|denied|later`).
- **Frontend `public/js/rxn-geo-consent.js`** — maneja clicks del banner, fade-out al success, dispara `CustomEvent('rxn:geo-consent-changed')`.
- **Legal**: cada respuesta se persiste con IP + user-agent al momento de la decisión (prueba legal).

### Fase 3 — Integración en Auth

- **`AuthService::attempt()` modificado**: después de `session_regenerate_id(true)` y la inyección de `$_SESSION`, invoca `GeoTrackingService::registrar(EVENT_LOGIN)` en try/catch silencioso. El `evento_id` creado queda en `$_SESSION['rxn_geo_pending_event_id']`.
- **`admin_layout.php` modificado**:
  - En `<head>`: consume `$_SESSION['rxn_geo_pending_event_id']` y lo emite como `<meta name="rxn-pending-geo-event" content="ID">` (one-shot, unset tras consumir).
  - Antes de scripts: incluye partial `_consent_banner.php` + los 2 JS nuevos.
- **Frontend `public/js/rxn-geo-tracking.js`**: expone `window.RxnGeoTracking.report(eventoId)`. Auto-reporta al `DOMContentLoaded` si hay meta tag. Timeout manual 5.5s sobre `navigator.geolocation.getCurrentPosition()`. Heurística: accuracy < 100m → `'gps'`, mayor → `'wifi'`. Error code 1 (permission denied) → `'denied'`.

---

## Rutas agregadas

- `POST /geo-tracking/consent` — graba la decisión del banner. Requiere login. Cross-tenant.
- `POST /geo-tracking/report` — recibe lat/lng/accuracy del browser y los asocia al evento creado server-side. Requiere login. Validación de propiedad (user_id del evento === user_id de sesión).

---

## Por qué

Charly pidió trackear ubicación de dispositivos/usuarios al iniciar sesión y crear presupuestos/tratativas/PDS (entorno de ventas, auditoría comercial). La arquitectura quedó sembrada en los `MODULE_CONTEXT.md` de los 5 módulos relevantes (2026-04-16 AM) con el plan de 6 fases. Este sprint materializa las primeras 3 — las mínimas para validar el pipe end-to-end con el login como primer punto de integración, antes de tocar los módulos transaccionales.

**Cumplimiento legal (Ley 25.326)**: el banner de consentimiento es obligatorio y trazable por versión. Sin consentimiento explícito del usuario no podemos activar el tracking en producción.

---

## Impacto

- **Schema**: 3 tablas nuevas (`rxn_geo_eventos`, `rxn_geo_config`, `rxn_geo_consent`). Ninguna toca tablas existentes.
- **Backend**: nuevo namespace `App\Modules\RxnGeoTracking`. Un solo módulo existente modificado (`Auth\AuthService`).
- **Frontend**: 2 JS nuevos + 1 partial. Admin_layout.php tocado en `<head>` y antes de scripts.
- **Riesgo operativo**: **bajo**. El service es fire-and-forget: si el tracking falla, ningún módulo consumidor lo siente.
- **Performance**: una llamada cURL externa (ip-api.com) por login/creación. Timeout 2s. En peor caso agrega 2s al login pero no lo bloquea ante caída del provider (el evento se persiste igual sin ciudad/país).

---

## Decisiones tomadas

1. **ip-api.com como resolver default**: free tier, zero-config, HTTP. Abstraído detrás de `IpGeolocationResolver` — migrar a MaxMind GeoLite2 self-hosted es un cambio de 1 línea (el binding del service) cuando queramos mejorar privacidad.
2. **Nombre del módulo: `RxnGeoTracking` (prefijo `Rxn`, no `Crm`)**: porque toca Auth también, es más sistémico que específico del CRM.
3. **Defaults implícitos en `GeoTrackingConfigRepository`**: sin fila en `rxn_geo_config` se asume la config default. Evita migración para poblar una fila por empresa preventivamente.
4. **Retención configurable por empresa**: default 90 días, rango [30, 730]. Validación server-side en `upsert()`.
5. **"Decidir después" NO cierra la pregunta**: el banner vuelve a aparecer la próxima sesión. Charly pidió consentimiento obvio y explícito.
6. **Un solo commit atómico** para todo el sprint 1 (no 15 commits chicos).

---

## Validación

- ✅ `php tools/run_migrations.php` → OK. Las 3 tablas existen con las columnas/índices esperados.
- ✅ `php -l` pasa en todos los archivos nuevos y modificados sin errores de sintaxis.
- 🔲 **Pendiente**: test manual end-to-end (loguear, ver banner, responder, verificar fila en `rxn_geo_consent`, verificar fila en `rxn_geo_eventos` con `event_type='login'` e IP correcta).

---

## Pendiente (futuros sprints)

- **Fase 4 — Dashboard admin con Google Maps**: controller, vista, mapa con markers, filtros, export CSV.
- **Fase 5 — Integración en transaccionales**: agregar 1 línea a `CrmPresupuestos\PresupuestoController::store()`, `CrmTratativas\TratativaController::store()`, `CrmPedidosServicio\PedidoServicioController::store()` para invocar `GeoTrackingService::registrar()` después del commit exitoso.
- **Fase 6 — Job de purga periódica**: CLI script que itera empresas y borra eventos más viejos que `retention_days`. Schedulear vía cron del server.

---

## Archivos nuevos

- `database/migrations/2026_04_16_create_rxn_geo_tracking_tables.php`
- `app/modules/RxnGeoTracking/Dto/GeoLocation.php`
- `app/modules/RxnGeoTracking/IpGeolocationResolver.php`
- `app/modules/RxnGeoTracking/IpApiResolver.php`
- `app/modules/RxnGeoTracking/GeoEventRepository.php`
- `app/modules/RxnGeoTracking/GeoTrackingConfigRepository.php`
- `app/modules/RxnGeoTracking/GeoConsentRepository.php`
- `app/modules/RxnGeoTracking/GeoTrackingService.php`
- `app/modules/RxnGeoTracking/RxnGeoTrackingConsentController.php`
- `app/modules/RxnGeoTracking/RxnGeoTrackingReportController.php`
- `app/modules/RxnGeoTracking/views/_consent_banner.php`
- `public/js/rxn-geo-consent.js`
- `public/js/rxn-geo-tracking.js`

## Archivos modificados

- `app/modules/Auth/AuthService.php` — invoca `GeoTrackingService::registrar(EVENT_LOGIN)` después de `session_regenerate_id`.
- `app/shared/views/admin_layout.php` — inyecta meta tag del evento pendiente + incluye banner y scripts.
- `app/config/routes.php` — 2 rutas nuevas bajo `/geo-tracking/`.
- `app/config/version.php` — bump a 1.10.0 + entry en history.
