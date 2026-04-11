# CRM Agenda — Fase 2 (Security & Change Control)

**Fecha:** 2026-04-11
**Módulo nuevo:** `CrmAgenda`
**Módulos tocados quirúrgicamente:** `CrmPedidosServicio`, `CrmPresupuestos`, `CrmTratativas`, `Dashboard`, `routes.php`

---

## Resumen ejecutivo

Fase 2 del plan aprobado por el rey: **CrmAgenda**, un calendario unificado visual que muestra todos los eventos del CRM (PDS, Presupuestos, Tratativas, eventos manuales) en una sola vista tipo FullCalendar, con sincronización **push-only** hacia Google Calendar por usuario (default) o por empresa (configurable).

Implementa el diseño "sourcing polimórfico" acordado con el rey: tabla propia `crm_agenda_eventos` con columnas `origen_tipo` + `origen_id` que permiten proyectar eventos desde cualquier módulo origen sin duplicar lógica.

**Decisión arquitectónica del pase:** se descartó `google/apiclient` y se implementó el flujo OAuth2 + Calendar API v3 con **cliente cURL nativo**. Ver sección "Decisión: librería Google" abajo.

---

## Archivos creados

### Módulo nuevo `app/modules/CrmAgenda/`
- `AgendaController.php` — ABM de eventos manuales, eventsFeed JSON, OAuth connect/callback/disconnect
- `AgendaRepository.php` — CRUD + búsqueda por origen polimórfico + sync status helpers
- `AgendaProyectorService.php` — Hooks de dominio desde PDS/Presupuesto/Tratativa, idempotente
- `GoogleOAuthService.php` — Flujo OAuth2 completo con cURL nativo + encriptación de tokens
- `GoogleCalendarSyncService.php` — Push create/update/delete a Google Calendar API v3 con cURL
- `MODULE_CONTEXT.md` — Doc completa siguiendo plantilla del proyecto
- `views/index.php` — FullCalendar 6.1.11 via CDN, dark theme, filtros por origen, panel de Google Auth
- `views/form.php` — Alta/edición de eventos manuales con color picker y all-day toggle

### Migraciones `database/migrations/`
- `2026_04_11_create_crm_agenda_eventos.php`
- `2026_04_11_create_crm_google_auth.php`
- `2026_04_11_add_agenda_mode_to_empresa_config_crm.php` (idempotente vía `SHOW COLUMNS`)

### Documentación
- `docs/logs/2026-04-11_crm_agenda_fase_2_security.md` (este archivo)

---

## Archivos modificados quirúrgicamente

### Hooks de proyección (idempotentes, tragan excepciones)

- `app/modules/CrmPedidosServicio/PedidoServicioRepository.php`
    - `create()`: después del commit, llama a `AgendaProyectorService::onPdsSaved($row)` con el array del PDS incluyendo el nuevo ID.
    - `update()`: después del execute, llama a `onPdsSaved()` con los datos actualizados.
    - `deleteByIds()`: después del UPDATE soft-delete, itera los IDs y llama a `onPdsDeleted()` para cada uno.

- `app/modules/CrmPresupuestos/PresupuestoRepository.php`
    - `create()`: después del commit de la transacción, llama a `onPresupuestoSaved()` normalizando `cliente_nombre_snapshot`.
    - `update()`: dentro del try (después de `insertItems`), llama a `onPresupuestoSaved()`.
    - `deleteByIds()`: itera los IDs y llama a `onPresupuestoDeleted()`.

- `app/modules/CrmTratativas/TratativaRepository.php`
    - `create()`: después del commit, llama a `onTratativaSaved()`.
    - `update()`: después del execute, llama a `onTratativaSaved()`.
    - `deleteByIds()`: itera los IDs y llama a `onTratativaDeleted()`.

Todos los hooks están envueltos en `try/catch (\Throwable)` vacíos porque el contrato es **nunca romper el save del módulo origen por un problema en la agenda**.

### Rutas — `app/config/routes.php`
Bloque nuevo `--- MODULO CRM AGENDA ---` con 11 rutas, todas con guard `$requireCrm`:
- `GET /mi-empresa/crm/agenda` — vista principal
- `GET /mi-empresa/crm/agenda/events` — feed JSON para FullCalendar
- `GET /mi-empresa/crm/agenda/crear` — formulario de alta
- `POST /mi-empresa/crm/agenda` — store
- `GET /mi-empresa/crm/agenda/google/connect` — redirect a Google consent
- `GET /mi-empresa/crm/agenda/google/callback` — OAuth2 callback (GET por spec)
- `POST /mi-empresa/crm/agenda/google/disconnect` — disconnect
- `GET /mi-empresa/crm/agenda/{id}/editar` — form de edición
- `POST /mi-empresa/crm/agenda/{id}/eliminar` — soft-delete
- `POST /mi-empresa/crm/agenda/{id}` — update

