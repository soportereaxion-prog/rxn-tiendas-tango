# Incorporación de Dataset Pedidos de Servicio a RXN_LIVE

## Qué se hizo
- Se interpretó una query legacy de análisis de Pedidos de Servicio (que involucraba cruces asimétricos con GVA14, GVA21, Vendedores y Técnicos).
- Se homologó dicha extracción hacia la estructura modernizada y desnormalizada del nuevo CRM (`crm_pedidos_servicio`).
- Se creó la vista SQL `RXN_LIVE_VW_PEDIDOS_SERVICIO` en `rxn_live_views.sql`.
- Se dio de alta el dataset "Pedidos de Servicio (Tiempos)" en el catálogo de `RxnLiveService`, habilitando gráficos y reportes tipo pivot.

## Por qué
El requerimiento era contar con la misma analítica operativa de Tiempos VS Facturación (Tango) que se utilizaba legacy, pero ahora de manera nativa y rápida dentro del nuevo ecosistema `RXN_LIVE`.

## Impacto
- Se agrega un tercer dataset clave al panel.
- El sistema auto-creará la vista (`ensureViewsExist`) a nivel base de datos la próxima vez que se consulte el módulo.
- No se introdujo sobrearquitectura, respetando la estructura existente del catálogo en `RxnLiveService.php`.
