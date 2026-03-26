# Ajuste Bisturí — Telemetría Baja de HTTP CURL

## Antecedentes
Tras haber inyectado el dumper pasivo en el Controller, se documentó que Axoft Connect estaba respondiendo `status: 200, data: null` en el esquema de Producción para los endpoints de metadatos `2941` y `984`.
Jefatura certificó que enviando idéntico contrato (`pageSize=10`, `pageIndex=0`, `view=`) a través de un probador externo, la API entrega los datos.

## Hipótesis Técnica
El componente `ApiClient` nativo de Tienda podría estar mutilando parámetros vacíos (`view=''`) o formateando incorrectamente los Headers (`ApiAuthorization`) durante la construcción de la URL o el volcado de Array a cURL.

## Solución Ejecutada
1. **Introspección Core HTTP:**
   Se modificó `App\Infrastructure\Http\ApiClient` inyectándole la capacidad de registrar en memoria el estado real pre-vuelo (Pre-Flight) de su requerimiento: URL final procesada (Post `http_build_query`), Métodos, y Headers de la capa HTTP.
2. **Elevación:**
   `TangoApiClient` ahora lee esta trazabilidad (su variable nativa `$client->debugLastRequest`) y la expone al Frontend.
3. **Persistencia Pasiva:**
   El controlador `EmpresaConfigController` atrapa esta variable y la empaqueta dentro del `debug_selectores_connect.json` bajo la clave `HTTP_REQUEST_TRACE`.

## Uso Operativo
Al clickear "Validar Conexión" y revisar posteriormente el archivo `logs`, Jefatura podrá comparar milimétricamente el `CURLOPT_URL` generado por la Tienda contra el endpoint de Postman exitoso y descubrir la discrepancia protocolar exacta.
