# [CLIENTES WEB] - Selector dinamico para vinculo Tango

## Que se hizo
- Se reemplazo el flujo puramente manual de `codigo_tango` en `app/modules/ClientesWeb/views/edit.php` por un buscador dinamico con autofiltrado mientras el operador escribe.
- Se agrego el endpoint local `GET /mi-empresa/clientes/buscar-tango` en `app/config/routes.php` y `ClienteWebController::buscarTango()` para consultar Tango desde backend y devolver sugerencias JSON.
- Se extendio `ClienteTangoLookupService` para soportar busqueda de clientes en `process=2117`, resolucion por `ID_GVA14` y normalizacion de payloads.
- Se reforzo la persistencia para que al guardar el cliente se use el Tango seleccionado y, si el codigo cambia sin una seleccion valida, se limpien los IDs Tango previos evitando desalineaciones.

## Por que
- El operador debia ingresar el cliente Tango a mano y luego validarlo, generando friccion y riesgo de guardar codigos incorrectos o IDs viejos mezclados con un codigo nuevo.
- La operacion correcta es seleccionar un cliente real de Tango mostrando `codigo + razon social`, pero persistiendo el vinculo por `ID_GVA14`.

## Impacto
- La pantalla de edicion de clientes ahora permite buscar candidatos remotos de forma asistida y vincularlos con menos error operativo.
- Al guardar, el sistema conserva la logica comercial existente pero la hace mas segura: evita que quede `codigo_tango` nuevo con `id_gva14_tango` obsoleto.
- Se mantiene compatibilidad con datos ya guardados porque el valor actual sigue visible y la seleccion nueva solo reemplaza el vinculo cuando el usuario realmente elige un cliente del buscador.

## Decisiones tomadas
- Se mantuvo arquitectura simple: PHP server-rendered + JS vanilla con `fetch()` y debounce corto, sin introducir librerias externas de autocomplete.
- La busqueda remota se resolvio en backend para no exponer credenciales Tango al navegador.
- El proceso elegido fue `2117`, reutilizando la logica ya aprobada para clientes Tango y manteniendo `ID_GVA14` como clave de persistencia del vinculo.
