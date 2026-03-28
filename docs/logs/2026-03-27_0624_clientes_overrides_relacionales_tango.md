# [CLIENTES WEB] - Overrides relacionales Tango desde la web

## Que se hizo
- Se extendio `app/modules/ClientesWeb/views/edit.php` para que al usar `Obtener clientes de Tango` tambien aparezcan selectores dinamicos de condicion de venta, lista de precios, vendedor y transporte.
- Se agrego `GET /mi-empresa/clientes/metadata-tango` en `app/config/routes.php` y `ClienteWebController::obtenerMetadataTango()` para entregar los catalogos comerciales via JSON.
- Se amplio `ClienteTangoLookupService` con catalogos paginados para `process=2497`, `process=984`, `process=952` y `process=960`, incluyendo codigo externo, ID interno y etiqueta visible.
- Se reforzo el guardado para que los valores traidos del cliente Tango puedan ser sobreescritos desde la web y persistan tanto el codigo visible como el ID interno utilizado por el mapper de pedidos.

## Por que
- El cliente Tango puede no tener completos sus parametros comerciales en el ABM del ERP, pero los pedidos web igual necesitan esos IDs para salir bien armados.
- Hacia falta una capa intermedia donde la web tomara el cliente habitual como base y permitiera corregir o completar sus relaciones comerciales sin tocar el maestro fuente.

## Impacto
- Al buscar o vincular un cliente Tango, el operador ahora puede revisar en la misma pantalla las relaciones clave del encabezado del pedido antes de guardar.
- Si ya habia valores persistidos localmente, al abrir los selectores se preseleccionan; si el cliente Tango trae defaults, se aplican como base editable.
- El pipeline de pedidos sigue usando IDs internos (`ID_GVA01`, `ID_GVA10`, `ID_GVA23`, `ID_GVA24`), pero ahora con mayor flexibilidad operativa para completar faltantes desde la web.

## Decisiones tomadas
- Se mantuvo arquitectura simple: backend PHP devuelve metadata y el front solo hace `fetch()` con JS vanilla cuando el operador lo necesita.
- Para transporte se uso `process=960`, porque la auditoria real mostro que `2117` devuelve clientes y no el catalogo de transportes.
- Los overrides solo se aplican cuando el bloque comercial fue efectivamente abierto/cargado, evitando que un guardado comun toque relaciones sin intencion del operador.
