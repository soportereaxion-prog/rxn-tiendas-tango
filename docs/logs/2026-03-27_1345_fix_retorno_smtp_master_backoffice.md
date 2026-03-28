# [UI] - Retorno correcto desde SMTP Master RXN

## Que se hizo
- Se corrigio el boton de retorno en `SMTP Master RXN` para que vuelva al `RXN Backoffice` en lugar de llevar a la gestion de empresas.

## Por que
- El flujo esperado desde esa pantalla es regresar al launcher administrativo del backoffice, no al listado de empresas.

## Impacto
- Se conserva el contexto visual y operativo correcto al salir de SMTP global.

## Decisiones tomadas
- Se reutilizo la ruta existente `/admin/dashboard` como destino natural del retorno.
