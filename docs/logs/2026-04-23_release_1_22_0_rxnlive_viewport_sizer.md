# Release 1.22.0 — RXN Live: encuadre pleno del viewport en vistas analíticas

**Fecha:** 2026-04-23
**Build:** 20260423.4
**Rama:** main

---

## Contexto

Charly reportó (por segunda sesión consecutiva) que en **PDS (Pedidos de Servicio)** dentro de RxnLive la grilla no quedaba encuadrada: el contenido de la tabla se derramaba más abajo del borde inferior de la ventana, ocultando el footer de paginación (`Mostrar 50 / N registros`) y el footer global de la app. En **Ventas Histórico** el problema era invisible. Al cerrar sesiones anteriores el bug no se había liberado.

El release 1.21.0 había intentado un fix (item #31: agregar `overflow-y:auto` al `.tab-pane.table-responsive`) que mejoraba el scroll horizontal pero NO resolvía el encuadre vertical — por eso 1.21.0 había quedado **SIN OTA** esperando esta iteración.

En esta sesión Charly también dejó explícito que RxnLive es la herramienta analítica más usada de la suite ("*nuestro mi PowerBI*") y pidió que el fix quede "ultra registrado" — por eso el bump va por minor (no patch) y el MODULE_CONTEXT incorpora historia evolutiva completa.

---

## Qué se hizo

### Fase 1 — Diagnóstico

- Se descubrió que el tab-pane `#plana` tenía `max-height: 70vh` inline hardcodeado. En una notebook típica (~900px de viewport) eso da ~630px, pero el topbar + header del módulo + card de filtros + tabs + card-footer de paginación + footer global ocupaban MÁS del 30vh restante → el pane desbordaba.
- En Ventas Histórico (14 filas) el contenido intrínseco nunca llenaba los 70vh, así que el bug era invisible. En PDS (45 filas) sí lo llenaba y derramaba.
- También se verificó que el Pivot Result Container (`#pivotResultContainer`) tenía el mismo bug.

### Fase 2 — Primer intento (sizer sobre el pane, v1-v2)

- **v1**: se reemplazó el `70vh` por un sizer JS que medía `innerHeight − pane.top − cardFooter.offsetHeight − 16px`. El tab-pane quedaba acotado y mostraba el card-footer de paginación. **Falló**: el footer global de la app ("Re@xion Soluciones" + datos de contacto + márgenes del `<main class="mb-4">`) quedaba fuera del viewport igual, porque el cálculo no lo contemplaba.
- **v2**: se agregó un pase correctivo con doble `requestAnimationFrame` que medía `documentElement.scrollHeight − clientHeight` y restaba ese overflow al max-height del pane. El body ya no scrolleaba, pero aparecía un **hueco negro enorme dentro del card** de la tabla: el `.card.h-100` se estiraba al col-lg-8 (que tomaba el alto del chart-col por `align-items:stretch` del row), pero el tab-pane interno estaba limitado por max-height más chico → hueco entre el final del tab-pane y el borde del card.

### Fase 3 — Solución definitiva (sizer sobre el card + flex-column, v3)

El error conceptual de v1-v2 era **limitar el tab-pane interno** cuando lo correcto es **limitar el card completo**. Nuevo approach:

1. **CSS**: convertir el `#tableSectionCol > .card` en `display: flex; flex-direction: column`. Dentro, la `.card-body.tab-content` es `flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; overflow: hidden`. El `.tab-pane.active` es `flex: 1 1 auto; min-height: 0` y la clase `.rxn-live-pane` lleva `overflow-y: auto`. Con eso el tab-pane ocupa exactamente lo que sobra entre header y footer — sin hueco, en cualquier circunstancia.

2. **JS**: `installRxnLivePaneSizer` setea `max-height` al **card de la tabla y al card del chart** en runtime. Dos pases:
   - Estimativo: `innerHeight − card.top − 12px`.
   - Correctivo (doble rAF): si `documentElement.scrollHeight − clientHeight > 0`, descuenta ese overflow de ambos cards. Así captura footer global, copyrights, márgenes — sin enumerar.

3. **Sincronización chart ↔ tabla**: el max-height se aplica a ambos cards para que el row no se estire más allá por el chart-card (que podría tener contenido intrínseco más alto que el max-height permitido al table-card).

### Fase 4 — Documentación

- `MODULE_CONTEXT.md` de RxnLive ampliado con:
  - Sección "Layout / Viewport — sizer dinámico del card de la tabla" con el setup CSS completo.
  - Historia evolutiva v0 (hardcoded) → v1 (sizer sobre pane) → v2 (doble pase sobre pane) → v3 (sizer sobre card + flex-column). Esto evita que en futuras iteraciones se repita el zigzag.
  - Ítems 5 y 6 en "No romper" (mantener clase `.rxn-live-pane`, no re-hardcodear `max-height: Nvh`).
  - Checklist post-cambio ampliado con validación en PDS denso + Ventas liviano.

### Fase 5 — Engram

- Topic `rxnlive/viewport-height-sizer` upserteado tres veces durante la sesión (v1, v2, v3) para que quede el razonamiento completo — no solo la solución final.
- Nuevo topic `rxnlive/criticality-business-importance` guardando la declaración de Charly: RxnLive es la herramienta analítica principal de Reaxion, critical por uso aunque MEDIO por aislamiento de datos.

---

## Por qué

- El fix del 1.21.0 (`overflow-y:auto`) era quirúrgico sobre un síntoma, no sobre la causa raíz. El bug seguía vivo.
- RxnLive es la herramienta analítica principal: cada bug de UX ahí impacta el trabajo diario del equipo.
- Dos releases anteriores habían quedado SIN OTA esperando esta corrección — esta release libera 1.21.0 al reino.
- El approach flex-column + sizer dinámico es robusto ante cualquier cambio futuro del admin_layout, topbar, barra de filtros o chart. No depende de magic numbers ni de enumerar qué hay arriba/abajo.

---

## Impacto

- **RxnLive** (todos los datasets): el footer de paginación siempre queda pegado al borde inferior del viewport, sin scroll del body, sin hueco interno del card, en cualquier resolución. Probado en Ventas Histórico (14 filas) y PDS (45 filas denso); por diseño funciona igual para Clientes e Integración Tango.
- **Factory OTA**: esta release libera también los cambios de 1.21.0 (UX transversal de toda la suite: full-width, tema claro, Escape contextual, compactación de forms) que habían quedado sin OTA esperando este fix.

---

## Decisiones tomadas

- **Bump minor (1.22.0) en vez de patch (1.21.1)**: por ser un cambio arquitectural del layout que aplica a TODOS los datasets de la herramienta analítica más usada, y por el pedido explícito de Charly de que quede "ultra registrado".
- **Setear max-height al card (no al pane)** + flex-column: es la única forma robusta de evitar el hueco cuando el chart-col fuerza más altura al row por `align-items:stretch`. Cualquier otro approach (limitar pane, medir siblings, usar 70dvh, calc con offsets hardcodeados) es frágil ante cambios del admin_layout.
- **Sincronizar max-height entre table-card y chart-card**: aunque suena redundante, es necesario para que el chart (que puede tener contenido intrínseco más alto por el canvas) no empuje al row a superar el viewport.
- **Documentar la historia evolutiva v0→v3 en MODULE_CONTEXT**: para que la próxima iteración (si aparece edge case) arranque con contexto completo y no se repita el zigzag.

---

## Validación

- Refresco duro (Ctrl+Shift+R) en `/rxn_live/dataset?dataset=pedidos_servicio` → footer de paginación pegado al borde inferior, sin scroll del body, sin hueco interno. Confirmado por Charly.
- Refresco en `/rxn_live/dataset?dataset=ventas_historico` → idéntico encuadre, sin regresión del comportamiento previo.
- Toggle chart/tabla (Alt+botones) → el sizer se recalcula automáticamente via el `dispatchEvent('resize')` que ya existía en `applyViewVisibility`.
- Cambio entre Vista Plana y Tabla Dinámica → `shown.bs.tab` re-mide y aplica al pane que se vuelve visible.
- Redimensionar la ventana → debounce 60ms, se re-ajusta sin flicker.

---

## Pendiente

- Vigilar comportamiento en resoluciones extremas (4K, pantallas ultra-anchas, laptops <768px). El `MIN_CARD_HEIGHT=320` es el piso; si en pantallas muy chicas se siente apretado, bajar el piso o agregar un breakpoint.
- Considerar virtualización de filas en la Vista Plana cuando los datasets crezcan más allá de ~500 filas — el DOM con 500 `<tr>` empieza a notarse al filtrar/ordenar.
- Cualquier cambio futuro al `admin_layout.php` (altura del topbar, alertas globales, banner corporativo) debe revalidar el encuadre de RxnLive — el sizer lo acomoda solo pero vale la pena confirmar.

---

## Relevant files

- `app/modules/RxnLive/views/dataset.php` — bloque `<style>` del `.rxn-live-shell` + IIFE `installRxnLivePaneSizer` + clases `.rxn-live-pane` en los dos panes.
- `app/modules/RxnLive/MODULE_CONTEXT.md` — nueva sección "Layout / Viewport" + ampliación de "No romper" y checklist post-cambio.
- `app/config/version.php` — bump a 1.22.0.
- `database/migrations/2026_04_23_03_seed_customer_notes_release_1_22_0.php` — seed con la novedad visible para el cliente final.