### Dashboard — `app/modules/Dashboard/views/crm_dashboard.php`
Nueva tarjeta `'agenda'` con icon `bi-calendar-event` apuntando a `/mi-empresa/crm/agenda`.

---

## Decisión arquitectónica: cliente cURL nativo en vez de `google/apiclient`

**En el plan original** discutido con el rey, se mencionó `google/apiclient` como librería a instalar. **Al momento de implementar**, se revisó `composer.json` y se detectó que el proyecto solo tiene `phpmailer/phpmailer` como dependencia directa — el sistema es minimalista y no usa Guzzle ni ningún cliente HTTP externo.

**Se tomó la decisión de implementar con cURL nativo** por las siguientes razones:

1. **Cero dependencias nuevas**: `google/apiclient` arrastra ~15 paquetes transitivos (guzzlehttp, firebase/php-jwt, phpseclib, monolog, ramsey/uuid, etc.) que habrían inflado el `vendor/` sin beneficio real.
2. **Scope real de Fase 2**: solo necesitamos OAuth2 token exchange + events CRUD (POST, PUT, DELETE). Todo HTTP plano. 300 líneas de cURL nativo alcanzan.
3. **Consistencia con el proyecto**: el resto del código no usa Guzzle. Meter `google/apiclient` habría roto la uniformidad del patrón de acceso HTTP.
4. **Debuggeabilidad**: cada request es código propio, cada error es trazable línea por línea.

**Cuándo reconsiderar:** si Fase 3 necesita features avanzados (batch requests, resumable uploads, push notifications con validación de firmas, streaming paginado masivo), ahí sí vale instalar la librería oficial. Por ahora: overkill.

La decisión se documentó en `app/modules/CrmAgenda/MODULE_CONTEXT.md` y en los comentarios de los servicios.

---

## Checklist de Política de Seguridad Base

### ✅ Aislamiento multiempresa (`Context::getEmpresaId()`)
- **Controller**: todos los métodos obtienen `$empresaId = (int) Context::getEmpresaId()` al inicio.
- **Repository**: todas las queries filtran por `empresa_id` explícito pasado como parámetro.
- **eventsFeed**: el endpoint JSON inyecta `empresa_id` del contexto en la query, no acepta parámetro cliente.
- **GoogleOAuthService**: la tabla `crm_google_auth` tiene UNIQUE (empresa_id, usuario_id). El método `findAuth()` siempre filtra por `empresa_id`.
- **Proyector**: `AgendaProyectorService::upsertEvent()` usa `$empresaId` del array de origen para todas las operaciones.

### ✅ Permisos / Guards estrictos en backend
- Todas las 11 rutas de agenda usan el wrapper `$action(..., $requireCrm)`, invocando `EmpresaAccessService::requireCrmAccess()` antes del handler.
- Cada método del controller empieza con `AuthService::requireLogin()` como defensa en profundidad.

### ✅ Separación RXN admin (sistema) vs admin tenant
- `CrmAgenda` es un módulo **tenant**. No expone acciones administrativas de sistema.
- No modifica configuración global ni tablas del core (solo `empresa_config_crm.agenda_google_auth_mode`, que es por empresa).

### ✅ No mutación de estado por peticiones GET
- GET solo para lectura: `index`, `eventsFeed`, `create` (form), `edit` (form), `googleConnect`, `googleCallback` (spec OAuth2 obliga GET aquí, validado con state+nonce).
- POST para todas las mutaciones: `store`, `update`, `eliminar`, `googleDisconnect`.

### ✅ Validación fuerte server-side
- `AgendaController::validateRequest()` levanta `ValidationException` con array de errores.
- Validaciones:
    - `titulo`: obligatorio, max 200 chars.
    - `inicio`, `fin`: parseables con `DateTimeImmutable`, `fin >= inicio`.
    - `color`: formato hex `/^#[0-9a-fA-F]{6}$/`, fallback al color default si no valida.
    - `estado`: debe estar en el ENUM de `AgendaRepository::ESTADOS`, fallback a 'programado'.
    - `origen_tipo` hardcoded a `'manual'` en el controller (nunca se acepta del input).

### ✅ Escape seguro en salida (XSS)
- Todas las salidas dinámicas en las views usan `htmlspecialchars()`.
- Los datos del evento se pasan a FullCalendar via JSON (serialización segura por `json_encode`).
- Los mensajes de Flash se renderizan con `htmlspecialchars()`.
- Los errores de Google API se muestran via Flash pero nunca contienen tokens.

