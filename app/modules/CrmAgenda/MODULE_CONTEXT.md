# MODULE_CONTEXT — CrmAgenda

## Nivel de criticidad
MEDIO. La agenda es un módulo de visualización y productividad, no transaccional. Si se rompe, el operador pierde la vista unificada del calendario pero puede seguir operando PDS, Presupuestos y Tratativas normalmente desde sus módulos originales. El sync a Google Calendar es best-effort: cualquier falla en el push se registra en `crm_agenda_eventos.sync_error` pero no bloquea el guardado local.

## Propósito
Ofrecer una vista unificada de todos los eventos del CRM en un calendario visual (FullCalendar), con sincronización push-only a Google Calendar por usuario (default) o por empresa (configurable). Cubre tres fuentes de eventos:

1. **Proyectados** desde otros módulos: PDS (por fecha de inicio), Presupuestos (por fecha), Tratativas (por fecha de cierre estimado). El patrón es de **sourcing polimórfico**: cada evento lleva `origen_tipo` + `origen_id` apuntando a su registro de origen.
2. **Manuales**: eventos creados directamente desde el calendario (reuniones, recordatorios, tareas propias del operador).
3. **Extensibles**: la columna `origen_tipo` es un ENUM con espacio para `llamada`, `tratativa_accion`, y cualquier tipo futuro.

## Alcance
**QUÉ HACE:**
- Lista eventos en rango de fechas vía endpoint JSON (`/events`) para alimentar FullCalendar.
- ABM de eventos manuales (create, edit, soft-delete).
- Proyección automática vía `AgendaProyectorService` cuando los repositories de PDS, Presupuesto y Tratativa guardan un registro (hook explícito).
- Flujo OAuth2 con Google Calendar (connect, callback, disconnect, refresh token automático).
- Push a Google Calendar cuando se crea/edita/borra un evento (best-effort, errores se loguean en la tabla pero no bloquean).
- Resolución dinámica del modo de auth (`usuario` vs `empresa`) según `empresa_config_crm.agenda_google_auth_mode`.

**QUÉ NO HACE (fase 2):**
- **NO es bidireccional**. Los eventos creados directamente en Google Calendar NO vuelven al CRM. Si el operador modifica un evento desde su Google, el CRM no se entera.
- NO usa `google/apiclient` ni ninguna librería externa de Google. Todo cURL nativo. Ver decisión abajo.
- NO permite editar eventos proyectados (PDS, Presupuestos, Tratativas) desde el calendario — click redirige al módulo de origen.
- NO soporta recurrencia (eventos recurrentes semanales, mensuales, etc.).
- NO maneja invitados / attendees. El evento va al calendario del usuario autenticado y nada más.
- NO tiene recordatorios / notificaciones propias del CRM.

## Decisión: cliente cURL nativo vs `google/apiclient`
Se eligió **cliente cURL nativo** en vez de `google/apiclient` para el flujo OAuth2 y las llamadas a Calendar API v3. Razones:

- **Cero dependencias nuevas**: `google/apiclient` arrastra ~15 paquetes transitivos (guzzlehttp, firebase/php-jwt, phpseclib, monolog, etc.) que contaminan `vendor/`. El proyecto base solo tenía `phpmailer/phpmailer` como dependencia directa.
- **Control**: el flujo de Google Calendar que necesitamos es HTTP plano (OAuth2 token endpoint + REST de Calendar API v3 events). 300 líneas de cURL nativo alcanzan para create/update/delete de eventos + refresh token.
- **Consistencia con el proyecto**: el resto del código no usa Guzzle en ningún lado. Meter `google/apiclient` habría roto la uniformidad.
- **Debuggeable**: cada request se puede ver línea por línea, el payload se arma a mano.

**Cuándo reconsiderar:** si Fase 3 requiere batch requests, resumable uploads, push notifications via webhooks con validación de firmas, o streaming de resultados paginados masivos, ahí sí vale la pena instalar la librería oficial. Por ahora: overkill.

## Piezas principales
- **Controlador:** `AgendaController`
    - `index()`: renderiza FullCalendar con filtros y estado de Google Auth
    - `eventsFeed()`: endpoint JSON para FullCalendar `events` callback
    - `create/store/edit/update/eliminar`: ABM de eventos manuales
    - `googleConnect/googleCallback/googleDisconnect`: OAuth2 flow
- **Repositorio:** `AgendaRepository` (CRUD + búsqueda por origen polimórfico)
- **Servicios:**
    - `AgendaProyectorService`: recibe hooks desde otros módulos y hace upsert + push
    - `GoogleOAuthService`: OAuth2 flow completo (auth URL, callback, token refresh, disconnect, criptografía)
    - `GoogleCalendarSyncService`: push create/update/delete a Google Calendar API v3
