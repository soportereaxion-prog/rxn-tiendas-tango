# [Pedidos] - Reenvio masivo y contador visible de envios a Tango

## Que se hizo
- Se agrego un contador visible de intentos de envio a Tango en el detalle y en el listado de pedidos.
- Se incorporaron acciones masivas para reenviar pedidos seleccionados y para reenviar todos los pendientes.
- Se mantuvo el estado de integracion existente y se reutilizo `intentos_envio_tango` como fuente del conteo.

## Por que
- Era necesario poder volver a enviar pedidos de forma controlada sin operar uno por uno.
- El contador ayuda a diagnosticar rapidamente si un pedido ya fue intentado varias veces.

## Impacto
- El operador puede seleccionar pedidos desde el listado y reenviarlos en lote.
- Tambien puede disparar un reenviar todo lo pendiente.
- El detalle de cada pedido deja mas claro su historial de intentos.

## Decisiones tomadas
- No se agregaron columnas nuevas porque el conteo ya estaba cubierto por `intentos_envio_tango`.
- Se mantuvo el flujo individual existente y se sumaron las rutas masivas como extension simple.
