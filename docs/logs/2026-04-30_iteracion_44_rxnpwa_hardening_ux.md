# Iteración 44 — RXN PWA hardening + UX + dev bypass (release 1.39.0)

**Fecha**: 2026-04-30
**Build**: 20260430.13
**Tema**: Cerrar pendientes Fase 3 + 3 mejoras UX pedidas por Charly + fix crítico para pruebas locales sin SSL.

## Qué se hizo

### Hardening Fase 3 (pendientes de la iteración 43)

- **CSRF en endpoints POST de la PWA**:
  - `RxnPwaController::checkCsrf()` valida `HTTP_X_CSRF_TOKEN` contra `$_SESSION['csrf_token']` via `CsrfHelper::validate()`. Si falla → 403 con error accionable.
  - El cliente JS lee `<meta name="csrf-token">` (ya estaba en shell y form) y manda `X-CSRF-Token` en cada `fetch` POST.
  - Aplicado a: `/api/rxnpwa/presupuestos/sync`, `/api/rxnpwa/presupuestos/{id}/attachments`, `/api/rxnpwa/presupuestos/{id}/emit-tango`.
- **Rate limiting** (FileCache-backed via `App\Core\RateLimiter`):
  - `sync` 60/min por (user, empresa). Permite drenar 5 drafts con backoff sin trabar.
  - `uploadAttachment` 120/min por (user, empresa). Cada draft puede tener N adjuntos.
  - `emit-tango` 20/min por (user, empresa). Más restrictivo porque pega contra Tango.
  - Si excede → 429 + header `Retry-After` + body `{retry_after: N}`. El cliente lo parsea y muestra mensaje.
- **GC de drafts ya entregados al server**:
  - `RxnPwaDraftsStore.garbageCollectSynced(daysOld=7)`: borra drafts con `status='synced'|'emitted'` y `updated_at` más viejo que N días + sus attachments. Server-side queda intacto.
  - `RxnPwaDraftsStore.purgeAllSynced()`: purga inmediata sin importar edad.
  - Shell hace GC automático al `DOMContentLoaded` (best-effort, falla silente).
  - Sección "Cola de envío" suma botón "Limpiar N enviados del celu" cuando hay synced > 0.

### UX (pedidos del rey en la sesión)

- **Card "PWA — Presupuestos Mobile" del dashboard CRM oculta en desktop**:
  - `crm_dashboard.php` detecta UA server-side con la misma regex que el banner azul (`Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile`). Si NO es mobile, `unset($defaultCards['pwa_presupuestos'])`.
  - El banner azul de invitación sigue apareciendo en mobile (sin cambios).
- **Header del shell PWA — 2 cajas en grid**:
  - Antes: 1 alert "Comprobando catálogo" arriba + 1 card sólo-botón abajo. Resultado: mucho vertical desperdiciado.
  - Ahora: `div.row.g-2` con `col-7` (estado del catálogo) + `col-5` (botón Sincronizar). Eliminada la card de abajo.
  - `renderBadge()` en `rxnpwa-register.js` reescrito a markup compacto sin alert: título coloreado / items+tamaño / fecha. Encaja en la card chica.

### Fix crítico — GPS local sin SSL

**Problema**: cuando Charly probaba la PWA desde el celu apuntando a `http://192.168.10.10:9021` (LAN sin SSL), el gate de GPS quedaba trancado en "GPS desactivado / Denegaste el permiso de ubicación" aunque el GPS del celu estuviera ON.

**Causa raíz**: `navigator.geolocation` está bloqueado en contextos NO seguros (HTTP plano, excepto `localhost`/`127.0.0.1`). El browser ni siquiera muestra prompt — devuelve `PERMISSION_DENIED` directo. Esto es por diseño de la spec de Geolocation API. No es un bug del GPS — es seguridad del browser.

**Fix**:
- `rxnpwa-geo-gate.js` ahora detecta `window.isSecureContext === false` ANTES de llamar a `navigator.geolocation`.
- Si insecure context → muestra overlay específico con título "Servidor sin HTTPS" + botón warning "Activar GPS simulado (sólo dev)".
- Al aceptar el bypass, se setea `currentGeo = { lat: -34.6037, lng: -58.3816, accuracy: 50, source: 'dev_mock', captured_at: now }` (Obelisco BA).
- Banner amarillo sticky en el top de la PWA mientras el modo dev esté activo, recordando que la geo es ficticia.
- `rxnpwa-sync-queue.js` y `rxnpwa-form.js` aceptan `'dev_mock'` como source válido SÓLO cuando `isInsecureContext()` es true. En HTTPS, el gate jamás permite asignar `dev_mock` → la regla productiva (gps/wifi obligatorios) queda intacta.
- `GeoTrackingService::reportarPosicionBrowser()` agrega `'dev_mock'` a `$allowedSources` para que el server persista sin coercionar a 'error'. En logs productivos no debería aparecer; si aparece, indica un anómalia rastreable.

