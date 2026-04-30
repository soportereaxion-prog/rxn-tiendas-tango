# Release 1.29.1 — Presupuestos P3: autoguardado server-side con 3 estados visibles + Ctrl+S + fix lock post-error

**Fecha**: 2026-04-29 (cierre de sesión #40)
**Versión**: 1.29.1
**Build**: 20260429.3

## Contexto

El release 1.29.0 quedó listo en la sesión anterior pero NO se subió a producción — Charly dijo explícitamente "no voy a producción hasta que tengamos el guardado P3". Esta sesión cierra esa promesa: la P3 está implementada y validada, y la OTA de 1.29.1 incluye TODO lo de 1.29.0 + la P3.

## Qué se hizo

### P3 — Autoguardado server-side de Presupuestos
- El `<form>` de Presupuestos ahora tiene `data-rxn-draft="presupuesto:<id-o-new>"` (cuando no está locked post-Tango).
- Reusa toda la infraestructura del módulo `Drafts` introducida en 1.28.0 — backend cero touch. El whitelist `ALLOWED_MODULOS = ['pds', 'presupuesto']` ya estaba previsto desde el día uno.

### Indicador visible de estado del autoguardado (3 estados semánticos — decisión P0.1=C de Charly)
- 🟢 **Sin cambios** — `currentJson === baselineJson` (lo que está en DB).
- 🟡 **Borrador autoguardado HH:MM · falta Guardar** — `currentJson === lastSavedJson` y ≠ baseline. La red de seguridad está activa.
- 🔴 **Cambios sin guardar** — `currentJson !== lastSavedJson`. Debounce pendiente o save falló.
- ⚠️ **Error al autoguardar** — fetch al endpoint rechazó. Auto-recupera al próximo cambio.

### Hotkey Ctrl+S = Submit del form (decisión P0.2=A)
- Registrada via `RxnShortcuts.register` con `e.preventDefault()` para evitar el "Save Page" del browser.
- Aparece automático en el overlay `Shift+?`.

### Activación opt-in via slot `<span data-rxn-draft-status>` (decisión P0.3=B)
- Charly explícitamente pidió no mezclar PDS por ahora ("PDS es muy interno, lo iteramos en otra").
- El JS solo enciende badge + Ctrl+S si encuentra el slot en la view. PDS no lo tiene → sigue funcionando idéntico.
- El día que se quiera activar en PDS u otros módulos: sumar el slot en la view + el `data-rxn-draft` (que PDS ya tiene). Cero cambios al JS.

### Mejoras al JS de autosave (`rxn-draft-autosave.js`)
- **Baseline ahora se calcula INMEDIATO al `DOMContentLoaded`** (antes era doble RAF). Cualquier mutación posterior del JS del módulo cuenta como dirty correctamente.
- **Poll de 2s para detectar cambios silenciosos**: muchos módulos modifican valores con `el.value = X` sin disparar `input`/`change` (caso típico: `applyClientContext` y `appendItem` en CrmPresupuestos). Sin este poll, los presupuestos nuevos NUNCA armaban el debounce y el draft no se persistía. Con el poll, cada 2s se compara `serializeForm()` con la última observada; si difiere, dispara `trigger()` como si fuera evento real. Costo: ~1ms cada 2s.
- **Evento `rxn-draft-state`** despachado desde el form en cada cambio de estado. Cualquier consumidor externo puede escuchar.

### Bugfix pre-existente — lock de cabecera tras error de validación
- **Síntoma**: cuando el server devolvía errores de validación (ej: "Falta la clasificación") y el form re-renderizaba con renglones cargados, el JS del módulo arrancaba con `if (hasItems()) lockHeader()` y deshabilitaba el picker que el operador necesitaba justo corregir.
- **Fix**: bypass del lock cuando hay banner de errores en el DOM (`#crm-budget-error-banner`). Mismo patrón que el bypass `isFromCopy`.

## Por qué

- Charly NO quería ir a producción con 1.29.0 sin el autoguardado de Presupuestos. La P3 cierra esa promesa.
- El bug del lock post-error era pre-existente desde antes de la sesión, pero salió a la luz cuando Charly probó la P3 — aprovechamos para arreglarlo en el mismo OTA.
- El poll de 2s es defensa en profundidad: módulos legacy que mutan `el.value` sin events son la regla, no la excepción. Sin esto el autosave de Presupuestos nunca se hubiera activado en escenarios reales.

## Impacto

- Operadores ya no pierden trabajo si se cae la conexión, cierran el browser o se vence la sesión a mitad de un presupuesto.
- Productividad: Ctrl+S = guardar sin tocar el mouse.
- Bugfix transversal — `data-rxn-form-intercept` ya no pelea con Ctrl+S ni con el banner de errores.
- PDS sigue intocado (decisión explícita del rey).

## Decisiones tomadas

- **Versionado**: 1.29.0 → 1.29.1 (patch). El 1.29.0 nunca llegó a prod, pero queda como entry histórica en `version.php` y log propio.
- **Seed customer_notes**: dos migraciones en este OTA (1.29.0 y 1.29.1) — la del 1.29.0 fue olvidada en la sesión anterior, la sumamos ahora junto con la del 1.29.1. Patrón idempotente por (title, version_ref).
- **3 estados semánticos** en lugar de "saving" intermedio: Charly priorizó claridad de qué hay en server vs qué falta confirmar, no el detalle técnico del fetch en vuelo.
- **Opt-in via slot**: PDS sin tocar.

## Validación

- ✅ Charly probó local — copia de presupuesto funciona, "Mis borradores" lista correctamente.
- ✅ Bug lockHeader post-error reproducido y arreglado en local con Ctrl+Shift+R.
- ✅ Poll de 2s captura cambios de cliente picker silenciosos (`applyClientContext`).
- ✅ Migraciones idempotentes, ambas seed corridas en local sin error.
- ✅ Service worker / manifest no se tocaron (PWA es proyecto separado, próxima sesión).

## Pendiente (próxima sesión)

- 🔲 **Proyecto PWA — Bloque A**: andamiaje PWA (manifest + SW + registro) + catálogo offline-readable con versionado server-side. Decisiones tomadas:
    - Scope SOLO Presupuestos.
    - Tamaño catálogo: 100-5000 (10k excepcional) → sync completo viable, no delta-sync.
    - Vista mobile dedicada `form_mobile.php` (no responsive del actual).
    - Versionado de catálogo por hash global por empresa, alerta visible cuando hay versión nueva.
    - Sync se apoya en el "Sync Catálogos" server-side existente.
- 🔲 Bloque B: `form_mobile.php` + creación offline con `#TMP-<uuid>` (2 sesiones).
- 🔲 Bloque C: Sync queue + reconciliación al volver online (1 sesión).
- 🔲 Otros pendientes anteriores que siguen vigentes (sync n8n, GC drafts, restaurar descripción original, etc.).

## Files

- `app/config/version.php` — bump 1.29.0 → 1.29.1, build 20260429.3.
- `app/modules/CrmPresupuestos/views/form.php` — `data-rxn-draft` en el form, slot del status badge en el header.
- `public/js/rxn-draft-autosave.js` — extensión opt-in con 3 estados, eventos, Ctrl+S, baseline inmediato + poll 2s.
- `public/js/crm-presupuestos-form.js` — bypass de `lockHeader` cuando hay banner de errores.
- `database/migrations/2026_04_29_06_seed_customer_notes_release_1_29_0.php` — nota release 1.29.0 (versionado, lock, descripciones).
- `database/migrations/2026_04_29_07_seed_customer_notes_release_1_29_1.php` — nota release 1.29.1 (autoguardado P3 + Ctrl+S).
