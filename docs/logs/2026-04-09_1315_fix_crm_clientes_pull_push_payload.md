# 2026-04-09 13:15 - Fix CRM Clientes: Pull en form, Payload Info y Push verificado

## Cambios realizados

- `app/modules/RxnSync/RxnSyncController.php`
  - El botón `i` deja de consultar una columna inexistente (`tango_response_json`) y ahora lee el último `payload_resumen` real desde `rxn_sync_log`.
- `app/modules/RxnSync/RxnSyncService.php`
  - El `Push` de clientes valida respuestas semánticas de Tango (`succeeded=false`) y ya no marca falso éxito.
  - El payload de cliente ahora toma `direccion` local en `DOMICILIO` en vez de columnas inexistentes (`calle` / `numero`).
  - El `Pull` individual devuelve y persiste más campos cacheables del cliente (`razon_social`, `documento`, `email`, `telefono`, `direccion`, `activo`).
- `app/modules/CrmClientes/CrmClienteController.php`
  - Se agregó endpoint dedicado `pushToTango()` para clientes CRM, alineado al patrón ya usado por artículos.
- `app/config/routes.php`
  - Se agregó la ruta `POST /mi-empresa/crm/clientes/{id}/push-tango`.
- `app/modules/CrmClientes/views/form.php`
  - El `Pull` ahora pisa el formulario abierto en caliente con los datos recién traídos.
  - El `Push` usa endpoint dedicado y muestra `Payload enviado` + `Respuesta API`.
  - El botón `i` usa parseo defensivo para no explotar con `Unexpected token '<'`.
- `app/modules/CrmClientes/views/index.php`
  - El `Push` usa endpoint dedicado y un detalle de respuesta más claro.
- `app/modules/RxnSync/views/index.php`
  - La consola RXN Sync muestra detalles separados para `Payload enviado`, `Respuesta API`, `Snapshot Tango` y `Cache local actualizada`.

## Validación de seguridad base

- Multiempresa: se mantiene aislamiento por `Context::getEmpresaId()` en `CrmClienteController` y `RxnSyncController`.
- Permisos backend: las rutas continúan protegidas por `$requireCrm` y `AuthService::requireLogin()`.
- Admin sistema vs tenant: sin cambios de alcance; el flujo sigue operando dentro del contexto tenant CRM.
- No mutación por GET: se preserva `POST` para `push` y `pull`; `GET` queda solo para lectura de historial/payload.
- Validación server-side: se validan `id`, `entidad` y respuestas reales de Tango antes de informar éxito.
- Escape/XSS: los bloques JSON del frontend siguen escapando `<` y `>` antes de insertarse en modal HTML.
- Impacto acceso local del sistema: nulo; no se tocaron roles globales ni superficie de administración RXN.
- CSRF: no se incorporó token nuevo en esta iteración porque el patrón actual de acciones AJAX internas ya está unificado así; queda como deuda transversal del stack, no introducida por este fix puntual.

## Resultado esperado

- Pull de cliente desde la ficha: actualiza backend y también refresca el formulario visible sin reingresar.
- Botón `i`: vuelve a mostrar historial/payload guardado sin reventar el modal.
- Push de cliente: sólo se informa éxito si Tango realmente acepta la operación; además deja trazabilidad más clara para diagnosticar rechazos reales.
