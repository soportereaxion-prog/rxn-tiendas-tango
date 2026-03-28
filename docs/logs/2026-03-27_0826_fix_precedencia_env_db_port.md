# [CORE] - Fix de precedencia .env para conexion de base de datos

## Que se hizo
- Se audito el error que mostraba `127.0.0.1:3300` al volver al listado de Clientes Web, pese a que el `.env` del proyecto define `DB_PORT=3307`.
- Se actualizo `app/config/database.php` para leer primero el archivo `.env` real del proyecto y usar `getenv()` solo como fallback secundario.
- Se mantuvo soporte para `DB_PASS=` vacio, evitando que el parseo del `.env` lo rompa o lo convierta en un valor inconsistente.

## Por que
- Bajo Apache/FCGI o entorno local WAMP puede existir una variable de entorno global `DB_PORT` que pise silenciosamente el valor del proyecto.
- La configuracion de base no debe depender de residuos del entorno del sistema cuando el repositorio ya define su propio `.env`.

## Impacto
- La app ahora resuelve consistentemente `127.0.0.1:3307` desde el `.env` del repo.
- Se evita que rutas como `Clientes Web -> Volver` fallen por leer un puerto ajeno (`3300`) heredado del host.

## Decisiones tomadas
- Se eligio una solucion simple y local en `app/config/database.php`, sin introducir librerias externas para dotenv.
- La prioridad queda asi: `.env` del proyecto -> variables de entorno del proceso -> defaults minimos.
