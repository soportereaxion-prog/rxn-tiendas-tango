# 2026-04-15 10:05 — Release 1.7.0: Política Anti-Loop + Sistema de Shortcuts (Shift+?)

## Fecha y tema

Release 1.7.0 build 20260415.4 — dos pilares arquitectónicos transversales implementados en una sola sesión.

## Qué se hizo

### Fase 1 — Cerrar el bug del titilado (vista "filtrón")

Contexto previo: en la sesión del 2026-04-14 se había implementado safe mode + admin tool de vistas + hardening de `applyViewConfig` para el bug de una vista guardada cuyo config dejaba el dataset "Pedidos de Servicio" titilando. La sesión 2026-04-15 arrancó con Charly probando la solución y reportando que **aunque safe mode permite entrar al dataset, al seleccionar la vista "filtrón" sigue titilando tan rápido que no se puede abrir DevTools**.

**Decisión**: en vez de seguir persiguiendo la causa exacta del loop (requería reproducir en dev con la vista problemática, cosa que no se podía hacer en la sesión previa), se implementó un **circuit breaker defensivo permanente** que:

- Persiste un contador `{count, firstAt, history[]}` en `sessionStorage` por dataset.
- Si detecta **≥5 redirects en <2s** sobre el mismo dataset, CORTA la cadena de redirects.
- Limpia el `sessionStorage.rxn_live_volatile_X` (posiblemente envenenado) y su propio counter.
- Muestra un banner rojo persistente con:
  - URL actual (la que disparó el último redirect).
  - URL que iba a redirigir (el delta que causa el loop).
  - CTA directo al Safe Mode.
- Con flag `?debug_loop=1` en URL activa modo verbose: `console.warn` en cada ciclo + bloque `<details>` expandido en el banner con historial completo de URLs visitadas + dump JSON del config aplicado.

**Resultado en prod/dev**: la vista "filtrón" ahora muestra el banner, la dataset queda usable, el usuario sale por Safe Mode. El bug SIGUE existiendo a nivel causa raíz (ping-pong cliente↔servidor con los query params `f[razon_social]`), pero el escudo defensivo cierra el impacto al usuario. Charly decidió **dejarlo ahí** — el circuit breaker vale más como policy transversal que perseguir la causa exacta de este caso puntual.

**Archivos tocados**:
- `app/modules/RxnLive/views/dataset.php`:
  - Funciones nuevas `detectAndBreakRedirectLoop(nextUrl, viewId, config)` y `showLoopBrokenBanner(info)` (~180 líneas).
  - Envueltos los 2 redirects de `loadSelectedView()` (uno en bloque reset_view línea ~2056, otro en bloque cambio de vista línea ~2106) para pasar por el circuit breaker antes de ejecutar `window.location.href`.

### Fase 2 — Política Anti-Loop como regla transversal del proyecto

Charly pidió explícitamente que la mecánica defensiva quede **documentada como regla del proyecto**, no solo implementada. Se formaliza en dos lugares:

**`CLAUDE.md` — nueva sección "Principios defensivos transversales"**:

> Evitar SIEMPRE los loops infinitos. Cualquier código que pueda disparar navegación (window.location.href, replaceState, pushState), recarga de datos en cascada, re-render reactivo o cualquier otra acción que pueda auto-invocarse debe tener un circuit breaker explícito: contador por ventana de tiempo (ej: ≥3 ocurrencias en <3s), persistido en sessionStorage cuando corresponda, que al dispararse CORTE la cadena, muestre al usuario un banner con la causa exacta (URL previa vs URL nueva, config aplicado, view_id), y dé una salida clara (link a Safe Mode, reset, etc.). La regla es: si algo puede loopear, asumí que va a loopear en prod y metele freno antes de deployar.

> Diagnóstico persistente > DevTools. Cuando un bug titila/congela la UI tan rápido que el usuario no puede abrir DevTools, el diagnóstico tiene que vivir en el código mismo (banner con info completa + console.error con payload + opcional ?debug_loop=1 o similar flag para verbose). No depender de que el usuario saque screenshots de consola.

