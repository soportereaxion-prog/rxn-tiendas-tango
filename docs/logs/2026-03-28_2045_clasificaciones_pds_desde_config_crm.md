# [CRM/PDS] - Clasificaciones locales desde Configuracion CRM

## Que se hizo
- Se agrego en `Configuracion CRM` un textarea `clasificaciones_pds_raw` para cargar el catalogo local de clasificaciones del PDS.
- El selector `Clasificacion` del PDS ahora consume ese catalogo interno, con busqueda por codigo o descripcion y sin depender de endpoint externo.
- El PDS guarda `clasificacion_codigo` y `clasificacion_descripcion` como snapshot local para sostener el historico aunque el catalogo cambie despues.

## Por que
- No existe hoy un endpoint formal para consumir clasificaciones y hacia falta una solucion simple, local-first y operativa.

## Impacto
- El operador puede mantener todas las clasificaciones de CRM desde configuracion y reutilizarlas inmediatamente en los Pedidos de Servicio.
- El selector del PDS queda alineado con la misma idea de cache interna que clientes y articulos.