## Por qué

- **CSRF + rate limit**: hardening que había quedado en pendientes Fase 3. Los 3 endpoints POST son superficies de ataque clásicas (CSRF para abusar de sesiones, rate limit para evitar floods). Con esto la PWA queda al mismo nivel de seguridad transversal que el resto de la suite.
- **GC**: sin GC, IndexedDB del celu acumula indefinidamente drafts ya enviados al server. En equipos de campo con uso intenso esto crece sin control.
- **Card desktop**: el banner azul ya invita a abrir la PWA cuando se detecta mobile. La card adicional confunde en desktop ("¿la abro o no?").
- **Header rediseñado**: feedback directo de Charly viendo la PWA en celu real — "muy grandes" las cajas. Compactar en grid recupera vertical.
- **Bypass dev sin SSL**: bloqueante para hacer pruebas en LAN antes de subir a prod. Open Server local NO tiene SSL configurado y poner certificados auto-firmados rompe el SW. La salida limpia es asumir el escenario y darle un opt-in explícito que NO afecta producción.

## Decisiones P0 (con Charly)

1. **El bypass dev se ofrece SÓLO en contexto inseguro**. En HTTPS el gate sigue siendo bloqueante real. No hay flag para forzarlo en prod — la detección es automática y sin override.
2. **`source='dev_mock'` viaja al server y se persiste en `rxn_geo_eventos.accuracy_source`** sin coercionar. Si en prod aparece, es señal de que alguien usó la PWA en HTTP (anómalo, rastreable).
3. **GC default 7 días**. Más conservador que 30 (no acumula tanto) y más relajado que 1 (no borra historia útil del día anterior). Si el operador quiere purgar todo ya, hay botón explícito.
4. **Rate limits por (user, empresa)** — no por IP. La IP de un celu puede compartirse en la oficina; lo que importa es la combinación de identidad + tenant.
5. **Subtítulo del header del PWA queda igual** ("Presupuestos") — la grid del header del shell se aplica al main, no al header sticky.

## Validación

- ✅ Lint PHP OK en los 4 archivos backend tocados (`RxnPwaController`, `GeoTrackingService`, `crm_dashboard.php`, `presupuestos_shell.php`).
- ✅ Lint PHP OK en `app/config/version.php`.
- 🔲 Smoke test mobile real: probar desde el celu con GPS ON (debe entrar normal) y con GPS OFF (debe mostrar overlay original) en `https://suite.reaxionsoluciones.com.ar`.
- 🔲 Smoke test pruebas locales en LAN: abrir desde celu apuntando a `http://192.168.10.10:9021` (debe ofrecer bypass dev).
- 🔲 Smoke test rate limit: emitir 21 veces a Tango en 1 min → la 21° debe devolver 429.
- 🔲 Smoke test CSRF: forzar sesión sin token (clear cookies) y enviar request → 403.
- 🔲 Smoke test GC: marcar `updated_at` de un draft synced con fecha vieja, recargar shell → debe desaparecer.

## Adicional — release 1.40.0 (mismo día, después de probar 1.39.0 en celu)

Charly cargó pruebas reales en mobile y detectó 3 mejoras adicionales:

### Gate depósito obligatorio antes de agregar renglones

- Antes se podía abrir el modal de agregar artículo sin depósito en cabecera, lo que dejaba renglones sin contexto de stock y precios sin lista resuelta.
- `openRenglonModal()` valida primero `draft.cabecera.deposito_codigo`. Si está vacío:
  - `showStatus('error', ...)` con mensaje claro.
  - El select de depósito recibe `.is-invalid` + foco + `scrollIntoView({behavior:'smooth'})`.
  - Return — el modal NO abre.
- Label del select lleva `<span class="text-danger">*</span>` y atributo `required`. Visualmente alineado con Lista de precio y Clasificación.

### Edición de renglones

