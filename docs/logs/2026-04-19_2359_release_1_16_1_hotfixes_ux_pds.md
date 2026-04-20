# Release 1.16.1 — Hotfixes UX PDS (calc en vivo, Alt+O, códigos cliente/artículo)

**Fecha**: 2026-04-19 (cierre nocturno post-release 1.16.0)
**Versión**: 1.16.0 → 1.16.1
**Build**: 20260419.2 → 20260419.3
**Tipo**: Patch UX (sin migraciones, sin cambios de schema)

---

## Qué se hizo

Pulido UX sobre el form de PDS (`CrmPedidosServicio`), 6 pedidos de Charly en una misma sesión nocturna:

### 1. Copy de PDS blanquea descuento + motivo
Al copiar un PDS desde el botón "Copiar" del form de edición, el controller ya reseteaba fechas, Tango sync y duraciones, pero NO tocaba `descuento` ni `motivo_descuento`. Ahora sí: `descuento = '00:00:00'` y `motivo_descuento = null`.

### 2. Botón "reloj" de Finalizado respeta el día de inicio
El botón que autollena Finalizado con "ahora" usaba `new Date()` plano. Charly necesitaba poder cerrar PDS retroactivos (ej: inicio día 17) sin que el botón saltase al día de hoy. Ahora el botón toma el **día de `fecha_inicio` (si está cargada) + la hora actual del sistema**. Si no hay fecha_inicio, mantiene el comportamiento anterior.

### 3. Cronómetro vivo cuando no hay fecha fin
Feature que existía al principio del módulo y se había sacado sin querer. Ahora, cuando `fecha_inicio` está cargada y `fecha_finalizado` está vacío, los chips Tiempo bruto / Tiempo neto / Decimal se refrescan en vivo cada 500ms usando `new Date()` como endDate. Apenas se setea fecha fin → snapshot normal.

### 4. Fix definitivo del calc durante edición de fecha fin (el gran problema)

**Lo que se veía**: editabas la fecha fin (tipeando o usando el popup del calendario) y los chips no se actualizaban hasta dar Enter. Charly recordaba que antes iba en vivo.

**Iteraciones fallidas** (4 intentos antes del rootcause):
- **Intento 1**: agregar `change` listener además de `input`. No alcanzó.
- **Intento 2**: hookear `_flatpickr.config.onChange` como array apilable. Siguió fallando en ciertos flujos.
- **Intento 3**: priorizar el parse del altInput en lugar de `selectedDates[0]`. Sin efecto.
- **Intento 4**: parseAltString con regex propio para no depender de `fp.parseDate`. Sin efecto.
- **Intento 5 (el bueno)**: diagnóstico persistente via overlay `?debug_calc=1`. El overlay reveló que el problema NO era el parse ni el readDate: **el `evaluate()` directamente no se estaba ejecutando** después de cierto momento. El overlay quedaba congelado aunque el campo se editaba.

**Root cause real**: Flatpickr con `altInput: true` + popup del calendario hace **switch de focus** entre el altInput y el popup. Al abrir el popup, dispara el `blur` del altInput → nuestro handler llamaba `clearInterval(focusPollId)` → el polling moría silenciosamente. A partir de ese momento, aunque el usuario siguiera editando, nada reactivaba el refresh.

**Fix**: reemplazo del polling basado en focus/blur + ticker separado por un **`setInterval(evaluate, 500)` permanente**. Barato (solo DOM y parse regex), inmune al manejo de focus del picker. El ticker del cronómetro sale gratis en el mismo tick.

**Lección meta**: ante un bug que se resiste a múltiples fixes de hipótesis, parar las iteraciones, meter diagnóstico persistente (ya marcado como regla en `CLAUDE.md`), medir, y recién ahí fijar. El overlay visible en pantalla vale más que 4 iteraciones ciegas.

### 5. Atajo Alt+O para copiar desde el form
Antes, Alt+O solo funcionaba en listados con filas `data-copy-url` (cubierto por `rxn-list-shortcuts.js`). En el form de edición no había registro, entonces no aparecía en el modal `Shift+?` del PDS ni respondía a la tecla. Ahora registrado manualmente en `form.php` apuntando al `form[action$="/copiar"] button[type="submit"]`.

### 6. Códigos de cliente y artículo persistentes en el meta del picker
Antes, al seleccionar cliente o artículo aparecía el caption del picker ("#id | codigo") debajo del campo. Al grabar y recargar, PHP renderizaba solo "#id" y se perdía el código. Solución:

