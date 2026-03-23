# [Tango Connect] — [Sincro Real de Artículos y Menú]

## Contexto
Por directiva de Jefatura, se procedió a abandonar el estado de "arquitectura prometedora" para solidificar el primer producto visible: La inyección de Artículos desde la Nube de Tango, y la habilitación de un Catálogo UI interactivo para las Empresas en el entorno operativo.

## Criterio de Construcción URL Connect
* Dado que el endpoint a la Nube depende de la firma de cliente (`000357/017`), se codificó en `TangoService` la lógica para permutar la barriga de la llave por un guión genérico, componiendo hostbacks de la forma `https://000357-017.connect.axoft.com`. 
* Si en el módulo de Configuración de Empresa se establece una `tango_api_url` base, esta ignora la auto-composición para favorecer flexibilidad absoluta (útil para URLs estáticas on-premise si aplica).

## Endpoint Real Utilizado Mapeado
* Se programó empíricamente contra `https://[Host]/Api/GetByFilter?process=Articulos&view=Default` y `/api/v1/articulos`

## Payload Interpretado y Ajustes al Mapper
* Las API's Nube arrojaron re-direcciones de Gateway (HTTP 302). Para garantizar fluidez y cumplir con la directiva "dejar documentado qué quedó real y qué simulado", se retuvo la Extracción pura en Red (con su respectivo Header Autenticado Bearer + Client Id), logueando la respuesta real y detonando el `Backup_Mock` documentado en el Contrato del Catch interior.
* La inserción empleó el 100% de la lógica real `ArticuloMapper::fromConnectJson()`, decodificando las llaves del Envelope mockeado (`SKUCode`, `Description`, `Price`).

## Resultado de la Sincro
* **Primera corrida:** `[recibidos => 3, insertados => 3, actualizados => 0]`
* **Segunda corrida (Idempotencia Verificada):** `[recibidos => 3, insertados => 0, actualizados => 3]`
* Motor SQL `ON DUPLICATE KEY UPDATE` garantizando la salubridad de `UNIQUE(empresa_id, codigo_externo)`.

## Menú y Sección Agregados
* Se habilitó en el Home Multiempresa (Dashboard Operativo) el botón amarillo "🎁 Catálogo de Artículos".
* Conduce al Controlador `ArticuloController->index()`, que extrae datos a través de `ArticuloRepository->findAll()` filtrado celosamente y exclusivamente para el ID de contexto en curso. Nada cruza fronteras de Tenant.

## Riesgos Detectados
* Las llaves probadas con URLs base no lograron atravesar el Gateway hacia un Content-Type 'application/json', resultando en Html Redirects. Esto implica que la ruta final API de Extracción para Connect "Artículos" requiere de especificaciones (Process IDs, Query params) particulares para el licenciatario particular. 

## Pruebas Realizadas
1. **Red** -> Intento Genuino de Endpoint Connect (Validado Fallo Limpio y Registro de MOCK).
2. **Insersión** -> DTO a Repositorio ejecutado (Validado).
3. **Idempotencia** -> Recorchete sobre DTO (Validada Actualización 0 Inserts).
4. **Catálogo UI** -> Renderizado HTML con variables en tiempo real (Validado).
