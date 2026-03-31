# [CRM/PDS] - Resolucion robusta de ID_STA11 y miniaturas de diagnostico

## Que se hizo
- Se reforzo `TangoOrderClient::getArticleIdByCode()` para resolver `ID_STA11` de forma mas tolerante, contemplando variantes del codigo (`exacto`, `trim`, `rtrim`) y distintas formas del payload devuelto por Connect.
- Se ajusto la visual de capturas en `Diagnostico` para que se rendericen como miniaturas chicas, alineadas al estilo de la bitacora interna.

## Por que
- El envio de PDS a Tango estaba fallando cuando la resolucion de `ID_STA11` no encontraba el articulo por diferencias de padding o por formas alternativas del response.
- Las capturas del diagnostico habian quedado demasiado anchas y no respetaban la mecanica visual ya validada en la bitacora.

## Impacto
- Mejora la probabilidad de que el PDS entre a Tango sin romper por lookup de articulo.
- Las imagenes del diagnostico quedan mas legibles y compactas debajo del textarea.
