# Release 1.16.4 — Hotfix PDS: validaciones de diagnóstico + clasificación + interceptor client-side

**Fecha**: 2026-04-20
**Build**: 20260420.3
**Scope**: módulo CrmPedidosServicio
**Tipo**: hotfix crítico

---

## Reporte

Charly, textual: *"Bug crítico en PDS!! No está guardando los PDS, ni corriendo los controles básicos, al poner el guión en el pds e intentar grabar se supone que tendría que validar todo el form, sin embargo le das f10 y te saca, no ocurre ninguna validación, te saca del PDS por mas que hayas completado los otros campos inclusive."*

También reportó desde prod: *"Hoy en produ me dijeron que no validaba la clasificación y es correcto."*

---

## Root cause

El form PDS sí se estaba grabando — pero con datos de baja calidad. Secuencia real:

1. Usuario abre PDS en edit mode. Los campos cargan con los valores originales del registro (fecha_inicio, solicito, cliente_id, articulo_id).
2. Usuario toca solo el campo `diagnostico` y pone `-`.
3. Aprieta F10 → dispara click sobre el botón "Guardar" → submit al server.
4. `validateRequest` revisa: fecha OK (tiene valor), solicito OK (tiene valor), cliente OK, artículo OK, descuento OK, fecha_finalizado (opcional salvo action=tango).
5. **Diagnóstico y clasificación NO se validaban**. El `-` en diagnóstico pasa.
6. El controller graba y hace `header('Location: ...')` al listado → "te saca del form" sin feedback de error.

Desde la perspectiva del usuario pareció que "no había validación" — y técnicamente era correcto: para dos campos críticos no había ninguna.

La hipótesis de Charly era que el `setInterval` del cronómetro (release 1.16.1) podía interferir con submit/F10. **Descartada** al auditar: `evaluate()` solo lee DOM y actualiza textContent, no toca el form ni intercepta eventos.

---

## Regla acordada con Charly

> "Cualquier campo que se complete y se intente guardar debe validar el resto."
> "Si no tocás nada → no te obligue (form no dirty)."

Aplicado al form PDS: todo submit **sobre un form dirty** tiene que pasar por validación completa. Si el form no fue tocado, se permite el submit sin interceptar (para no estorbar en PDSs legacy que el usuario solo quiere ver).

Campos obligatorios:
- `fecha_inicio`, `solicito`, `cliente_id`, `articulo_id`, `clasificacion_codigo`, `diagnostico` (siempre).
- `fecha_finalizado` (solo para `action=tango`).
- `clasificacion_id_tango` numérico (solo para `action=tango` — sin id_tango Tango rechaza).

---

## Fix aplicado

### 1) Server-side — nueva validación

En `PedidoServicioController::validateRequest()`:

```php
if ($diagnostico === '') {
    $errors['diagnostico'] = 'Debes indicar el diagnóstico del servicio.';
}

if ($clasificacion === '') {
    $errors['clasificacion_codigo'] = 'Debes indicar la clasificación del servicio.';
} elseif ($action === 'tango' && ($clasificacionIdTango === '' || !is_numeric($clasificacionIdTango))) {
    $errors['clasificacion_codigo'] = 'La clasificación debe estar vinculada a un registro de Tango para poder enviar el pedido.';
}
```

### 2) View — feedback visible

En `form.php` los campos `clasificacion_codigo` y `diagnostico` reciben:
- Atributo `required` (HTML5 — no confiamos en esto como única barrera, pero suma).
- Clase `is-invalid` condicional cuando hay error.
- Div `.invalid-feedback.d-block` con el mensaje.

### 3) Client-side — submit interceptor

Nuevo interceptor en `setupDirtyCheckAndEmailControl` que:

1. Listener `submit` en el mainForm.
2. Si no es dirty → deja pasar.
3. Si es dirty → detecta action via `e.submitter.value` (save/tango).
4. Corre `collectClientSideErrors(action)` que chequea los mismos campos que el server.
5. Si hay errores: `e.preventDefault()` + `stopImmediatePropagation()` + visualiza:
   - Clase `is-invalid` en cada campo faltante.
   - Div `.invalid-feedback.rxn-client-error` debajo con el mensaje.
   - Alert consolidado con la lista completa.
   - Scroll al primer error + foco (con `preventScroll: true` para que el scroll sea el smooth).

El server **sigue siendo la fuente de verdad**. El interceptor client-side es:
- UX (feedback instantáneo sin round-trip).
- Guard extra (si algo futuro hace redirect antes de validar, el form no sale).

---

## Qué NO se tocó

- **setInterval del cronómetro**: confirmado inocente. Se mantiene el polling cada 500ms porque soluciona el bug histórico del focus/blur con Flatpickr.
- **El handler de F10** (`rxn-shortcuts.js`): sigue disparando `saveBtn.click()`. Con el nuevo interceptor submit, incluso con F10 el form bloquea si falta algo.
- **Las otras validaciones del server** (fecha, solicito, cliente, artículo, descuento): seguían funcionando bien, se conservan.

---

## Archivos afectados

- `app/modules/CrmPedidosServicio/PedidoServicioController.php` — validateRequest endurecido.
- `app/modules/CrmPedidosServicio/views/form.php` — required + feedback visible en clasificación y diagnóstico.
- `public/js/crm-pedidos-servicio-form.js` — submit interceptor en setupDirtyCheckAndEmailControl.
- `app/config/version.php` — bump a 1.16.4.

---

## Validación

- [x] Caso: PDS nuevo sin diagnóstico → server tira error + client bloquea.
- [x] Caso: PDS editado con clasificación vacía → bloqueado.
- [x] Caso: PDS editado con diagnóstico = `-` → ahora pasa (≥1 char acordado con Charly), pero `""` bloquea.
- [x] Caso: PDS abierto sin tocar nada + F10 → permite submit (no dirty).
- [x] Caso: `action=tango` sin id_tango → rechaza con mensaje específico.
- [ ] Pendiente: probar en runtime que F10 sobre form dirty muestra alert + scroll al primer error.

---

## Pendiente para próximas iteraciones

- Si Charly quiere endurecer más el diagnóstico (longitud mínima, rechazar placeholders como solo `-` o `asdf`), abrir iteración separada con la regla exacta. Hoy la regla es ≥1 char no-whitespace, acordada explícitamente.
- Evaluar si otros forms CRM tienen la misma deuda (Presupuestos, Tratativas, etc.) — auditar en una pasada aparte.
