# [CRM/PDS] - Envio a Tango estable y capturas en Diagnostico

## Que se hizo
- Se corrigio el error `SQLSTATE[HY093]` del update de `crm_pedidos_servicio`, provocado por un parametro sobrante al guardar el PDS.
- Se implemento adjunto de capturas para `Diagnostico` en el PDS con pegado desde portapapeles, dropzone liviano y referencias automaticas `#imagenN`.
- Las capturas se guardan en disco bajo `/public/uploads/pds-diagnostico/YYYY/MM` y se registran en la nueva tabla `crm_pedidos_servicio_adjuntos`.
- La ficha del PDS ahora muestra capturas ya guardadas y permite sumar nuevas en edicion sin depender de la API.
- Se amplio la ayuda operativa para explicar el uso de imagenes pegadas dentro del diagnostico.

## Por que
- El flujo de envio a Tango quedaba bloqueado por una falla local de persistencia.
- El diagnostico necesitaba capturas internas referenciables para futuro envio por correo y mejor trazabilidad tecnica.

## Impacto
- Guardar/actualizar el PDS vuelve a funcionar sin reventar por parametros PDO.
- El operador puede pegar imagenes en `Diagnostico`, ver sus referencias y guardarlas junto al pedido de servicio.
- El PDS queda mejor preparado para futuros mails operativos con adjuntos o referencias visuales.

## Decisiones tomadas
- Se mantuvo el enfoque local-first: capturas almacenadas en disco y referencias persistidas en tabla propia del PDS, sin meter servicios externos.
- Las referencias `#imagenN` se insertan del lado cliente al pegar la imagen y se materializan al guardar el formulario.
