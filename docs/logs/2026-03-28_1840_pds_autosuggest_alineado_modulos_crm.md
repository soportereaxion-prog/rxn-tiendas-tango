# [CRM/PDS] - Autosuggest alineado con directorios CRM

## Que se hizo
- Los selectores de `Cliente` y `Articulo` del PDS ahora reutilizan las sugerencias de los repositorios internos de `Clientes CRM` y `Articulos CRM`.
- Se enriquecieron las sugerencias con los mismos datos utiles del directorio (razon social/codigo Tango/GVA14 para clientes, SKU/descripcion para articulos).
- Si no hay coincidencias, el selector ahora lo informa visualmente en vez de quedar sin reaccion aparente.

## Por que
- El comportamiento tenia que sentirse como un selector interno del CRM y no como un input comun sin feedback.

## Impacto
- El operador puede buscar desde el PDS sobre la cache interna del CRM con una UX mas parecida a los CRUD del sistema.
