# ESTADO ACTUAL

## módulos tocados

* módulo: core (Nombres de Autoloader alterados)
* módulo: **tango** (¡NUEVO E INAUGURADO!)
* módulo: infra (Capa nueva HTTP)

## módulos tocados

* módulo: tango (Extirpación Mock, Refactor Cliente Connect endpoint `Get?process=87` & Paginación Interna Cero).
* módulo: **articulos** (Mapper adaptado al Array Tango Nativo, limpieza transaccional de registros contaminados).
* módulo: empresa_config (Adición de Columna Empresa ID de Connect, Refactoreo Service).

## decisiones

* Se escindió la lógica de Redes fuera de la lógica de Dominios. 
* El orquestador responsable de mezclar Contexto de Empresa con variables de Peticiones REST recaerá puramente en el `Service` de la entidad correspondiente (`TangoService`), nunca en el Controlador, logrando controladores livianos.
* Se instaló política de composición en lugar de Herencia para el HTTP Client. Las reglas de infra emplearán excepciones semánticas (ConfigurationException, ConnectionException, etc.).
* Se implementó el Patrón Mapper para decodificar los Arrays JSON crudos que arroja Connect, aislándolos de la entidad Artículo pura.
* Se materializó la tabla `tango_sync_logs` para brindar trazabilidad forense a las integraciones masivas.
* La inserción de base de datos de los Artículos se maneja mediante `ON DUPLICATE KEY UPDATE` garantizando idempotencia directa desde MariaDB.
* Jamás se versionan secretos: La Inyección del Test de Vuelo se efectuó vía Script Volátil ya purgado.

## riesgos

* **Ajuste de Ruta Principal Connect**: Las pruebas arrojaron éxito estructural repeliendo la invocación desde cURL (`Could not resolve host`), comprobando el blindaje del `Catch` del Log. La URL usada `nexosync.tangonexo.com` es ilustrativa. Se necesita que el Licenciatario asigne su ruta real para operar.
* **PELIGRO DEPLOY LINUX**: Composer ya advirtió un Classmap conflictivo por incompatibilidad de Case-Sensitive en las carpetas base de módulos (Auth != auth). Debe ser solventado vía `git mv` temporal antes del pase final a productivo Unix. (Ver Log Refactor 02-11 para detalles técnicos).

## próximo paso

* Testear en Staging o Local mediante Postman/CURL con una URL Base cien por cien real validada por Axoft.
* Consolidar el refactoring de carpetas / Rename en GIT para estandarizado PSR-4 cross-platform.
* Avanzar clonando la arquitectura Sync hacia "Pedidos" y "Stock".
