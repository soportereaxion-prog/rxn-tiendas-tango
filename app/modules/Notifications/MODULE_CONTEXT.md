# MODULE_CONTEXT — Notifications (sistema global in-app)

## Nivel de criticidad
MEDIO. Es un canal de comunicación al usuario — si se cae, los usuarios no reciben avisos in-app pero NO se bloquea ninguna operación. Los hooks que emiten notifs deben ser tolerantes a fallos (try/catch silencioso).

## Propósito
Sistema centralizado de notificaciones in-app que sirve a TODA la suite (no solo a un módulo). Cualquier módulo puede emitir notificaciones a un usuario destinatario llamando a `App\Core\Services\NotificationService::notify()`. La UI es una campanita en el topbar global con badge de no-leídas + dropdown con las últimas N + página `/notifications` con listado paginado.

## Alcance
**QUÉ HACE:**
- Emisión: cualquier servicio del sistema puede crear notificaciones a usuarios específicos (filtradas por empresa_id + usuario_id).
- Anti-duplicados: parámetro `$dedupeKey` opcional que evita emisiones repetidas en una ventana de 24hs (útil para hooks que se disparan en cada request).
- Lectura: campanita en topbar con badge numérico + dropdown con las últimas 8 + página dedicada con paginación y filtros (todas / no-leídas / leídas).
- Mutación: marcar como leída individual, marcar todas como leídas, soft-delete individual.
- Persistencia: por decisión del rey, las notificaciones NO se borran automáticamente (no hay TTL). El soft-delete existe solo para que el usuario quite ruido visible.

**QUÉ NO HACE (por ahora):**
- NO emite push del browser (Web Push API). Requeriría PWA + service worker + permisos del usuario. Fase futura.
- NO emite mails ni SMS (los mails masivos van por `CrmMailMasivos`, los transaccionales por `MailService`).
- NO permite emisión manual desde admin ("aviso a todos los usuarios"). Solo el sistema (hooks de servicios) emite. Cuando aparezca el caso real de broadcast manual, se suma un endpoint admin.
- NO tiene preferencias por tipo (ej: "silenciar notifs de tipo X"). Se puede agregar leyendo un campo en `usuarios` cuando aparezca la necesidad.
- NO hace polling automático en background. La campanita carga al render de cada página y al abrir el dropdown — sin intervalos.

## Piezas principales
- **Servicio core:** `App\Core\Services\NotificationService` — único punto de emisión y lectura. Vive en `app/core/Services/` porque es transversal a toda la suite (no es un módulo del CRM ni de Tiendas).
- **Controller:** `App\Modules\Notifications\NotificationController` — endpoints HTTP/JSON.
- **Vistas:**
    - `app/modules/Notifications/views/index.php`: página `/notifications` con listado paginado.
    - `app/shared/views/components/notifications_bell.php`: campanita reusable embebida en el topbar global.
- **Frontend:**
    - `public/js/rxn-notifications.js`: hidrata la campanita, dropdown, mark-as-read on-click, mark-all.
    - `public/css/rxn-notifications.css`: estilos del dropdown, badge y lista.
- **Tabla:** `notifications` (empresa_id + usuario_id + type + title + body + link + data JSON + read_at + soft-delete).
- **Migración:** `database/migrations/2026_04_24_00_create_notifications.php`.

## Endpoints
- `GET /notifications` — página con listado paginado (filtro all/unread/read).
- `GET /notifications/feed.json?limit=N` — JSON con últimas N + contador de no-leídas. Lo consume la campanita.
- `POST /notifications/{id}/leer` — marca como leída (CSRF).
- `POST /notifications/marcar-todas-leidas` — marca todas como leídas (CSRF).
- `POST /notifications/{id}/eliminar` — soft-delete (CSRF).

## Cómo emitir una notificación desde otro módulo

```php
use App\Core\Services\NotificationService;

(new NotificationService())->notify(
    empresaId:  $empresaId,
    usuarioId:  $userId,
    type:       'crm_horas.turno_olvidado',  // clave estable
    title:      'Tenés un turno abierto desde ayer',
    body:       'Iniciaste a las 09:00 y no cerraste. ¿Querés cerrarlo ahora?',
    link:       '/mi-empresa/crm/horas',
    data:       ['hora_id' => 42],
    dedupeKey:  'horas.olvido.user42.2026-04-23' // opcional, anti-spam
);
```

Devuelve el ID de la notif creada, o `0` si fue deduplicada.

## Convención de nombres de `type`
`<modulo>.<accion>` en snake_case. Ej:
- `crm_horas.turno_olvidado`
- `crm_horas.no_iniciaste`
- `crm_horas.ajuste_admin`
- `sistema.broadcast`
- `crm_tratativas.proxima_a_vencer` (futuro)

## Seguridad
- **Multi-tenant**: TODAS las queries filtran por `empresa_id + usuario_id`. No hay método "leer todas las del sistema" expuesto.
- **CSRF**: todos los POST validan token via `Controller::verifyCsrfOrAbort()`. El meta `<meta name="csrf-token">` en el `<head>` permite al JS leer el token sin formularios.
- **IDOR**: las queries de mark-read / soft-delete filtran por `id + empresa_id + usuario_id`. Si un usuario manda el ID de otro usuario, la query no afecta filas.
- **XSS**: el `body` se renderiza con `htmlspecialchars()` en la vista y con sanitización JS en el dropdown. Evitar emitir notifs con HTML crudo controlable por usuario.
- **Anti-spam**: `dedupeKey` evita que un mismo hook genere notifs repetidas. La ventana es de 24hs.

## Dependencias
- **`empresas`** (FK con cascade): si se borra una empresa, sus notificaciones se borran físicamente.
- **`usuarios`** (sin FK física): si se borra un usuario, sus notificaciones quedan huérfanas. Mitigación futura: cascade o limpieza por job.
- **`Context::getEmpresaId()`** + `$_SESSION['user_id']`: para resolver el destinatario en lectura.

## Riesgos conocidos
- **Sin TTL**: las notificaciones se acumulan indefinidamente. Si un usuario activo recibe 5 notifs por día, en un año tendrá ~1800 filas. Performance OK con el índice `idx_notif_inbox`. Si crece mucho, sumar paginación más agresiva o purga manual desde admin.
- **Sin retries**: si la inserción falla (DB caída), la notif se pierde. Los emisores deben envolver la llamada en try/catch silencioso para no romper el flujo principal.
- **Sin push real**: el usuario tiene que estar en la app para ver la notif. No hay aviso "push" hasta que abra el browser.

## Checklist post-cambio
- [ ] Migración corre en local sin errores.
- [ ] Campanita aparece en el topbar (verificar tema claro y oscuro).
- [ ] Click en la campanita abre dropdown con "Cargando…" → carga las notifs.
- [ ] Si hay no-leídas, badge rojo con número.
- [ ] Click en notif no-leída la marca como leída + navega al link.
- [ ] "Marcar todas como leídas" funciona desde dropdown y desde página.
- [ ] Página `/notifications` paginada con filtros all/unread/read.
- [ ] Anti-duplicado funciona (emitir 2 veces con el mismo `dedupeKey` solo crea 1).
