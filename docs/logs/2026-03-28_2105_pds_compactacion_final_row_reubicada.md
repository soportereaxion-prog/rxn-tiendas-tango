# [CRM/PDS] - Reubicacion de fila operativa y miniaturas compactas

## Que se hizo
- Se reubico la fila `Solicito + Pedido Tango + Articulo` desde el encabezado superior hacia el bloque `Detalle tecnico` para reducir aire muerto en la parte alta del formulario.
- Las miniaturas adjuntas del diagnostico ahora usan `inline-grid`, evitando que quede una franja ancha inutil a la derecha cuando hay una sola imagen.

## Por que
- La parte superior del PDS seguia teniendo demasiado ruido vertical y la zona de miniaturas dejaba una sensacion de bloque pegado innecesario.

## Impacto
- El formulario queda mas compacto y el detalle tecnico arranca mas arriba.
- Las imagenes adjuntas se leen como miniaturas chicas debajo del diagnostico sin ocupar una fila visual exagerada.
