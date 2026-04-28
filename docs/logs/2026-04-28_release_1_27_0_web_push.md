# Release 1.27.0 — Web Push (notificaciones nativas del navegador)

**Fecha**: 2026-04-28
**Versión**: 1.27.0
**Build**: 20260428.4

## Tema

Cierre del trío de notificaciones (1.25 in-app + 1.26 tick global con n8n + 1.27
Web Push). El usuario ahora puede optar por recibir popups nativos del SO
incluso con la pestaña cerrada. Off por defecto, opt-in explícito desde Mi
Perfil.

## Qué se hizo

### 1. Vendor

`composer require minishlink/web-push:^10` — trajo guzzle, web-token/jwt-library,
psr-*, brick/math, symfony/polyfill-php83, spomky-labs/* (15 packages nuevos).

**Saneamiento crítico (incidente 2026-04-28)**: el require borró
`dompdf/dompdf v3.1.5` porque dompdf nunca estaba declarado en composer.json
(solo phpmailer estaba en require). Restaurado con
`composer require dompdf/dompdf:^3.1`. Documentado el antipatrón en CLAUDE.md
+ MODULE_CONTEXT de PrintForms para que no vuelva a pasar.

### 2. Backend

- `app/core/Services/WebPushService.php` — emisión, subscribe, unsubscribe,
  cleanup automático (410 → borra, 5+ fallos → disabled_at).
- `NotificationService::notify()` ahora dispara push fire-and-forget al final
  del flujo. Push es complemento, no sustituto: si falla, la in-app sigue OK.
- `WebPushController` con `subscribe`, `unsubscribe`, `status`. CSRF + sesión.
- 3 rutas nuevas en `routes.php`.

### 3. DB

- `web_push_subscriptions`: `(empresa_id, usuario_id, endpoint, p256dh, auth,
  user_agent, created_at, last_push_at, failed_attempts, disabled_at)`. UNIQUE
  por endpoint (un usuario puede tener N subs).

### 4. Frontend

- `public/sw.js` — Service Worker. Recibe push, muestra notificación nativa,
  click abre link y foca pestaña existente.
- `public/js/rxn-web-push.js` — `window.RxnWebPush` con
  `isSupported/isIos/permissionState/getStatus/enable/disable`. NUNCA pide
  permission sin click explícito.
- `mi_perfil.php` — card nuevo "Notificaciones del navegador" con tri-state
  (off / on / blocked) + disclaimer iOS.

### 5. Env vars

3 nuevas en `.env` (van a prod):

```
VAPID_PUBLIC_KEY=BD33itmyNFn8V33iUlG9yurmLP0jHCTAq8ddApVKPI4f4ioarnwm5p49-wptB5RUIcFfRRbcQ7DhNXaXTEpGVXg
VAPID_PRIVATE_KEY=a04ZyqiGBiJG6b3-5C7pojFKU5ir4ru0an7GBxt91wg
VAPID_SUBJECT=mailto:soporte@reaxion.com.ar
```

**LAS MISMAS** claves van al `.env` local Y al de producción. NO rotar — los
browsers suscritos pierden la sub si se cambian.

### 6. Tooling

- `tools/generate_vapid_keys.php` — generador CLI. UNA SOLA VEZ por
  instalación.
- `tools/smoke_web_push.php` — verifica VAPID + tabla + `sendToUser` sin subs.

## Runbook post-OTA

1. Subir el ZIP a Plesk → corren las 2 migraciones nuevas (`05_create_web_push_subscriptions` y `06_seed_customer_notes_1_27_0`).
2. **Agregar las 3 env vars VAPID al `.env` de producción** (mismas que local — copiar literal del `.env` de dev).
3. Verificar con `php tools/smoke_web_push.php` que `WebPushService configured: YES`.
4. Probar flujo end-to-end: ir a Mi Perfil con un usuario en Chrome/Firefox → "Activar notificaciones" → aceptar prompt → crear una nota con recordatorio en +1 min → cerrar pestaña → esperar el push.

## Decisiones de diseño

- **Off por default + opt-in explícito**: Charly lo confirmó. Prompts no
  solicitados son castigados con "Block" persistente — peor escenario.
- **Push como complemento, no reemplazo**: la notif in-app siempre se crea, el
  push es además. Si VAPID falla o no hay subs, no se pierde nada.
- **iOS pospuesto**: Safari iOS solo soporta Web Push si la app está instalada
  como PWA. Charly va a meter el `manifest.json` cuando ataque presupuestos.
  El card detecta iOS y muestra disclaimer claro.

## Pendiente

- Iconografía del navegador: `/img/rxn-icon-192.png` no existe todavía. La
  notif sale igual con el icon default del SO. Cuando se haga el branding PWA
  (release de presupuestos), subir `/assets/img/rxn-icon-192.png` y ajustar el
  fallback en `sw.js`.
- Opción futura: reportes en Mi Perfil de "tu última push enviada hace X" para
  diagnosticar si algo se rompió silenciosamente.
