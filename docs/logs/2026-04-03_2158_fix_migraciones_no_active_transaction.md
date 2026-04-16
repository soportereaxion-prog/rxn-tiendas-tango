# Solución a Error de Transacción en Migraciones (PDO)

## Qué se hizo
Se modificó la clase `MigrationRunner` (`app/core/MigrationRunner.php`) para capturar y tolerar la excepción `PDOException` con el mensaje "There is no active transaction" tanto al intentar realizar `commit()` como al hacer `rollBack()`.

## Por qué
El motor de migraciones (`MigrationRunner`) envolvía la ejecución de cada script de migración dentro de una transacción general usando `$pdo->beginTransaction()`.
Sin embargo, diversos motores de bases de datos, y en particular **MySQL / MariaDB**, ejecutan un COMMIT implícito de manera automática cuando reciben comandos DDL (Data Definition Language) como `CREATE TABLE`, `ALTER TABLE`, o `DROP TABLE`.

Cuando la migración contenía dichos comandos y finalizaba correctamente, el paso siguiente en `MigrationRunner` llamaba a `$pdo->commit()`. Pero, como la transacción ya había sido cerrada implícitamente por el motor de BD al ejecutar el DDL, PHP (PDO) lanzaba la excepción indicando que no existía ninguna transacción activa. Esto causaba un "falso fallo" que frenaba la ejecución de más migraciones.

## Impacto
* Ahora las migraciones que contengan código estructural (DDL) se completan correctamente sin provocar falsos negativos en el reporte de finalización.
* El mecanismo sigue siendo seguro, ya que continúa deteniendo la ejecución de las migraciones y devolviendo un error general ante otras fallas de sintaxis o ejecución SQL (como un constraint violado).

## Decisiones tomadas
* En lugar de quitar el bloque `beginTransaction()`, se optó por preservarlo por su utilidad para envolver migraciones transaccionales (DML, ej: insertando flags o registros), y manejar expresamente la excepción por la ausencia de transacción activa (que en un caso exitoso pre-DDL significa simplemente que ya se asimiló el cambio).
* Esta solución es acorde a la directiva de no introducir sobreingeniería en el arranque mínimo y evolutivo del proyecto (y es la práctica tradicional en drivers PDO/MySQL).
