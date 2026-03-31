# [CRM/PDS] - Compactacion fina del encabezado

## Que se hizo
- Se elimino texto auxiliar no esencial del encabezado del PDS para sacar aire muerto entre filas.
- Se redujeron aun mas `padding`, `gap` y separaciones verticales del formulario.
- Se ajusto el picker para no volver a inyectar mensajes vacios debajo de `Cliente` y `Articulo` cuando no hay seleccion.

## Por que
- El encabezado seguia dejando un bloque visual vacio entre la segunda fila de campos y `Detalle tecnico`.

## Impacto
- La pantalla queda mas compacta y el bloque tecnico sube sin romper la logica de busqueda de cliente/articulo.
