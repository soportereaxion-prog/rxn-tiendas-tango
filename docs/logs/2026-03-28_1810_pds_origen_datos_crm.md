# [CRM/PDS] - Origen de datos alineado al entorno CRM

## Que se hizo
- Se confirmó y dejó explícito que `Cliente` en PDS consulta `crm_clientes`.
- Se corrigió `Articulo` en PDS para que consulte `crm_articulos` en lugar del catálogo de Tiendas.
- Se ajustó el refresco de snapshots de precio del PDS para tomar también `crm_articulos`.

## Por que
- El formulario PDS pertenece al entorno CRM y debe consumir la base operativa del CRM, no mezclar catálogos con Tiendas.

## Impacto
- Los buscadores de `Cliente` y `Articulo` ahora quedan alineados con el módulo CRM.
- El envío a Tango usa snapshots originados en datos CRM cuando el operador selecciona artículo y cliente desde el PDS.