**`docs/estado/current.md`** — decisión arquitectónica "Política Anti-Loop Transversal (Circuit Breakers Obligatorios)" con referencia al patrón implementado en `loadSelectedView`.

### Fase 3 — Sistema de Shortcuts Shift+? (estilo GitHub)

Charly pidió un sistema que mejorara la discoverability de atajos de teclado. Propuesta original: presionar `Alt` muestra tooltips con letras sobre cada elemento. Se evaluaron tradeoffs y **se optó por la alternativa `Shift+?`** (convención universal: GitHub, Gmail, Linear, Notion) + modal centralizado con lista completa agrupada por contexto.

**Por qué Shift+? en lugar de Alt-hint**:
- `Alt` colisiona con la menu bar del browser/OS → frágil para accesibilidad.
- Alt-hint requiere anotar cada elemento → alto mantenimiento, fácil de desactualizar.
- Modal centralizado escala mejor: una sola fuente de verdad, cada módulo se registra declarativamente.

**Arquitectura**:
- **`window.RxnShortcuts`** — namespace global con API pública `{register, list, open, close, isOpen}`.
- `register({id, keys, description, group, scope, when, action})` — declaración de un shortcut con metadata para el overlay.
- Matching canónico de combos: normaliza `Ctrl+Enter` / `ctrl+return` / `CTRL+ENTER` al mismo formato interno.
- Scope `"no-input"` respeta focus en `INPUT/TEXTAREA/SELECT`/contentEditable (evita que tipear "?" en buscador abra overlay).
- Dispatcher único `document.addEventListener('keydown', dispatch)` — no hay múltiples listeners compitiendo.

**Visual del overlay** (inspirado en GitHub):
- Backdrop oscuro con blur, panel centrado 820px max, max-height 100vh - 96px.
- Grid 2 columnas desktop (1 columna mobile) con grupos.
- Header con título `<i class="bi bi-keyboard"></i> Atajos de teclado` + botón cerrar.
- Cuerpo: cada grupo con título en caps + lista de items con `descripción ... keys`.
- `<kbd>` estilo GitHub: font monospace, padding 2px 7px, border, inset shadow bottom.
- Footer con hints: "Presioná Shift+? en cualquier pantalla" + "Cerrar con Esc".
- Soporte dark/light via `[data-bs-theme="dark"]`.
- Animaciones sutiles con fallback `prefers-reduced-motion: reduce`.

**Migración de shortcuts existentes**: los 4 shortcuts hardcoded en el archivo previo (F10/Ctrl+Enter guardar, Escape cancelar, Insert/Alt+N nuevo, / F3 Alt+B buscar) + los 2 de modal (Enter aceptar, Escape/ArrowLeft cancelar) se re-registran con la nueva API manteniendo 100% la lógica original (selectores, excepciones, fallbacks, preventDefault).

**Archivos nuevos**:
- `public/css/rxn-shortcuts.css` (~200 líneas).

**Archivos modificados**:
- `public/js/rxn-shortcuts.js` (reescrito completo — ~300 líneas).
- `app/shared/views/admin_layout.php` — línea nueva `<link href="/css/rxn-shortcuts.css?v=<?= time() ?>" rel="stylesheet">`.

## Por qué

- **Anti-loop**: porque el bug del filtrón se descubrió solo cuando Charly fue a prod y reportó "no puedo abrir DevTools porque titila". En futuras sesiones, cualquier loop similar va a tener esta red automática — no dependemos de diagnosticar el bug exacto en cada caso.
- **Shortcuts Shift+?**: mejora de discoverability que llevaba tiempo pidiéndose. El refactor a registry declarativo es precondición para que el sistema crezca sin desactualizarse (cada módulo se registra = el overlay se autogenera).

## Impacto