- **Vistas:**
    - `views/index.php`: FullCalendar 6.1.11 via CDN, dark theme, filtros por origen
    - `views/form.php`: alta/edición de eventos manuales
- **Rutas/Pantallas:** `/mi-empresa/crm/agenda`
- **Tablas:**
    - `crm_agenda_eventos`: eventos locales con `origen_tipo` + `origen_id`
    - `crm_google_auth`: credenciales OAuth2 encriptadas (por usuario o por empresa)
- **Migraciones:**
    - `database/migrations/2026_04_11_create_crm_agenda_eventos.php`
    - `database/migrations/2026_04_11_create_crm_google_auth.php`
    - `database/migrations/2026_04_11_add_agenda_mode_to_empresa_config_crm.php`

## Dependencias directas
- `empresa_config_crm.agenda_google_auth_mode` (ENUM `usuario`|`empresa`, default `usuario`) — resuelve el modo de autenticación.
- `.env`: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` (configurados una sola vez en Google Cloud Console).
- `.env`: `APP_KEY` — clave base para derivar la clave de encriptación de tokens por empresa (junto con `empresa_id`).
- FullCalendar 6.1.11 via CDN (MIT license). Cargado por script tag en `views/index.php`.

## Dependencias indirectas / impacto lateral
- **Hooks en otros módulos**: `PedidoServicioRepository::create/update`, `PresupuestoRepository::create/update` y `TratativaRepository::create/update` invocan al `AgendaProyectorService` después de persistir el registro local. Cualquier cambio en la estructura de esos repositories requiere revisar que el hook siga llamando con el array correcto.
- **Soft-delete cascade**: cuando `PedidoServicioRepository::deleteByIds`, `PresupuestoRepository::deleteByIds` o `TratativaRepository::deleteByIds` se disparan, deberían invocar `AgendaProyectorService::onXxxDeleted` para borrar los eventos proyectados. (TODO Fase 2.1: conectar esto; por ahora solo se proyecta en save, el delete de origen deja el evento huérfano en la agenda hasta que el operador lo limpie manualmente o se cierre con un rescan).

## Seguridad Base (Política de Implementación)
- **Aislamiento Multiempresa**: OBLIGATORIO Y ESTRICTO. Todas las queries del `AgendaRepository` y de `GoogleOAuthService` filtran por `Context::getEmpresaId()`. La tabla `crm_google_auth` tiene UNIQUE (empresa_id, usuario_id) para garantizar una sola conexión por combinación.
- **Permisos / Guards**: rutas protegidas con `$requireCrm` (acceso CRM obligatorio). Cada método de controller inicia con `AuthService::requireLogin()`.
- **Mutación**: todas las acciones destructivas son POST (store, update, eliminar, googleConnect, googleDisconnect). Las consultas de lectura (index, eventsFeed, edit, googleCallback) son GET — nota especial sobre `googleCallback`: es GET porque Google redirige al callback con un GET, y eso es correcto según OAuth2 spec. El code y el state se validan antes de persistir.
- **Validación Server-Side**: `AgendaController::validateRequest()` con `ValidationException`. Valida título obligatorio, fechas parseables, fin >= inicio, color en formato hex válido, estado en ENUM.
- **Escape Seguro (XSS)**: todas las salidas usan `htmlspecialchars()`. Los datos del evento se pasan a FullCalendar via JSON (seguro por serialización).
- **Criptografía de tokens**: `access_token` y `refresh_token` se encriptan con `openssl_encrypt('aes-256-cbc', ...)` usando clave derivada `sha256(APP_KEY + "|empresa=" + empresa_id)`. IV aleatorio por cada encriptación. Si alguien lee la base, no se roba las sesiones de Google.
- **State parameter en OAuth**: el parámetro `state` transporta `{empresa_id, usuario_id, mode, nonce}` codificado en base64url. El nonce evita replays. Se valida en el callback antes de persistir los tokens.
- **Manejo de errores defensivo**: todo `try/catch` en el proyector y el sync service traga excepciones silenciosamente. La proyección a Google NUNCA bloquea el save de PDS/Presupuesto/Tratativa. Los errores se guardan en `crm_agenda_eventos.sync_error` para debugging posterior.
- **No se persisten errores sensibles**: los mensajes de error de Google no contienen tokens, solo descripciones del problema.

## Reglas operativas del módulo
- **Eventos proyectados no se editan desde Agenda**: si el operador hace click en un evento de tipo PDS, Presupuesto o Tratativa en el calendario, FullCalendar redirige al módulo de origen (`/mi-empresa/crm/pedidos-servicio/X/editar`, etc.). El CRM es la fuente de verdad.
- **Click en fecha vacía = crear evento manual**: FullCalendar detecta el dateClick y redirige a `/crear?start=ISO`. La fecha llega prellenada.
- **Sync push-only**: al guardar un evento manual (store/update), se llama a `GoogleCalendarSyncService::push()`. Si la empresa o el usuario tienen una conexión OAuth activa, el evento se replica en Google; si no hay conexión activa, se omite silenciosamente. Esto vale también para los eventos proyectados.
- **Refresh automático de token**: `GoogleOAuthService::getValidAccessToken()` chequea si el token expira en menos de 2 minutos y, si sí, hace un refresh transparente usando el `refresh_token`. Los tokens nuevos se guardan de vuelta encriptados.
- **Modo usuario vs empresa**: el switch en `empresa_config_crm.agenda_google_auth_mode` decide si cada operador conecta su propia cuenta o si hay una sola conexión empresa-wide. El `GoogleOAuthService` resuelve dinámicamente cuál usar sin que el resto del código se entere.
- **Colores por origen**: definidos en `AgendaRepository::DEFAULT_COLORS` (PDS azul, Presupuesto verde, Tratativa amarillo, Llamada violeta, Manual gris). Los eventos manuales permiten color custom vía color picker.
- **Timezone**: `America/Argentina/Buenos_Aires` hardcodeado. Si el proyecto se multi-regionaliza, esto se parametriza por empresa.

## Tipo de cambios permitidos
- Agregar nuevos `origen_tipo` al ENUM (requiere migración ALTER).
- Agregar filtros de usuario / responsable al eventsFeed.
- Sumar vistas adicionales de FullCalendar (timeline, resource view) — requiere plugins premium.
- Implementar el cascade de soft-delete desde los repos de origen al proyector (TODO Fase 2.1).

## Tipo de cambios sensibles
- Alterar el formato de encriptación de tokens: invalidaría todas las sesiones existentes. Requiere migración de re-encriptación.
- Cambiar la derivación de clave (`sha256(APP_KEY + empresa_id)`) sin migrar los tokens: idem.
- Modificar el state parameter sin preservar compatibilidad con callbacks en vuelo: puede invalidar conexiones en progreso.
- Tocar el hook del proyector en los repositories de origen: puede romper la proyección silenciosamente (los errores se tragan).

## Riesgos conocidos
- **Sin cascade de soft-delete desde origen**: si se elimina un PDS/Presupuesto/Tratativa, el evento proyectado queda huérfano en la agenda hasta la próxima vez que se haga un rescan manual. Mitigación futura: conectar el hook `onXxxDeleted` en los repositories de origen (Fase 2.1).
- **Sin retries de push**: si Google Calendar API está caído al momento del push, el evento queda local con `sync_error` seteado pero no se reintenta automáticamente. Hay que exponer un botón "Re-sync" en la UI o un cron de reintento (Fase 3).
- **Concurrencia de refresh_token**: si dos requests paralelos intentan refrescar el mismo token, pueden generar race conditions. Google permite usar el mismo refresh_token varias veces, así que no es catastrófico, pero puede invalidar uno de los access_tokens antes de tiempo. Mitigación: implementar lock a nivel BD o Redis cuando aparezca en producción.
- **APP_KEY rotation**: si el rey cambia `APP_KEY` en `.env`, TODOS los tokens encriptados dejan de poder desencriptarse. El método `decrypt()` lanza excepción y el mensaje dice explícitamente "APP_KEY puede haber cambiado". Mitigación: no rotar APP_KEY sin un plan de migración de tokens.
- **GOOGLE_* sin configurar**: si las variables de entorno no están seteadas, el botón "Conectar" lanza excepción. El error se muestra vía Flash, pero el mensaje es técnico. Mejorar UX futuramente.

## Checklist post-cambio
- [ ] Las 3 migraciones corrieron y las tablas existen.
- [ ] `/mi-empresa/crm/agenda` renderiza FullCalendar sin errores JS.
- [ ] El endpoint `/events` devuelve JSON válido con eventos en rango.
- [ ] Crear un evento manual funciona y aparece en el calendario.
- [ ] Editar un evento manual funciona y persiste cambios.
- [ ] Eliminar un evento manual lo remueve del calendario.
- [ ] Click en un evento proyectado (PDS/Presupuesto/Tratativa) redirige al módulo de origen.
- [ ] Crear un PDS (o Presupuesto o Tratativa con `fecha_cierre_estimado`) hace aparecer un evento automáticamente en la agenda.
- [ ] Flujo OAuth2 con Google Calendar completa el callback y persiste los tokens encriptados (requiere GOOGLE_CLIENT_ID/SECRET/REDIRECT_URI en `.env`).
- [ ] Desconectar Google Calendar elimina la entrada de `crm_google_auth`.
- [ ] Eventos creados con auth activo aparecen en el Google Calendar del usuario.
