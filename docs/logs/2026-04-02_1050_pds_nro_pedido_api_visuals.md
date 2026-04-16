# 2026-04-02: Optimizaciones Visuales PDS y Refactoring de Numero de Orden Tango

## Que se hizo
- Se anadio badge rojo de 'ERROR API' en la esquina inferior del Estado Operativo si la operacion hacia el ERP tuvo errores.
- Se transformo el checkbox rapido suelto de 'Fecha Finalizado' en un boton real con checkmark respetando estetica transversal.
- Se agrego consulta extendida GetById para los pedidos process=19845 con el objetivo de recuperar el NRO_PEDIDO final formateado de Tango luego del post de Creacion. Este reemplaza al ID de base en crudo.
- Se visibilizo este numero tanto en la edicion (ESTADO OPERATIVO) como en el crud (bajo el numero de PDS) mediante badges.
- Se integro la hotkey Alt+P en el form para habilitar envio directo usando el teclado.

## Por que
El usuario deseaba un acceso visual claro del estatus negativo o el recupero de la order visible con formato real (ej 0000-00000000). La inclusion del boton checkmark unifica la UX y la combinacion de teclado agiliza horas operativas de soporte.

## Impacto
- app/modules/Tango/TangoOrderClient.php: getOrderById agregado.
- app/modules/CrmPedidosServicio/PedidoServicioTangoService.php: Recarga ID extendido si hay exito o guarda fallback local
- views/index.php y views/form.php: Integran badges segun dictamen.
- js/crm-pedidos-servicio-form.js: Lee clic en boton en vez de change en checkbox.