- **Usuarios**: `Shift+?` disponible en cualquier pantalla del admin (layouts que consumen `admin_layout.php`). El overlay muestra inmediatamente los 6 shortcuts globales ya existentes + el propio shortcut de ayuda. A medida que otros módulos registren sus propios shortcuts, aparecerán automáticamente.
- **Desarrolladores**: nueva API `RxnShortcuts.register({...})` para agregar atajos sin tocar el dispatcher. Scope `no-input` evita conflictos con formularios.
- **Defensa anti-loop**: cero overhead en navegación normal. El contador solo persiste cuando hay redirects rápidos sucesivos. En uso normal (redirect humano, >2s entre navegaciones), el counter se resetea cada vez.
- **Rendimiento**: ambas features son client-side puras. Sin queries adicionales a DB. Sin cambios en el backend.

## Decisiones tomadas

- **Threshold anti-loop = 5 redirects en <2s**: balance entre detectar loops reales (que son en milisegundos) y no afectar navegación humana rápida (min 500ms entre cambios). Documentado como tunable en futuras iteraciones.
- **sessionStorage (no localStorage)**: los contadores son ephemeral por pestaña. No queremos que un loop detectado en una pestaña afecte a otras.
- **`Shift+?` sobre `?` a secas**: evita que tipear "?" en buscador abra el overlay. El `Shift` es natural en teclado ES/US (ya se presiona para generar `?`).
- **Overlay custom, no Bootstrap Modal**: más simple, sin dependencias de Modal state management, animaciones más limpias, no se confunde con los modales propios de confirmación del sistema.
- **Shortcuts existentes preservados 1:1**: ni cambios de keys, ni cambios de semántica. Solo se migran a la API declarativa manteniendo selectores y fallbacks.
- **Group sorting**: "General" y "Ayuda" van primero, resto alfabético. Permite que el shortcut de ayuda (Shift+?) sea lo primero que el usuario ve al abrir el overlay.
- **Bug del filtrón: NO se persigue la causa raíz en esta sesión**: Charly decidió conformarse con el circuit breaker. El bug de ping-pong queda documentado como "resuelto a nivel impacto, no a nivel causa". Si en el futuro aparece otro síntoma del mismo patrón, revisitamos.

## Validación

- Verificación visual en dev (`localhost:9021`):
  - Vista "filtrón" (view_id=12) muestra banner rojo y no titila.
  - Banner incluye URL actual y URL que iba a redirigir (confirma delta `f[razon_social][op/val]`).
  - CTA Safe Mode funciona.
  - El dataset es usable después del banner.
- Shortcuts migrados: no testeado caso por caso (cambio mecánico con selectores idénticos). Pendiente validación humana en la próxima sesión.

## Pendiente

- **Shortcuts Fase 2**: cada módulo (CRM Pedidos, CRM Tratativas, Presupuestos, Pedidos Web, Empresas, Usuarios, Articulos, Clientes, Mail Masivos, RxnLive, etc.) debe declarar sus shortcuts propios con `RxnShortcuts.register` para que aparezcan en el overlay. Se hará iterativo a medida que se toque cada módulo.
- **Shortcuts Fase 3 (opcional)**: evaluar Alt-hint overlay si aparece masa crítica de shortcuts y los usuarios piden exploración visual.
- **Anti-loop en otros flujos**: replicar el patrón de circuit breaker en otros puntos críticos del sistema (envío de PDS a Tango con retry, sync Tango con reenvío masivo, reportes con paginación infinita). Inventariar en próxima iteración.
- **Tests**: no hay tests automatizados del dispatcher de shortcuts ni del circuit breaker. Por ahora, validación manual.

## Env vars nuevas

Ninguna. Features 100% client-side.

## Archivos tocados (resumen)

- `CLAUDE.md` (edit)
- `docs/estado/current.md` (edit)
- `app/config/version.php` (bump a 1.7.0 + entry nueva en history)
- `app/modules/RxnLive/views/dataset.php` (+200 líneas: funciones circuit breaker + envoltorio de 2 redirects)
- `public/js/rxn-shortcuts.js` (reescritura completa ~300 líneas)
- `public/css/rxn-shortcuts.css` (nuevo ~200 líneas)
- `app/shared/views/admin_layout.php` (+1 línea link CSS)
- `docs/logs/2026-04-15_1005_release_1_7_0_antiloop_y_shortcuts.md` (este log)
