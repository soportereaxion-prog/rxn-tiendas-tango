# [CORE + CLIENTES WEB] - Blindaje por rechazo transitorio de conexion DB

## Que se hizo
- Se audito el error mostrado al guardar un cliente web y se reviso `D:\RXNAPP\3.3\logs\php_error.log`.
- Se confirmo que el fallo no provenia del formulario ni del `UPDATE`, sino de un rechazo de conexion PDO hacia MySQL en `127.0.0.1:3307`.
- Se reforzo `app/core/Database.php` con reintentos breves de conexion antes de abortar la peticion.
- Se mejoro el log tecnico y el mensaje final para indicar host y puerto implicados cuando el motor no responde.

## Por que
- El stacktrace atrapaba todo como `Error al conectar con la base de datos`, pero el motivo real era un `SQLSTATE[HY000] [2002]` por conexion denegada.
- En entornos WAMP locales esto puede pasar por arranque tardio del servicio MySQL o microcortes entre Apache y el motor.

## Impacto
- La aplicacion ahora tolera mejor rechazos transitorios al abrir PDO, reduciendo falsos negativos en acciones como guardar clientes.
- Si el motor sigue realmente caido, el mensaje resultante deja una pista mucho mas accionable (`host:puerto`) en vez de un error completamente ciego.

## Decisiones tomadas
- Se aplico un fix minimo en capa `core`, para beneficiar a todo el sistema y no solo al modulo de Clientes Web.
- No se modifico configuracion `.env` ni puertos de base: la auditoria mostro que el DSN configurado sigue siendo correcto y funcional cuando el servicio esta levantado.
