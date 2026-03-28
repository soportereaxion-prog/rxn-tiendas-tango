# [CONFIGURACION] - Selector dinamico para ID de Empresa Connect

## Que se hizo
- Se reemplazo el campo manual `tango_connect_company_id` por un `<select>` dinamico en `app/modules/EmpresaConfig/views/index.php`, replicando el patron visual y operativo ya usado para listas de precio y deposito.
- Se extendio `EmpresaConfigController::getConnectTangoMetadata()` para devolver tambien el catalogo de empresas de Connect junto con la metadata existente.
- Se agrego `TangoApiClient::getMaestroEmpresas()` consumiendo `process=1418` sobre `Get` con header `Company: -1`, manteniendo paginacion, normalizacion de IDs y trazas debug.

## Por que
- El campo `ID de Empresa` seguia siendo texto libre, mientras el resto de selectores Connect ya estaba asistido por metadata real.
- Esto dejaba una brecha UX y de integridad: el operador podia tipear un ID invalido o perder referencia del valor originalmente guardado.

## Impacto
- Al presionar `Validar Conexion`, la UI ahora puede resolver y mostrar las empresas reales de Axoft Connect.
- Si el `tango_connect_company_id` guardado existe en el maestro remoto, el selector queda posicionado automaticamente sobre ese valor.
- Si la configuracion aun no tiene empresa elegida, la validacion puede hacerse igual usando `Company: -1` para descubrir el catalogo base.

## Decisiones tomadas
- Se mantuvo la solucion simple, sin nuevas capas ni endpoints extras: se reutilizo `/mi-empresa/configuracion/tango-metadata`.
- Se dejo `process=1418` desacoplado del company seleccionado forzando `-1`, porque el propio selector debe poder construirse antes o independientemente de la empresa activa.
- Se conservaron los dumps debug dentro de `logs/debug_selectores_connect.json` incluyendo el nuevo bloque de empresas normalizadas.