### ✅ Criptografía de tokens OAuth
- `access_token` y `refresh_token` se encriptan con `openssl_encrypt('aes-256-cbc', ...)`.
- IV aleatorio de 16 bytes por cada encriptación.
- Clave derivada: `sha256(APP_KEY + "|empresa=" + empresa_id)`. Esto significa:
    - Si un atacante roba la base pero NO el `.env`, no puede desencriptar.
    - Los tokens de la empresa A son ilegibles con la key de la empresa B (defense in depth).
- El método `decrypt()` lanza excepción descriptiva si la key no coincide, avisando que "APP_KEY puede haber cambiado".

### ✅ State parameter en OAuth2 contra CSRF/replay
- El parámetro `state` del flujo OAuth2 lleva: `{empresa_id, usuario_id, mode, nonce}` codificado en base64url.
- El `nonce = random_bytes(8)` hace que cada request sea único (evita replays de callbacks antiguos).
- Se valida en `handleCallback()` antes de persistir cualquier token.

### ✅ Manejo defensivo de errores en proyección
- TODO el código del `AgendaProyectorService` y del `GoogleCalendarSyncService` tiene `try/catch (\Throwable)` que trága excepciones para no romper el save del módulo origen.
- Los errores se persisten en `crm_agenda_eventos.sync_error` para debugging/rescan posterior.
- El `GoogleCalendarSyncService::deleteRemote` trata 404/410 (evento ya no existe) como éxito silencioso.
- El `refreshTokenIfNeeded` persiste el error en `crm_google_auth.last_error` antes de lanzar.

### ✅ Prepared statements
- 100% PDO con placeholders `:name` o posicionales `?`.
- `eventsFeed` acepta `start`, `end`, `usuario_id`, `origenes[]` como parámetros y los sanitiza/valida antes de usarlos en la query.
- Los nombres de columnas nunca vienen de input del usuario.

### ✅ Protección de datos sensibles en logs
- Los errores que se persisten en `sync_error` y `last_error` son descriptivos pero nunca contienen tokens, passwords, ni payloads completos.
- No se hace `error_log` de bodies completos de Google responses.

---

## Variables de entorno nuevas requeridas

El módulo requiere 3 variables de entorno para que el flujo OAuth funcione. **Sin ellas, el botón "Conectar Google" devuelve error** (la agenda local sigue funcionando igual, solo que sin sync a Google).

```bash
# .env
GOOGLE_CLIENT_ID=xxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xxxx
GOOGLE_REDIRECT_URI=https://tudominio.com/mi-empresa/crm/agenda/google/callback
```

### Setup en Google Cloud Console (manual, una sola vez)

1. Ir a https://console.cloud.google.com/ y crear un proyecto (o reusar uno).
2. Habilitar la **Google Calendar API**.
3. Credentials → Create Credentials → OAuth 2.0 Client ID:
    - Application type: Web application
    - Name: "rxn_suite CRM Agenda"
    - Authorized redirect URIs: `https://tudominio.com/mi-empresa/crm/agenda/google/callback`
4. Copiar `Client ID` y `Client secret` al `.env`.
5. OAuth consent screen → agregar scopes: `https://www.googleapis.com/auth/calendar.events`.
6. Si la app está en "Testing", agregar los emails de los operadores como test users.
7. Para producción: publicar la app (requiere verificación de Google si se usan scopes sensibles).

---

## Nuevo parámetro de empresa

En `empresa_config_crm` se agrega:

```sql
agenda_google_auth_mode ENUM('usuario','empresa') NOT NULL DEFAULT 'usuario'
```

- **`usuario`** (default): cada operador conecta su propia cuenta Google desde la Agenda. Cada uno ve sus eventos en su Google personal.
- **`empresa`**: una sola conexión global para toda la empresa. Todos los eventos del CRM van al mismo calendario (usualmente de un buzón compartido).

El switch se cambia desde el módulo `EmpresaConfig` (pendiente de UI, por ahora se setea via SQL directo). El `GoogleOAuthService::resolveAgendaMode()` lee este campo dinámicamente, así que cambiar el modo no requiere redeploy ni purgar caches.

---

## Flujo completo (end-to-end)

