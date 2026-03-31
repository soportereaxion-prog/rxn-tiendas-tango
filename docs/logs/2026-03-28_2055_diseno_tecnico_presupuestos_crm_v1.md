# [CRM/PRESUPUESTOS] - Diseno tecnico v1

## Que se hizo
- Se documento el diseno tecnico de `Presupuestos CRM` en `docs/crm_presupuestos_tecnico.md`.
- Se bajo el plan funcional a tablas concretas, rutas, capas y flujo exacto de alta/edicion.
- Se dejo explicitado que el browser no debe hablar directo con Connect/Tango para este modulo y que la cabecera comercial debe resolverse sobre cache/local primero.

## Por que
- El siguiente paso natural despues del plan funcional era cerrar una implementacion aterrizada para que el modulo pueda arrancar sin ambiguedades.
- El autocompletado por cliente, la carga de renglones y el pricing por lista necesitaban reglas tecnicas claras antes de escribir codigo.
- Tambien hacia falta responder al riesgo de futuro con muchas conexiones, evitando un formulario dependiente de consultas remotas interactivas.

## Impacto
- El proyecto ya cuenta con una hoja de ruta tecnica concreta para construir `Presupuestos CRM`.
- Queda definido que el modulo necesita tablas propias para cabecera, renglones y cache de catalogos comerciales.
- Se formaliza un flujo de alta basado en sugerencias locales, autocompletado comercial controlado y snapshots historicos.

## Decisiones tomadas
- Se propone `crm_presupuestos` y `crm_presupuesto_items` como nucleo obligatorio del modulo.
- Se agrega `crm_catalogo_comercial_items` como cache local por empresa para `deposito`, `condicion_venta`, `lista_precio`, `vendedor` y `transporte`.
- El precio por lista queda desacoplado de Tiendas; si el pricing CRM fino no esta resuelto aun, el renglon debe poder guardarse con precio manual sin bloquear el modulo.
- La impresion tipo Crystal/A4/canvas se posterga hasta despues de estabilizar cabecera, renglones y totales.