- `articulo_codigo` ya estaba en la DB (columna de snapshot), solo faltaba pasarlo a `hydrateFormState` y a `defaultFormState`. Fue otro caso del antipatrón "hydrateFormState como whitelist" ya documentado en memoria.
- `cliente_codigo` no existía como snapshot; trade-off consciente para no meter migración: nuevo método `PedidoServicioRepository::findClientCodeTangoById(clienteId, empresaId)` que hace lookup en `crm_clientes.codigo_tango`. Se llama desde `hydrateFormState`. **Tradeoff**: si el cliente cambia su `codigo_tango`, el meta del PDS viejo reflejará el nuevo (no es snapshot histórico). El payload Tango sigue siendo el ancla histórica real.

Form.php ahora muestra: `Cliente vinculado #212 | CODIGOTANGO` y `Articulo vinculado #1727 | ASEHRS504`.

### 7. Overlay de diagnóstico persistente (feature oculta)
Se deja activable con `?debug_calc=1` en la URL del form de PDS. Muestra un overlay verde/negro en la esquina inferior derecha con el estado interno del calc en tiempo real (altInput/input/selectedDates/readDate para ambos campos, descuento, segundos calculados, textContent final, y motivo de EARLY-RETURN si ocurre). Útil para debugear futuros bugs del picker sin depender de DevTools, respetando la regla del CLAUDE.md "Diagnóstico persistente > DevTools".

---

## Por qué

Charly detectó 5 puntos en una sesión nocturna (9 iteraciones en total, incluido el ciclo de diagnóstico con overlay):

1. Copy no venía limpio en 2 campos → fricción en el workflow de duplicar PDS.
2. Relojito saltaba al día de hoy → impedía cierre de PDS retroactivos.
3. Tiempo neto no corría en vivo → faltaba feedback del cronómetro.
4. Editar fecha fin no refrescaba las horas → el bug central, 4 iteraciones hasta root cause.
5. Alt+O no funcionaba en el form → inconsistencia con los demás atajos del PDS.
6. Códigos no persistían → UX degradada al recargar.

---

## Impacto

- **Operativo**: PDS ahora se puede copiar limpio, cerrar retroactivo, ver el cronómetro correr y editar fechas con feedback en vivo. Atajos consistentes (Alt+O, Alt+P, Alt+E visibles en `Shift+?`).
- **Técnico**: regla documentada en memoria (Engram + log) sobre Flatpickr + altInput + focus/blur. Overlay de debug oculto disponible para futuros casos.
- **DB**: sin cambios de schema. Sin migraciones.

---

## Decisiones tomadas

- **Lookup dinámico del `cliente_codigo` vs snapshot** — se eligió lookup por consistencia con "no agregar migración en un hotfix UX". El payload Tango mantiene el dato histórico si hiciera falta. Charly quedó OK con este trade-off tras explicárselo.
- **Dejar el overlay como feature oculta** — no molesta en producción (solo se activa con query param) y habilita debugging rápido ante bugs futuros del picker.
- **setInterval permanente a 500ms en lugar de on-demand** — costo despreciable (solo DOM + regex, sin allocs, sin queries) y 100% inmune al event handling de Flatpickr. Trade-off elegido: simplicidad > micro-optimización.

---

## Validación

Testeado con Charly en sesión:

- Copy PDS → descuento en "00:00:00", motivo textarea vacío ✅
- Botón reloj con inicio del 17 → cierra con día 17 ✅
- Sin fecha fin → bruto/neto corren como cronómetro ✅
- Editar fecha fin tipeando → bruto/neto siguen al texto en vivo ✅ (confirmado por Charly antes de la OTA)
- Alt+O desde el form → dispara el submit de copiar ✅
- Recargar un PDS guardado → meta muestra `#ID | CODIGO` para cliente y artículo ✅
- Overlay `?debug_calc=1` → visible, auto-refresh, útil ✅

---

## Pendiente

- Nada bloqueante. Si más adelante Charly quiere snapshot fiel del `cliente_codigo` (no dinámico), requiere migración + actualización del INSERT/UPDATE del repo + `buildFormStateFromPost` para recibir el codigo. Queda anotado como mejora no urgente.

---

## Relevant files

- `app/modules/CrmPedidosServicio/PedidoServicioController.php` — copy() reset + hydrate/default con articulo_codigo + cliente_codigo.
- `app/modules/CrmPedidosServicio/PedidoServicioRepository.php` — nuevo findClientCodeTangoById.
- `app/modules/CrmPedidosServicio/views/form.php` — metas con código + Alt+O.
- `public/js/crm-pedidos-servicio-form.js` — setupCalculator refactorizado (polling permanente), parseAltString nuevo, readDate con 3 capas, setupCheckboxAhora con día de inicio, overlay debug_calc.
- `app/config/version.php` — bump 1.16.1 / build 20260419.3.
- `docs/logs/2026-04-19_2359_release_1_16_1_hotfixes_ux_pds.md` — este log.
