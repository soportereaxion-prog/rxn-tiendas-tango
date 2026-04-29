# Release 1.28.0 — Recuperación de sesión + autoguardado de borradores

**Fecha:** 2026-04-29
**Iteración:** #38
**Build:** 20260429.1
**Tema:** Resolver pérdida de trabajo cuando vence la sesión en formularios largos.

## Contexto

Charly reportó un dolor recurrente: estar cargando un PDS (o cualquier formulario largo) y que se le venza la sesión. Antes de este release pasaban tres cosas malas en cadena:

1. No había aviso previo — el operador descubría la expiración solo al apretar "Guardar" y caer al login.
2. El POST con sesión muerta perdía toda la data del form.
3. Post-login el operador caía al dashboard, no al lugar donde estaba — perdía además el contexto.

Charly aclaró que en producción la cookie/sesión muere mucho antes de las 6h idle declaradas (cerca de la hora). Eso refuerza la importancia de los drafts: aunque el aviso preventivo no llegue a aparecer, el draft está en DB y se recupera al volver al form.

## Decisión arquitectónica

Combo A + B definido en sesión:

- **A (Return-URL + aviso preventivo)**: cubre los casos donde el operador todavía tiene la pestaña abierta y puede actuar.
- **B (Autoguardado server-side de drafts)**: red de seguridad para los casos donde A no llega — browser cerrado, tab dormida, corte de luz, crash.

Se descartó la opción C (re-login en modal sin recargar) por costo/beneficio: A+B cubre el 90% del dolor con mucho menos código. C queda como posible iteración futura si todavía duele.

## Qué se hizo

### Fase 1 — Aviso preventivo + Return-URL

- Endpoint nuevo `GET /api/internal/session/heartbeat` (`app/modules/Auth/SessionController.php`) que devuelve `{remaining_idle, remaining_absolute}` o 401 si la sesión murió. Los timeouts se mantienen sincronizados con `App.php` (6h idle / 12h absoluto).
- JS nuevo `public/js/rxn-session-keeper.js` cargado global en `admin_layout.php`. Pollea cada 60s. Banner amarillo a los 15min (countdown en vivo, vira a rojo a 2min). Botón "Extender ahora" hace un hit cualquiera al server, renueva `last_activity` naturalmente. Si llega 401 → redirige a `/login?next=<url-actual>`.
- `AuthService::requireLogin()` ahora arma `/login?next=<url>` con whitelist anti open-redirect (`isSafeNext`: path absoluto, prohíbe `//`, `\\`, `://`). Captura `REQUEST_URI` solo para GET — los POST con sesión muerta no pueden replayearse, mejor caer al dashboard que a una URL que va a fallar.
- `AuthController::showLogin/processLogin` leen `next` (query y POST), lo sanean con `resolveNext()` y redirigen ahí post-login. Hidden input `next` agregado a `login.php` para que sobreviva al POST.

### Fase 2 — Drafts en PDS

- Migración `2026_04_29_00_create_drafts_table.php` — tabla `drafts` con UNIQUE `(user_id, empresa_id, modulo, ref_key)`, payload_json LONGTEXT, índices para panel.
- `DraftsRepository` — CRUD + `findAllByUser` (sin payload, con `OCTET_LENGTH` para tamaño).
- `DraftsController` — endpoints REST `/api/internal/drafts/{get,save,discard}` (CSRF en save y discard, whitelist módulos, regex de ref, cap 1MB).
- JS `public/js/rxn-draft-autosave.js` — auto-init sobre `<form data-rxn-draft="modulo:ref">`. Banner inline al cargar si hay draft. Debounce 5s. Discard al submit via `navigator.sendBeacon` (sobrevive a la navegación). Excluye file y password del serializado. Compatible con Flatpickr via `RxnDateTime.setValue`.
- `app/modules/CrmPedidosServicio/views/form.php` — `<form>` marcado con `data-rxn-draft="pds:<id-o-new>"`.

### Fase 3 — Panel "Mis borradores"

- `GET /mi-perfil/borradores` (`DraftsController::index`) — vista HTML que lista todos los drafts del usuario en la empresa actual.
- `app/modules/Drafts/views/index.php` — tabla con módulo + ref + última edición + tamaño + [Retomar] / [Descartar].
- Botón "Mis borradores" en header de Mi Perfil B2B (`mi_perfil.php`).

