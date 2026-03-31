# [CRM/PDS] - Checkbox de ahora y preview liberado

## Que se hizo
- Se movio el bloque de pegado de imagenes para que quede debajo del textarea `Diagnostico`, liberando la parte superior del campo.
- Se redujo un poco mas el tamano de las miniaturas del diagnostico.
- Se agrego un checkbox junto a `Finalizado` para capturar la fecha y hora actual y mantenerla actualizada mientras este activo.

## Por que
- El preview seguia sintiendose pegado arriba del diagnostico en la operatoria visual.
- Cargar a mano la fecha/hora de finalizacion agregaba friccion innecesaria.

## Impacto
- El campo `Diagnostico` queda mas limpio y el pegado de imagenes acompana mejor el flujo natural de escritura.
- El operador puede cerrar el PDS con timestamp actual en un click.
