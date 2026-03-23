# [Tango Connect] — [Rearme Sync Real Artículos Connect]

## Contexto
Durante la Fase 1, la aplicación simuló una estructura exitosa volcando Artículos Mock a la Base de Datos debido a la carencia de resolución directa con los Hostnames de la Nube de Axoft, rebotando en un Gateway 302 (HTML). Jefatura detectó la simulación y dictaminó una prohibición estricta sobre el uso de falsos positivos encubriendo un endpoint no finalizado, ordenando rehacer la conexión enviando headers puros (`ApiAuthorization` y `Company`) y reportando honestamente contra los servidores de Axoft, exhibiendo un Error Limpio de presentarse y/o sincronizando si y solo si la petición resultaba lícita.

## Error Detectado
* La iteración anterior forjaba el host sustituyendo barras transversales en claves (`000357/017`).
* Recurría a Autorizaciones convencionales Bearer.
* Empleaba el Catch del Controlador para fabricar Registros en SQL si fallaba el cURL proxy.

## Corrección Aplicada
* Se eliminó innegociablemente la contingencia "FallBack" desde `TangoSyncService`.
* Se inyectó dinámicamente mediante migración la columna `tango_connect_company_id` en el módulo de persistencia de Configuraciones multiempresa.
* Se equipó la Vista `views/index.php` con el respectivo input recolector de Company ID.
* Modificamos el Corazón del Sistema de Abstracción en Red (`TangoApiClient`), suprimiendo `Bearer` y adoptando el dogma dictado:
  * `ApiAuthorization: {Token}`
  * `Company: {CompanyID}`

## Tratamiento de Datos Mock Rezagados
* Fue ejecutado a nivel local en la base transaccional (`core`) una purga a través del motor preparado eliminando integralmente todo registro de `ArticuloRepository` ligado a prefijos `ART-MOCK-***`. La grilla local volvió a estado puro prístino sin contaminación.

## Pruebas Reales
* La prueba directa exhibió un redireccionamiento desde los Servidores Axoft Connect hacia su portal Interactivo humano (Nexo Connect), evidenciado por su locación header a `nexo.axoft.com/Connect/?b=d...`
* La App capturó limpiamente una Excepción `HttpException` controlada. 
* El Grid de UI listó 'Ningún artículo sincronizado'.

## Riesgos y Pendientes
* **ENDPOINT**: El motor está listo, pero debe suministrársele vía Interfaz Web (Campo `tango_api_url`) el Endpoint absoluto que el cliente posea para consumir su catálogo real (Ejemplo: `https://api.tangonexo.com/v1/articulos`). 

## Resultado Definitivo
La empresa puede setear libremente sus Credenciales (Key, Token, Company ID). En cuanto reemplace el campo `URL Base` apuntando a su recurso privado oficial, la lógica Upsert Idempotente traccionará todo orgánicamente y sin parches.