- Cada renglón en `renderRenglones()` muestra ahora 2 botones: lápiz (azul outline) + trash (rojo outline).
- `data-rxnpwa-renglon-edit="${idx}"` dispara `openRenglonModal(idx)`.
- `openRenglonModal(idx)` pre-carga el modal con artículo + cantidad + precio + descuento del renglón existente. Título cambia a "Editar renglón", botón confirmar a "Guardar cambios".
- `confirmRenglon()` detecta modo edit y hace update in-place preservando `row_uuid` (idempotencia del sync server-side).
- Si el artículo del renglón ya no está en catálogo offline (catálogo resincronizado), se reconstruye objeto mínimo desde el draft. La edición no rompe.

### Botón pantalla completa

- Nuevo botón en el header del form, a la izquierda del Guardar (`bi-fullscreen` / `bi-fullscreen-exit` según estado).
- `toggleFullscreen()` usa Fullscreen API estándar (`requestFullscreen`/`exitFullscreen`).
- Fallback iOS Safari: la API no funciona en iOS para documentos arbitrarios. Cuando `requestFullscreen` no existe (o falla), togglea clase `body.rxnpwa-faux-fullscreen` que oculta el `.rxnpwa-header` sticky con CSS — recupera vertical equivalente.
- Listener `fullscreenchange` (+ `webkitfullscreenchange`) refresca el ícono cuando el operador sale con ESC sin tocar el botón.

### Por qué

Charly probó en celu real con presupuestos de varios renglones — sin edición había que borrar+reagregar para corregir cualquier dato del renglón, y el header sticky comía espacio en pantallas chicas. El gate de depósito surgió porque al cargar un test sin elegir depósito el sistema dejaba avanzar pero los renglones quedaban sin stock visible — bug silencioso.

### Validación

- ✅ Lint PHP OK en `presupuesto_form.php` y `version.php`.
- 🔲 Smoke mobile: agregar sin depósito → debe bloquear con mensaje + foco. Editar renglón → debe pre-cargar datos. Tap fullscreen → debe entrar a FS o hacer faux-fs en iOS.

## Adicional — release 1.41.0 (web mobile-friendly, mismo día)

Charly probó la suite desde el celu (no PWA, web normal) y vio 2 fricciones:

### Scroll vertical pisado por el Sortable del dashboard

- Síntoma: tocar el borde derecho para scrollear movía las cards de los dashboards.
- Causa: `Sortable.create(grid, { handle: '.rxn-module-card' })` activa drag al primer touch sobre la card. Cualquier swipe vertical iniciado encima de una card se interpreta como drag y bloquea el scroll.
- Fix: en los 3 dashboards (`crm_dashboard.php`, `home.php`, `tenant_dashboard.php`) sumar 3 opciones:
  - `delay: 250` — hold time antes de iniciar drag.
  - `delayOnTouchOnly: true` — el delay aplica SOLO a touch. En desktop con mouse no hay cambio.
  - `touchStartThreshold: 5` — pequeños movimientos (5px) durante el delay no se confunden con drag.
- Resultado: scroll vertical normal funciona sin pisarse con el sortable; reordenar requiere long-press explícito de 250ms.

### Bitácora minimizada por default en mobile

- Síntoma: el panel "Bitácora interna - Modulo X" (admin-only) ocupaba media pantalla en celu y tapaba el contenido del módulo.
- Causa: `$moduleNotesShouldOpen` se calculaba sólo por flash session o count de notas — ignoraba viewport.
- Fix: detección server-side de UA mobile (misma regex que el banner PWA y la card hide del dashboard). Si mobile → `$moduleNotesShouldOpen = false` siempre. En desktop conserva la lógica original.
- Resultado: en celu el panel aparece minimizado (launcher chico abajo a la derecha). El admin lo expande con tap si quiere consultarlo, queda expandido durante esa pantalla.

### Validación

- ✅ Lint PHP OK en `version.php`, `module_notes_panel.php`, `crm_dashboard.php`, `home.php`, `tenant_dashboard.php`.
- 🔲 Smoke desktop: drag de cards debe seguir funcionando inmediato (sin delay aparente).
- 🔲 Smoke mobile: scroll vertical en dashboards no mueve cards. Long-press 250ms sí inicia drag.
- 🔲 Smoke mobile: entrar a un módulo CRM con bitácora — debe aparecer el launcher chico, no el panel grande.

## Adicional — release 1.42.0 (fullscreen global persistente, mismo día)

