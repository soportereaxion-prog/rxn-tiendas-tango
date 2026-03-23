# ESTADO ACTUAL

## módulos tocados

* módulo: core (Nombres de Autoloader alterados)
* módulo: **tango** (¡NUEVO E INAUGURADO!)
* módulo: infra (Capa nueva HTTP)

## módulos tocados

* módulo: **core** (Creación del Session Helper estático `Flash` para notificaciones asíncronas).
* módulo: tango (Controladores de Sincronización derivan a Vistas HTML usando Sess-States en vez de volcar JSON crudo).
* módulo: articulos (Incorporación de Toast Alerts a la grilla para capturar los Resultados Flash tras una Sincronización).
* módulo: empresa_config (Configuración Inyectada `cantidad_articulos_sync` al Core de Base de Datos y UI).

## decisiones

* Se escindió la lógica de Redes fuera de la lógica de Dominios. 
* El orquestador responsable de mezclar Contexto de Empresa con variables de Peticiones REST recaerá puramente en el `Service` de la entidad correspondiente (`TangoService`), nunca en el Controlador, logrando controladores livianos.
* Se instaló política de composición en lugar de Herencia para el HTTP Client. Las reglas de infra emplearán excepciones semánticas (ConfigurationException, ConnectionException, etc.).
* Se implementó el Patrón Mapper para decodificar los Arrays JSON crudos que arroja Connect, aislándolos de la entidad Artículo pura.
* La inserción de base de datos de los Artículos se maneja mediante `ON DUPLICATE KEY UPDATE` garantizando idempotencia directa desde MariaDB.
* La Sincronización de **Precios (Process 20091)** se incrustó sobre los propios `Artículos` sumando las directivas configurables `lista_precio_1` y `2`. Esto descarta la necesidad forzosa de un módulo Satélite y hace a los precios resilentes a Fallas (Actualizan Vía Silenciosa con matching de SKUs).
* Jamás se versionan secretos: La Inyección del Test de Vuelo se efectuó vía Script Volátil ya purgado.

## riesgos

* **Ajuste de Ruta Principal Connect**: Las pruebas arrojaron éxito estructural repeliendo la invocación desde cURL (`Could not resolve host`), comprobando el blindaje del `Catch` del Log. La URL usada `nexosync.tangonexo.com` es ilustrativa. Se necesita que el Licenciatario asigne su ruta real para operar.
* **PELIGRO DEPLOY LINUX**: Composer ya advirtió un Classmap conflictivo por incompatibilidad de Case-Sensitive en las carpetas base de módulos (Auth != auth). Debe ser solventado vía `git mv` temporal antes del pase final a productivo Unix. (Ver Log Refactor 02-11 para detalles técnicos).

## próximo paso

* Testear en Staging o Local mediante Postman/CURL con una URL Base cien por cien real validada por Axoft.
* Consolidar el refactoring de carpetas / Rename en GIT para estandarizado PSR-4 cross-platform.
* Avanzar clonando la arquitectura Sync hacia "Pedidos" y "Stock".
