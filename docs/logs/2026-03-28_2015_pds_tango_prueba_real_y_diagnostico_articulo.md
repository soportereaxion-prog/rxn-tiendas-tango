# [CRM/PDS] - Prueba real de envio a Tango y diagnostico del articulo

## Que se hizo
- Se corrigio `TangoOrderClient` para que use rutas `/Api/*` consistentes con el resto de la integracion (`/Api/Create` y `/Api/GetByFilter`).
- Se endurecio `PedidoServicioTangoService` para no considerar exitoso un `HTTP 200` cuando Connect responde `succeeded=false`.
- Se agrego un reintento automatico sin `OBSERVACIONES` cuando el perfil de Tango rechaza ese campo.
- Se ejecuto una prueba real de envio del PDS `#2` hacia Tango/Connect.

## Resultado de la prueba
- El lookup de articulo ya resuelve `ID_STA11` correctamente.
- Para el PDS probado se confirmo que el payload envio `ID_STA11 = 13` y el codigo `0200200245`.
- Connect ya no rechaza por falta de `ID_STA11`; ahora devuelve un error de negocio del articulo: `La equivalencia de la unidad de ventas no puede ser 0`.

## Conclusión operativa
- El problema actual ya no esta en la app ni en el selector del PDS: el pedido llega a Tango con articulo e `ID_STA11` resueltos.
- El bloqueo restante apunta a configuracion maestra del articulo en Tango (unidad/equivalencia comercial del item `0200200245`).

## Impacto
- El sistema guarda mejor la trazabilidad real de Connect y deja de marcar como exitosos envios que fueron rechazados semanticamente.
