# Release 1.30.0 — Presupuestos: Comentarios + Observaciones a Tango + bugfix lock + 3 hotkeys

**Fecha:** 2026-04-30
**Build:** 20260430.1

---

## Qué se hizo

### 1. Comentarios + Observaciones — viajan a OBSERVACIONES de Tango

La cabecera del Presupuesto suma 2 campos `TEXT NULL` (`comentarios`, `observaciones`) que se editan en el form como 2 textareas paralelos (col-6 c/u, inspirados en el Tango legacy donde aparecen como paneles "Comentarios" y "Observaciones" lado a lado).

**Persistencia DB:**
- Migración `2026_04_30_alter_crm_presupuestos_add_comentarios_observaciones.php` (idempotente).
- ALTERs defensivos en `PresupuestoRepository::ensureSchema()`.
- `createNewVersion()` hereda los campos al versionar.

**UI:**
- 2 textareas col-6 (paralelos) post-leyendas en el form.
- Contador en vivo `N / 950 chars a Tango` con banner warning amarillo cuando se supera.
- JS de recálculo dentro del bloque P0/P1/P2 inline del form.

**Tango:**
- Viajan **concatenados como un único string** en el campo `OBSERVACIONES`, separados por `" | "` (NO usar `\n\n`).
- Sanitización defensiva: CRLF/LF/whitespace colapsado a espacio único.
- Truncado a 950 chars (límite Tango Connect).
- El nro de presupuesto NO se incluye (la relación queda asentada vía `nro_comprobante_tango`).

**PrintForms:**
- Ambos campos expuestos al Canvas como `presupuesto.comentarios` y `presupuesto.observaciones`.

**Defensivo en el mapper (release 1.30.0):**
- `TangoOrderMapper::map()` calcula `OBSERVACIONES` aparte y lo **reinyecta DESPUÉS del `array_filter`**. Garantía de que el campo crítico nunca se pierda por regresiones futuras del filtro.

### 2. Bugfix — lock de cabecera por clasificación

**Síntoma:** operador con renglones cargados y sin clasificación (campo obligatorio) → header bloqueado → no puede completar el campo que justamente bloqueaba el guardado.

**Causa:** `headerRequiredFieldsFilled()` chequeaba fecha + cliente + lista, pero NO clasificación. Cuando se sumó como obligatoria, el circuit breaker quedó desactualizado.

**Fix:**
- `public/js/crm-presupuestos-form.js`: sumar `clasificacion_codigo` al chequeo. Listener `change` que reaplica el lock cuando se completa, manteniendo la regla original (items + cabecera completa = lock).

### 3. Hotkeys nuevas (visibles en Shift+?)

Registradas en `RxnShortcuts` desde el form:
- **Alt+P** — Enviar Presupuesto a Tango.
- **Alt+E** — Enviar Presupuesto por correo.
- **Alt+O** — Copiar Presupuesto (duplicar como nuevo).

Sin conflicto con `rxn-list-shortcuts.js` que usa Alt+O para copiar URL en listados (en el form no hay listado).

---

## Por qué

Charly necesitaba paridad UX con el Tango legacy en la cabecera comercial. Los 2 campos son críticos para que el vendedor capture contexto del presupuesto (Comentarios = info del producto/contrato; Observaciones = texto libre del vendedor) y que esa info viaje al ERP en el campo correspondiente.

El bugfix del lock se descubrió en la misma sesión: el rey lo trajo como friction observada ("se queda pegado el validador"). Las hotkeys completan la setup de productividad del módulo.

---

## Trampa Tango descubierta durante el debug — DOCUMENTADA EN MODULE_CONTEXT

Durante el debug del envío descubrimos que el `ID_PERFIL_PEDIDO` de Tango Connect puede tener marcado `OBSERVACIONES` como **no editable desde la API**. Cuando eso pasa:

1. El primer envío falla con `succeeded: false` y mensaje `"El perfil utilizado (X - <NOMBRE>) no permite editar el campo OBSERVACIONES."`.
2. El retry defensivo del service (`shouldRetryWithoutObservaciones`) saca el campo y reenvía.
3. El segundo envío crea el pedido OK pero **sin observaciones**.
4. El operador ve "envío exitoso" mientras los datos se pierden silenciosamente.

**Solución:** NO es de código. El operador del cliente tiene que ir a Tango → Ventas → Pedidos → Perfiles → editar el perfil usado → habilitar la edición del campo OBSERVACIONES.

El retry defensivo se mantiene como red de seguridad (útil para otros casos edge — longitud, caracteres inválidos, etc).

---

## Impacto

- **Operadores de Presupuestos:** ganan 2 textareas grandes para capturar contexto rico que viaja al ERP. Ven en vivo cuántos chars se van a mandar.
- **PrintForms:** los Canvas pueden referenciar `presupuesto.comentarios` y `presupuesto.observaciones` para imprimir esa info en el documento.
- **Operadores de cualquier tenant nuevo:** la trampa del perfil de pedido queda documentada en MODULE_CONTEXT — la próxima vez que aparezca, el debug es de minutos, no de horas.

---

## Decisiones tomadas

1. **Separador `" | "` en lugar de `"\n\n"`:** texto de una sola línea es más portable y se ve mejor en grids de Tango. Aunque el bug original NO era por newlines (era por perfil), igual la decisión se mantiene como buena práctica.
2. **El nro de presupuesto NO se incluye en OBSERVACIONES:** redundante, ya queda en `nro_comprobante_tango`. Mantener el campo limpio para texto del operador.
3. **Reinyección post-array_filter en el mapper:** defensivo contra cambios futuros del filtro que pudieran sacar strings vacíos. El campo es crítico, no debe poder perderse.
4. **Retry defensivo se mantiene activo:** útil como red de seguridad para casos edge donde el campo pueda ser inválido por otro motivo.

---

## Validación

- Migración corrió OK en local.
- Lint OK en todos los archivos PHP tocados.
- Smoke test del mapper con row real de DB: emite `OBSERVACIONES` correctamente.
- Envío end-to-end a Tango (después de habilitar el campo en el perfil): `OBSERVACIONES = "Probando los comentarios de tu viejo | Una observacion de tu hermana"` ✅
- Hotkeys probadas en form de edit: Alt+P, Alt+E, Alt+O todas funcionando y visibles en Shift+?.

---

## Pendiente

- **Mejora futura (no urgente):** hacer que `markAsSentToTango` persista también el response del PRIMER envío cuando hubo retry. Hoy solo guarda el response final, así que cuando el retry se dispara la pista del rechazo se pierde. Esta sesión la suplimos con un log diagnóstico temporal — para futuras iteraciones convendría incorporarlo al flujo permanente.
