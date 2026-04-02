# 2026-04-02: Bloqueo de edicion por Nro Pedido en vez de Sync Status

## Que se hizo
Se corrigio el mecanismo de bloqueo del formulario de PDS cambiando la validacion de `tango_sync_status` === 'success' a revisar unicamente si existe o no un `nro_pedido` (estado que asegura que Tango devolvio efectivamente el ID de insercion real en su base). Ademas se evito que se puedan eliminar PDS (tanto individual como masivamente) si ya tienen `nro_pedido`.

## Por que
El status de success en el payload de envio puede ocurrir temporalmente antes de recargar, pero la regla de negocio firme dictaminada es que si la orden devolvio el numero (pedido ya integrado), quede bloqueada y preservada (incluso al eliminar).

## Impacto
- `app/modules/CrmPedidosServicio/views/form.php`: Formularios bloqueados por `nro_pedido`.
- `app/modules/CrmPedidosServicio/views/index.php`: PDS con ID externo desactivan el boton trash para dar alerta.
- `app/modules/CrmPedidosServicio/PedidoServicioRepository.php`: deleteByIds ahora tiene resguardo SQL `nro_pedido IS NULL`.
