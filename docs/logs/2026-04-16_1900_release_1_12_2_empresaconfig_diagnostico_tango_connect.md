# Release 1.12.2 — Diagnostico persistente en integracion Tango Connect

**Fecha y tema**: 2026-04-16 19:00 — EmpresaConfig / integracion Tango Connect. Fix de raiz al bug recurrente del selector de "ID de Empresa (Connect)" que queda vacio despues de validar credenciales.

## Qué se hizo

### Fase 1 — Diagnóstico en el cliente Tango

- `app/modules/Tango/TangoApiClient.php`:
  - Nueva propiedad publica `$debugLastDiagnostic` (array) que se rellena SIEMPRE al ejecutar `fetchCatalog()` o `fetchRichCatalog()` — exito, empty o error.
  - Agregadas las funciones privadas `resetDiagnostic()`, `finalizeDiagnostic()`, `recordDiagnosticError()`, `sampleRaw()`, `currentCompanyHeader()`.
  - Se cambio `catch (\Exception $e)` por `catch (\Throwable $e)` en los dos fetchs para capturar tambien `Error`.
  - Se captura `first_item_keys` (las claves del primer item devuelto por Axoft) y se expone via diagnostic — es la pieza clave para detectar cuando Axoft cambia el shape del JSON y los `idKeys` del TangoApiClient ya no matchean.
  - Se guarda `$defaultCompanyHeader` en el constructor porque `ApiClient::$defaultHeaders` es `private` y no podiamos leer el Company desde afuera.

### Fase 2 — Endpoints del controller

- `app/modules/EmpresaConfig/EmpresaConfigController.php`:
  - Los 4 endpoints atomicos (`getTangoEmpresas`, `getTangoListas`, `getTangoDepositos`, `getTangoPerfiles`) ahora devuelven `{success, data, diagnostic}`. El envelope lo arma `envelopeCatalogResponse()`.
  - Cuando la excepcion salta antes de que `fetchCatalog()` llene el diagnostic (ej: credenciales incompletas en `buildTangoClientFromPost()`), `fallbackDiagnosticFromException()` arma un diagnostic minimo con lo que se sabe.
  - Nuevo metodo `diagnoseTangoConnect()` que dispara `getMaestroEmpresas()` (flujo real con `Company: -1` y `process=1418`) y devuelve un payload con `empresas_parsed_count`, `resultData_list_count`, `top_level_keys`, `first_item_keys`, `raw_response_sample` (2000 chars) y `request_info` sanitizado.
  - `scrubSensitiveHeaders()` redacta el `ApiAuthorization` antes de emitir el debug por JSON.

### Fase 3 — Rutas

- `app/config/routes.php`:
  - Nueva ruta POST `/mi-empresa/configuracion/tango-diagnose` (guard `requireTiendas`).
  - Nueva ruta POST `/mi-empresa/crm/configuracion/tango-diagnose` (guard `requireCrm`).

### Fase 4 — UI

- `app/modules/EmpresaConfig/views/index.php`:
  - Nuevo boton "Diagnostico crudo" al lado de "Validar Conexion" (`btn-outline-secondary`) con tooltip explicando cuando usarlo.
  - Nuevo div `#tango-diagnostic-panel` debajo del hint, oculto por default.
  - `fetchCatalog()` JS ahora guarda SIEMPRE el diagnostic en el array `tangoDiagnostics` (incluso con `success=true`). El sink es `recordTangoDiagnostic()`.
  - Al iniciar `loadTangoMetadata()` el array se resetea y el panel se oculta (`hideTangoDiagnosticPanel()`).
  - Al finalizar la corrida, `renderTangoDiagnosticPanel()` pinta un banner visible si hay al menos un catalogo con outcome distinto de `ok`. Muestra badge (ERROR/VACIO), `process`, `Company`, `HTTP code`, `items_count`, `id_keys` vs `first_item_keys`, `error_message` y un `<details>` con el `raw_sample`.
  - Boton "Diagnostico crudo" wireado: hace POST a `/tango-diagnose`, parsea el payload y pinta en el mismo `#tango-diagnostic-panel` con toda la info del dump.
  - `escapeHtml()` para render seguro del HTML del banner.

## Por qué