Charly notó que el toggle de pantalla completa solo estaba en el form de presupuestos PWA, no en el shell ni en el backoffice CRM mobile. Pidió:
- Botón fullscreen en TODAS las superficies mobile (backoffice + shell PWA + form PWA).
- Persistencia: si lo activé en una pantalla, al ir a otra debe seguir activo.

### Helper global

- `public/js/rxn-fullscreen.js`: API `RxnFullscreen.{toggle, isActive, getPref, setPref}`.
- Persistencia: `localStorage[rxn_fullscreen_pref]` = `'on'` / `'off'`.
- Bootstrap: al cargar cualquier página, si `pref === 'on'` aplica modo faux (`body.rxn-faux-fullscreen`). No invoca la Fullscreen API real porque eso requiere user gesture, pero el faux replica visualmente el efecto ocultando los headers sticky.
- Toggle por click: cualquier botón con `[data-rxn-fullscreen-toggle]` actúa como switch. Al click intenta API real, si falla cae a faux. La pref se guarda antes del request a la API por si redirige.
- Auto-refresh de íconos: cuando cambia el estado (click, ESC, fullscreenchange events), todos los botones bound actualizan su ícono y title automático. Default: `bi-fullscreen` ↔ `bi-fullscreen-exit`.

### CSS global `rxn-fullscreen.css`

```css
body.rxn-faux-fullscreen .rxn-backoffice-topbar,
body.rxn-faux-fullscreen .rxnpwa-header,
body.rxn-faux-fullscreen #rxn-page-header { display: none !important; }
```

### Wiring

- `admin_layout.php`: carga `rxn-fullscreen.css` en `<head>` y `rxn-fullscreen.js` con los demás scripts globales (antes de `rxn-shortcuts`).
- `backoffice_user_banner.php`: clase `rxn-backoffice-topbar` al wrapper. Botón `btn-link` con `bi-fullscreen` + `data-rxn-fullscreen-toggle` visible solo `d-lg-none`, antes del `notifications_bell`.
- `presupuestos_shell.php`: botón `data-rxn-fullscreen-toggle` en el header al lado del "Volver al backoffice". Carga del helper antes del `geo-gate`.
- `presupuesto_form.php`: el botón existente migra a `data-rxn-fullscreen-toggle`. Helper cargado.
- `rxnpwa-form.js`: eliminada la sección local `toggleFullscreen` / `updateFullscreenIcon` / `isFullscreen`. La maneja 100% el helper global.
- `rxnpwa.css`: removidas las reglas locales `.rxnpwa-faux-fullscreen` (reemplazadas por la global).

### Por qué

Charly pidió "que el toggle persista al ir entre módulos así no hay que andar tildando cada vez". La forma natural de lograrlo es centralizar el comportamiento en un solo helper que: (1) recuerda la pref en localStorage, (2) la aplica al bootstrap de cualquier página que cargue el helper. La API real y el faux mode coexisten — la real cuando hay user gesture (más prolijo, esconde la URL bar también), el faux como fallback / persistencia.

### Validación

- ✅ Lint PHP OK en los 5 archivos PHP tocados.
- ✅ Lint JS OK en `rxn-fullscreen.js` y `rxnpwa-form.js`.
- 🔲 Smoke desktop CRM: el botón fullscreen NO debe aparecer (es `d-lg-none`).
- 🔲 Smoke mobile CRM: tap en el botón → entra. Navegar a otro módulo → sigue oculto el header. Tap de nuevo → vuelve normal y persiste apagado al navegar.
- 🔲 Smoke shell PWA: tap fullscreen → entra. Tap "Nuevo presupuesto" → en el form sigue activo.
- 🔲 Smoke form PWA: el botón funciona como antes pero coordinado con el resto.

## Pendiente

- **CRÍTICO para próxima OTA**: excluir `app/modules/*/MODULE_CONTEXT.md` del ZIP del release. Son docs internas (decisiones, gotchas, vocabulario) que NO deben viajar a producción. Ajuste necesario en `tools/build_update_zip.php` o en `App\Core\ReleaseBuilder`. Guardado en memoria persistente bajo `release/builder-exclude-module-context`.
- Íconos finales del shell — esperando que Charly drope `public/icons/rxnpwa-source.png` con el arte definitivo, después correr `tools/generate_rxnpwa_icons_from_source.php`.
- Updates de drafts ya `synced`: hoy editable pero no hay flujo "re-sync con cambios". A definir cuando aparezca el caso real.
