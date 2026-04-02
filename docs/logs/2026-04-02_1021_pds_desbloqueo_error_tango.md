# 2026-04-02: Desbloqueo de PDS ante error de sincronizacion

## Que se hizo
Se cambio la condicion de bloqueo del form de Pedidos de Servicio para evaluar el estado de exito del envio a Tango (`tango_sync_status` === 'success') en lugar de bloquear simplemente por la presencia del payload de sincronizacion.
Adicionalmente, se anadio un badge rojo de estado para advertir si el PDS sufrio un error de sincronizacion.

## Por que
Para evitar que el operador quede imposibilitado de editar y reintentar enviar un pedido de servicio si la API de Tango lo rechaza (ej. error en precios, clasificacion ausente, etc.).

## Impacto
- `app/modules/CrmPedidosServicio/views/form.php`: Modificacion del UI y de los campos disableados.