El bug "el selector de Empresa Connect queda vacio despues de validar" aparecio reiteradas veces desde el 2026-03-26 (ver `docs/logs/2026-03-26_1156_connect_selectores.md` en adelante). La investigacion del `2026-03-28_2131_auditoria_bug_selectores_empresa_connect.md` identifico parte del problema, y los hotfixes 2246/2253/2256/2305/2316 fueron parches de sintoma sin atacar la causa raiz: **los dos `catch (\Exception)` silenciosos en `TangoApiClient::fetchCatalog()` y `::fetchRichCatalog()` tragaban TODO** — excepciones HTTP (401/403/500), timeouts de cURL, cambios de shape del JSON de Axoft — y devolvian `[]`.

Con eso, el endpoint respondia `{success: true, data: []}`, la UI pintaba "-- Seleccioná una empresa --" y no habia forma de saber la causa sin abrir DevTools en el momento exacto — que en la practica nadie hace porque el operador no es developer.

Esta iteracion aplica el principio defensivo ya registrado en `CLAUDE.md` del proyecto: **"Diagnostico persistente > DevTools"**. El diagnostico vive en el codigo, no depende de que el usuario abra DevTools.

## Impacto

- El selector de empresa y los demas catalogos Connect siguen funcionando igual cuando todo esta bien.
- Cuando algo falla o viene vacio, el operador ve un banner visible con:
  - Que proceso de Axoft se consulto (`process=1418` para empresas, etc.).
  - Con que `Company:` viajo el request.
  - Que HTTP code devolvio Axoft.
  - Cuantos items vinieron parseados.
  - Que `id_keys` buscamos y que `first_item_keys` devolvio Axoft (comparar ambos arreglos es lo que permite detectar cambios de shape).
  - Mensaje de error si lo hubo.
  - `<details>` colapsable con los primeros 500 chars del body crudo.
- El boton "Diagnostico crudo" permite al operador disparar un dump directo sin tener que ejecutar toda la cadena de "Validar Conexion" — util cuando ya se sabe que algo falla y solo se quiere inspeccionar el shape de `process=1418`.
- El token Axoft nunca viaja al frontend en ningun momento (se redacta en `scrubSensitiveHeaders()`).

## Decisiones tomadas

- No se cambio el contrato publico de `TangoApiClient::getMaestro*` — siguen devolviendo array como antes. El diagnostic es una feature lateral que solo consume el nuevo flujo de Config. Eso evita romper a `CommercialCatalogSyncService::run()` (caller externo que sincroniza catalogos comerciales del CRM de Presupuestos y asume array vacio en error).
- No se agrego un endpoint nuevo generico tipo `/tango-raw` que reciba `process` arbitrario — solo el diagnose apuntado a `process=1418` — por el riesgo de exponer procesos sensibles (precios, stock, clientes) a traves del endpoint de diagnostico.
- No se escribe a ningun log de filesystem — todo el diagnostic viaja por JSON y se pinta en pantalla. Evita ensuciar `storage/logs/` y mantiene el debug asociado al usuario que lo disparo.
- Se eligio `catch (\Throwable)` en lugar de `\Exception` porque las excepciones del dominio HTTP (`HttpException`, `UnauthorizedException`) podrian en algun futuro ser rebajadas a `Error` o variantes — con Throwable nos cubrimos.

## Validación

- `php -l` OK en los 3 archivos tocados (`TangoApiClient.php`, `EmpresaConfigController.php`, `routes.php`).
- Smoke test local: se abre `/mi-empresa/configuracion`, se carga URL + llave + token reales, se aprieta "Validar Conexion", el selector de empresas se puebla con datos. Sin diagnostico visible (outcomes `ok`).
- Smoke test local forzando error: se introduce un token invalido, se aprieta "Validar Conexion" — aparece banner rojo con outcome `error`, class `UnauthorizedException`, HTTP 401 y raw_sample del body de Axoft.
- Smoke test del boton "Diagnostico crudo" — dispara, responde con `resultData_list_count` y `first_item_keys`, todo visible.

## Pendiente

- No aplica — la iteracion es cerrada. Si en el futuro aparecen nuevos tipos de falla (ej: Axoft agrega un wrapper nuevo), el diagnostic ya va a capturarlo porque se basa en outcome + shape, no en errores especificos hardcodeados.
