# Modificaciones RXN LIVE - DataSet PDS Tiempos

## Fecha y Cambio
- **Fecha:** 2026-04-04 08:55
- **Versión:** 1.1.50
- **Cambio:** Ampliación de metadatos en vista Live PDS.

## Qué se hizo
- Se generó la migración `2026_04_04_update_rxn_live_vw_pds` para actualizar la vista `RXN_LIVE_VW_PEDIDOS_SERVICIO`.
- Se incorporó la columna `codigo_articulo` extraída directo desde la base local de PDS.
- Se renombró el output visual (alias) en el objeto constructor del pivot `RxnLiveService.php` para normalizar "Técnico" a "Usuario", alineándose al label de la plataforma CRM.
- Se agregó explícitamente `nro_pedido_tango` y el nuevo `codigo_articulo` a la metadata del pivot para que queden disponibles en el sistema GUI analítico frontend.
- Se actualizó el archivo base de SQL (`database/rxn_live_views.sql`) para nuevas inicializaciones.

## Por qué
- Requerimiento operativo para cruzar y conciliar datos del reporte pivotante con la facturación y los datos crudos sincronizados hacia la base de Tango.
- Para corregir la confusión operativa sobre los identificadores (ID local vs Nro de pedido Tango) que no estaban tan expuestos visualmente en la herramienta de pivotes.

## Impacto
- No se mutaron esquemas crudos (DDL table mutations), solo se repisó una vista DDL (CREATE OR REPLACE VIEW) sin invalidar dependencias subyacentes.
- Impacta automáticamente en la UI Live Dashboard para todos los tenants que consulten la ficha PDS Tiempos.
- Listo para ser paquetizado en build OTA.