### Creación de un PDS con agenda conectada
1. Operador crea un PDS desde `/mi-empresa/crm/pedidos-servicio/crear`.
2. `PedidoServicioController::store()` valida y llama a `PedidoServicioRepository::create()`.
3. Después del `commit()`, el repo invoca `AgendaProyectorService::onPdsSaved($pds)`.
4. El proyector hace upsert en `crm_agenda_eventos` (create si no existe, update si ya existía).
5. El proyector llama a `GoogleCalendarSyncService::push($event)`.
6. El sync service resuelve el auth activo (modo usuario o empresa):
    - Si no hay auth → no hace nada, retorna false, el evento queda solo local.
    - Si hay auth → refresca el token si está por expirar, hace `POST /calendars/primary/events` a Google.
    - Guarda el `google_event_id` en `crm_agenda_eventos`.
7. Si Google devuelve error, lo persiste en `sync_error` y NO rompe nada.

### Creación de evento manual
1. Operador abre el calendario, hace click en un día vacío o en "Nuevo Evento".
2. Se renderiza `views/form.php` con fecha pre-cargada.
3. POST a `/mi-empresa/crm/agenda` → `store()`.
4. Validación, create, proyección a Google (idéntico al flujo de PDS salvo que `origen_tipo = 'manual'`).

### Flujo OAuth2
1. Operador click en "Conectar con Google" → `/agenda/google/connect`.
2. `googleConnect()` arma la URL con `state` encoded y redirige.
3. Google muestra el consent screen al usuario.
4. Usuario autoriza → Google redirige a `/agenda/google/callback?code=X&state=Y`.
5. `googleCallback()` decodea el state, valida que tenga `empresa_id`, invoca `handleCallback(code, state)`.
6. El service hace POST a `oauth2.googleapis.com/token` con el code → obtiene access + refresh tokens.
7. GET a `oauth2/v2/userinfo` con el access_token para obtener el email del usuario.
8. UPSERT en `crm_google_auth` con los tokens encriptados.
9. Flash de éxito y redirect a `/agenda`.

---

## Testing manual sugerido post-deploy

### Parte A: agenda local (sin Google)
- [ ] Correr las 3 migraciones via módulo de Mantenimiento o CLI.
- [ ] Entrar a `/mi-empresa/crm/agenda` → FullCalendar renderiza.
- [ ] Crear un evento manual → aparece en el calendario.
- [ ] Editar el evento → persiste cambios.
- [ ] Eliminar el evento → desaparece del calendario.
- [ ] Crear un PDS desde `/pedidos-servicio/crear` con fecha_inicio → debe aparecer automáticamente en la agenda con color azul.
- [ ] Crear un Presupuesto → aparece con color verde.
- [ ] Crear una Tratativa con `fecha_cierre_estimado` → aparece como evento all-day amarillo.
- [ ] Click en un evento proyectado redirige al módulo de origen.
- [ ] Click en fecha vacía abre el form de evento manual prellenado.
- [ ] Los filtros de checkbox por origen filtran correctamente el calendario.

### Parte B: sync con Google (requiere configuración previa)
- [ ] Setear `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` en `.env`.
- [ ] Click en "Conectar con Google" → redirige a Google OAuth consent.
- [ ] Autorizar en Google → vuelve al CRM con flash de éxito.
- [ ] El panel muestra "Conectado" con el email de la cuenta.
- [ ] Crear un evento manual nuevo → aparece en Google Calendar del operador (con descripción, ubicación, tiempo).
- [ ] Editar el evento → la edición se refleja en Google.
- [ ] Eliminar el evento → desaparece de Google.
- [ ] Crear un PDS → aparece en Google con prefijo "PDS #N — Cliente".
- [ ] Desconectar → los eventos ya sincronizados NO se borran de Google.

### Parte C: seguridad multitenant
- [ ] Loguearse con una empresa distinta → la agenda NO muestra eventos de la otra empresa.
- [ ] Intentar acceder a `/agenda/X/editar` con un ID de otra empresa → flash danger + redirect.
- [ ] Intentar disparar `eventsFeed` con otro `empresa_id` en query → el backend ignora y usa el del contexto.

---

## Pendientes explícitos para Fase 3 (fuera de scope)

- Sincronización bidireccional (pull desde Google + conflict resolution).
- Webhooks de Google Calendar (push notifications) para enterarse de cambios remotos.
- Batch requests a Calendar API.
- Retries automáticos de push fallidos (cron de reintento).
- UI para cambiar `agenda_google_auth_mode` desde EmpresaConfig.
- Eventos recurrentes (weekly, monthly, custom).
- Invitados / attendees en eventos manuales.
- Recordatorios propios del CRM (notificaciones in-app).
- Lock distribuido para refresh token en escenarios de concurrencia alta.
- Rescan manual de eventos huérfanos (cuando un origen se eliminó sin disparar el hook).