### Fase 4 — Documentación

- `app/modules/Drafts/MODULE_CONTEXT.md` — canónico del módulo. Incluye procedimiento de 3 pasos para enchufar un formulario nuevo (whitelist + helpers + atributo `data-rxn-draft`).
- `app/modules/CrmPedidosServicio/MODULE_CONTEXT.md` — entry agregada en "Dependencias directas" con cross-link al de Drafts.

## Por qué

- El return-URL + drafts es un patrón estándar en apps SaaS de buena calidad. Era una deuda técnica visible — Charly lo planteó después de varias sesiones perdiendo trabajo.
- La whitelist `isSafeNext` no es paranoia: sin ella el `next` sería un open redirect explotable (atacante manda `/login?next=https://malo.com`, el usuario se loguea y el server lo redirige al sitio malicioso).
- El autoguardado server-side (no localStorage) habilita el caso cross-device: si el operador empezó a cargar un PDS desde la PC y se le murió, puede retomarlo desde el celular logueado con el mismo user.
- El panel "Mis borradores" elimina la dependencia de "acordarse" qué PDS estaba a medio cargar.

## Impacto

- **Pérdida de trabajo en sesiones expiradas: cero** (en el peor caso se pierden los últimos 5s tipeados — debounce del autosave).
- **Operadores en producción**: el banner preventivo + el aviso de drafts les da una experiencia mucho menos frustrante. El "vence al toque a la hora" en prod queda mitigado de raíz.
- **Carga sobre DB**: 1 INSERT/UPDATE cada 5s por form abierto. Despreciable comparado con el tráfico de cualquier endpoint del CRM.
- **Carga sobre red**: 1 request HEAD-equivalente cada 60s por tab abierta. Despreciable.

## Decisiones tomadas

- **Last-write-wins entre tabs**: si el mismo PDS está abierto en 2 pestañas, gana el último save. Sin merge — complicaría la lógica para un caso de uso muy raro.
- **Sin TTL automático**: los drafts no expiran. Si en el futuro vemos basura acumulada, sumar job que borre `WHERE updated_at < NOW() - INTERVAL 30 DAY`.
- **Sin rate limiting en `save`**: por ahora confiamos en el cap de 1MB + UNIQUE por (user, empresa, modulo, ref). Si vemos abuso, sumar `RateLimiter` por `user_id`.
- **Discard al submit (no al submit OK)**: si el server rechaza el guardado real (validación), el draft queda descartado prematuramente. Se prefirió eso a tener un draft viejo conviviendo con el form actual — el usuario ve el error inline y mantiene los valores en pantalla.

## Validación

- Sintaxis: `php -l` OK en todos los archivos PHP modificados.
- Migración: ejecutada en local con `tools/run_migrations.php` — OK.
- Test manual:
  - PDS new → tipear data → ver POST a `/api/internal/drafts/save` en Network. ✅
  - Cerrar tab → reabrir PDS new → banner "Tenés un borrador del [fecha]". ✅ (capturado por Charly)
  - Panel `/mi-perfil/borradores` → lista el draft, links a Retomar / Descartar funcionando.

## Pendiente / Próxima iteración

- **Sumar drafts a CrmPresupuestos** — el otro form largo donde duele lo mismo. Cuando se entre a iterar Presupuestos: agregar `'presupuesto'` al whitelist de `DraftsController::ALLOWED_MODULOS` (ya está), agregar `data-rxn-draft="presupuesto:<id-o-new>"` al form. Nada más. Memoria saved con topic_key `rxn_suite/drafts/presupuestos-pendiente`.
- **Investigar la cookie corta en prod**: Charly mencionó que la sesión muere "al toque a la hora". App.php declara 6h idle / 12h absoluto pero algo en prod la corta antes. Posibles causas: PHP `session.gc_maxlifetime` del server, Plesk, OPcache, cookie domain. No es bloqueante (los drafts cubren el caso), pero vale la pena diagnosticar para que el aviso preventivo sea efectivo.
- **(Opcional, si todavía duele)**: opción C — re-login en modal sin recargar, reintentar el submit automáticamente.
