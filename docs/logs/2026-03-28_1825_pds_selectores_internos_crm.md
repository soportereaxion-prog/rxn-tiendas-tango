# [CRM/PDS] - Selectores internos de Cliente y Articulo

## Que se hizo
- Se reforzo el comportamiento de `Cliente` y `Articulo` en PDS como selectores internos con sugerencias locales del CRM.
- Se ajustaron placeholders y mensajes para dejar claro que ambos campos buscan dentro de `Clientes CRM` y `Articulos CRM`.
- Se enriquecio la sugerencia visual con datos utiles del modulo interno (codigo Tango/GVA14 para clientes, SKU/descripcion para articulos).

## Por que
- El objetivo operativo es seleccionar rapido desde la cache/modulos internos sin depender de llamadas remotas a la API en cada tipeo.

## Impacto
- El operador obtiene una experiencia mas parecida a los buscadores con autosuggest del sistema, pero aplicada como selector dentro del PDS.
