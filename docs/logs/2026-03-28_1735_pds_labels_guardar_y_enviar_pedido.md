# [CRM/PDS] - Ajuste de labels operativos

## Que se hizo
- Se unifico el CTA principal del formulario PDS como `Guardar` en lugar de variantes distintas segun alta/edicion.
- Se renombro el boton de integracion a `Enviar pedido`, alineandolo con la logica y la nomenclatura ya usada en otros circuitos del sistema.
- Se actualizo la ayuda operativa para reflejar ese nombre visible.

## Por que
- Operativamente el formulario primero guarda todo localmente y despues ofrece un envio comercial separado.
- El nombre `Enviar pedido` describe mejor la accion real y mantiene consistencia con otros modulos.

## Impacto
- Menos ambiguedad visual para el operador al trabajar el flujo `Guardar` -> `Enviar pedido`.
