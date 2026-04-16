# Log de Cambios

**Fecha:** 2026-04-03 22:38
**Módulo:** Global & CRM Pedidos de Servicio
**Descripción:** Estandarización de modales y atajo para correos en PDS.

## Qué se hizo
1. **Estandarización de Modales:** Se actualizó `public/js/rxn-shortcuts.js` para detectar si un modal de Bootstrap (`.modal.show`) se encuentra abierto. En ese caso:
   - Al presionar **Enter**, se simula automáticamente el clic sobre el botón principal (Aceptar/Guardar). Se exceptúan los `TEXTAREA` para permitir saltos de línea normales.
   - Al presionar **Escape**, se fuerza el cierre presionando el botón secundario (o botón con `data-bs-dismiss`) del modal y se detiene la propagación del evento, de manera que el sistema no gatilla el "Back" (historial) estándar.

2. **Atajo de Correo PDS:** En el formulario de `CrmPedidosServicio` (`app/modules/CrmPedidosServicio/views/form.php`), se añadió la combinación **Alt + E** al listener de teclado del módulo. Ésta simula el clic sobre el botón de envío de correo si está habilitado.

## Por qué
- Brindar una experiencia más fluida, propia de sistemas ERP de escritorio, acelerando la toma de decisiones dentro de los modales.
- Prevenir bugs de navegación: el presionar Escape en un modal anteriormente gatillaba `window.history.back()`, rompiendo el flujo. 
- Hacer que el envío de correos desde el Pedido de Servicio sea instantáneo por teclado, unificando atajos clave como **Alt + P** (Tango) y ahora **Alt + E** (Email).

## Impacto
- **Archivos Modificados:**
   - `public/js/rxn-shortcuts.js`
   - `app/modules/CrmPedidosServicio/views/form.php`
- UI: Mejor experiencia y feedback nativo para componentes integrados.
- Seguridades: Inalteradas. El botón de envío de correo de todos modos está validado y protegido en servidor. Se respetan los botones que estén deshabilitados (`btn.disabled = true`).

## Decisiones tomadas
- Se aplicó la misma regla genérica del módulo principal, lo que hace la solución global sin tener que acoplarla en todos y cada uno de los formularios.
