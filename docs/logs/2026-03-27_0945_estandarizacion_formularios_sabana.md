# [UI] - Estandarizacion responsive de formularios tipo sabana

## Que se hizo
- Se extendio la capa CSS global con utilidades para formularios amplios y consistentes: grilla, secciones, acciones, switches y panel lateral sticky.
- Se reorganizaron los formularios de `empresas`, `usuarios`, `articulos` y `clientes` para que usen una estructura tipo sabana en escritorio y colapsen ordenadamente en mobile.
- Se unifico el patron visual de cabecera, cuerpo principal y acciones finales sin introducir componentes nuevos ni dependencias externas.

## Por que
- Los formularios venian con anchos fijos y bloques apilados de forma irregular, lo que hacia que en celular se cortaran, se vieran angostos o perdieran jerarquia visual.

## Impacto
- En escritorio los formularios aprovechan mejor el ancho disponible con una composicion mas pareja.
- En mobile todos los campos vuelven a una sola columna, manteniendo legibilidad y botones accesibles.
- Se conserva el stack actual: PHP server-rendered + Bootstrap 5 + CSS propio.

## Decisiones tomadas
- Se priorizo una solucion conservadora basada en clases reutilizables dentro de `public/css/rxn-theming.css`.
- Se aplico primero a formularios nucleares del backoffice para consolidar un patron antes de extenderlo al resto del sistema.
