# [UX] - Ajuste final de confirmaciones y estandar modal global

## Que se hizo
- Se corrigio el flujo de `Enviar Seleccionados` en pedidos asegurando la carga de Bootstrap JS y el estado habilitado del boton segun checkboxes marcados.
- Se dejo un patron reusable de confirmacion visual basado en `public/js/rxn-confirm-modal.js` y `public/css/rxn-theming.css`.
- Se extendio el uso del modal semaforo a acciones criticas de pedidos, articulos y clientes.

## Por que
- El boton de reenvio masivo no respondia porque el modal dependia de Bootstrap JS y esa vista no lo tenia cargado.
- Hacia falta cerrar el estandar para que el mismo mecanismo pueda reutilizarse en otros contextos sin volver a improvisar.

## Impacto
- `Pedidos` recupera el reenvio masivo con experiencia visual consistente.
- El sistema ya cuenta con una base comun para confirmaciones y feedback importante.

## Decisiones tomadas
- Se mantuvo el stack simple: Bootstrap + JS vanilla.
- El boton de seleccionados queda deshabilitado hasta que exista al menos un pedido marcado, evitando clicks vacios.
