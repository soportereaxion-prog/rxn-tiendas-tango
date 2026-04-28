# Release 1.26.0 — Recordatorios de notas dispararados por n8n (tick cada 1 min)

**Fecha**: 2026-04-28
**Versión**: 1.26.0
**Build**: 20260428.3

## Tema

Resolver el agujero del release 1.25.0: los recordatorios de notas solo
disparaban si el usuario abría la suite (late firer dentro del feed de la
campanita). Sin nadie con la suite abierta a la hora marcada → recordatorio en
limbo. Sin cron en Plesk, la solución venía por n8n self-hosted.

## Qué se hizo

### Backend (PHP)

1. **`app/core/Services/NotificationDispatcherService.php`** (nuevo)
   - Servicio multi-tenant, sin estado de sesión.
   - `tick()` itera todas las empresas/usuarios buscando notas con
     `fecha_recordatorio <= NOW() AND recordatorio_disparado_at IS NULL`.
   - Por cada match: `NotificationService::notify(...)` + UPDATE de marca.
   - Devuelve `{ processed, by_source, errors, elapsed_ms }` para que el caller
     (n8n) tenga visibilidad de qué pasó.
   - Diseñado para crecer: cada fuente nueva (turnos vencidos, presupuestos por
     vencer, etc.) suma un método privado `dispatchX()` y `tick()` los compone.

2. **`app/modules/Notifications/NotificationController::tick()`**
   - Endpoint público sin sesión, autenticado por header `X-RXN-Token` con
     `hash_equals` contra `N8N_CALLBACK_TOKEN` del `.env`.
   - Patrón idéntico al de CrmMailMasivos `processBatch()` para consistencia.
   - Devuelve JSON con el resumen + tiempo de ejecución.
   - Atrapa Throwable interno para que n8n nunca reciba un 500 inesperado.

3. **`app/config/routes.php`**
   - `POST /api/internal/notifications/tick → NotificationController::tick`
     (sin guard, valida adentro).

4. **`database/migrations/2026_04_28_03_index_crm_notas_recordatorio_global.php`**
   - `CREATE INDEX idx_notas_recordatorio_global ON crm_notas
     (recordatorio_disparado_at, fecha_recordatorio, deleted_at, activo)`.
   - El índice anterior `idx_notas_recordatorio_pendiente` está optimizado para
     queries por usuario (lleva `created_by` como segunda columna). El tick es
     global y necesita un índice distinto para no full-scan la tabla.
   - Idempotente con `SHOW INDEX`.

5. **`tools/smoke_notifications_tick.php`** (nuevo)
   - CLI que invoca el dispatcher sin pasar por HTTP. Útil para validar antes
     del workflow remoto y para diagnóstico ad-hoc.

### n8n

Workflow **"RxnSuite — Notifications Tick"** (id `7MBeNYv2Zc6QGDjZ`):

- Schedule Trigger: cada 1 minuto.
- HTTP Request POST a
  `https://suite.reaxionsoluciones.com.ar/api/internal/notifications/tick`.
- Credencial: `httpHeaderAuth` con header `X-RXN-Token` (Charly carga el value
  con el token del `.env` de prod).
- `neverError: true` y `timeout: 15s` para que un blip de red no rompa el ciclo.

URL del workflow:
https://n8n.srv1045108.hstgr.cloud/workflow/7MBeNYv2Zc6QGDjZ

## Runbook de activación

Una vez instalado el OTA en prod:

1. Entrar al workflow en n8n.
2. Click en el nodo **POST /api/internal/notifications/tick**.
3. En "Credential to connect with" → crear nueva del tipo **Header Auth**:
   - Name: `RXN Internal Token (X-RXN-Token)`
   - Header Name: `X-RXN-Token`
   - Header Value: el valor exacto de `N8N_CALLBACK_TOKEN` del `.env` de prod
     (es el mismo que ya usa el workflow de Mail Masivos —
     `7a7534a36d20137e76...`).
4. Activar el toggle del workflow.
5. Verificar la primera ejecución en la pestaña **Executions** (debe responder
   200 con `{ ok: true, processed: 0, by_source: {...}, elapsed_ms: <50 }`).

## Por qué

El late firer del feed (release 1.25.0) era una solución elegante "sin
infraestructura", pero deja un punto ciego: si nadie tiene la suite abierta
cuando vence el recordatorio, la notif no se crea hasta que alguien entre. Para
algo cuyo propósito es justamente avisarte de algo que vos podés haberte
olvidado, eso no alcanza.

n8n self-hosted ya está en uso (workflow de Mail Masivos), así que sumar un
nuevo workflow de cron no agrega infra ni costo. El reparto sigue siendo limpio:
n8n hace el reloj, la app hace la lógica de negocio.

## Idempotencia

Dos capas de defensa:

1. El dispatcher marca `recordatorio_disparado_at = NOW()` por cada nota
   procesada — siguientes ticks no la vuelven a tomar.
2. El `NotificationService` deduplica por `dedupeKey` estable
   (`crm_notas.recordatorio.{id}`) en ventana de 24h. Si por carrera el late
   firer y el tick procesan la misma nota en milisegundos de diferencia, una
   sola notif termina en la campanita.

## Validación

- `php tools/smoke_notifications_tick.php` (sin recordatorios pendientes →
  `processed: 0`, `elapsed_ms: 22`). ✓
- Migración aplicada local sin error. ✓
- Workflow validado por el SDK de n8n (`validate_workflow` → `valid: true`). ✓

## Pendiente

- **Web Push** (popups nativos del navegador): pospuesto a fase 2 — requiere
  composer require `minishlink/web-push`, manejo de VAPID keys, Service Worker,
  UI de opt-in en Mi Perfil. Se le va a proponer a Charly como release
  separada.
- Mañana después del primer día de tick activo: revisar `Executions` en n8n
  para ver tiempo promedio y detectar si conviene bajar el index TTL o sumar
  alertas en n8n cuando el tick falla N veces seguidas.
