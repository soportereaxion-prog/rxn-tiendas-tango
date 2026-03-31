# [CRM/PDS] - Preview temporal debajo del diagnostico

## Que se hizo
- El preview temporal de imagenes pegadas ya no se muestra dentro de la caja superior de pegado; ahora aparece debajo del textarea `Diagnostico`.
- Las miniaturas del diagnostico se redujeron aproximadamente un 20% para ocupar menos ancho visual.
- Se ajusto el calculo del siguiente `#imagenN` para tolerar etiquetas viejas con o sin `#` y evitar repeticiones por parser estricto.

## Por que
- La previsualizacion arriba del textarea distraia y rompia la lectura del campo principal.
- Las miniaturas seguian un poco grandes para el espacio operativo del PDS.

## Impacto
- El flujo de pegado queda mas natural: primero escribes en diagnostico y debajo ves las miniaturas asociadas.
