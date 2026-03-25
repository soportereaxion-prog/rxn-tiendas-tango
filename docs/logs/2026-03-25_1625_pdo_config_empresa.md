# [DB] — Bugfix PDO Strict Variables en Configuración de Empresa

## Contexto
Tras migrar el servidor de base de datos a un modo PDO libre de emulación (`PDO::ATTR_EMULATE_PREPARES => false`), afloró un error subyacente en el módulo "Configuración de la Empresa" (tenant).

## Problema
Al intentar grabar cualquier actualización sobre los parámetros de la empresa (Identidad, Tango Connect, SMTP), se desencadenaba una excepción PDO crítica de tipo:
`SQLSTATE[HY093]: Invalid parameter number: parameter was not defined`

## Análisis y Corrección
- **Causa Raíz:** En `EmpresaConfigRepository.php`, la función `save()` preparaba la rutina de _UPDATE_ enviando el parámetro vinculante `':lista_precio_1'` dentro del array de ejecución `$stmt->execute()`. Sin embargo, el texto plano de la secuencia SQL (`UPDATE empresa_config SET...`) omitía la definición de `lista_precio_1 = :lista_precio_1`.
- Al estar desactivada la emulación, PDO valida milimétricamente la correlación 1 a 1 entre la oración SQL y el array ejecutado, rechazando la asimetría y abortando la transacción entera.
- **Implementación:** Se inyectó explícitamente `lista_precio_1 = :lista_precio_1` en el query _UPDATE_.

## Impacto
Pérdida de bloqueo CeroDía. Se recupera el ABM íntegro de Configuraciones Tenant con seguridad Strict-Mode a base de datos.
