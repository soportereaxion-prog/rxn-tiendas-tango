# [ARTICULOS] - Boton y flujo de sincronizacion total

## Que se hizo
- Se agrego un nuevo boton `Sync Total` en `app/modules/Articulos/views/index.php`, ubicado entre `Purgar Todo` y `Sync Stock`.
- Se registro la nueva ruta `GET /mi-empresa/sync/todo` en `app/config/routes.php`.
- Se incorporo `TangoSyncController::syncTodo()` para disparar una sincronizacion completa con un solo redirect final al listado de articulos.
- Se extendio `TangoSyncService` con `syncTodo()` para encadenar `Articulos -> Precios -> Stock` y consolidar estadisticas de ejecucion.

## Por que
- La operatoria actual obliga a disparar tres sincronizaciones manuales por separado, con mas clics, mas espera y mas chances de dejar el catalogo a medio actualizar.
- El usuario pidio una accion unificada y visible para correr todo el circuito desde la misma botonera.

## Impacto
- La UI ahora ofrece una accion pesada pero directa para sincronizar maestro, precios y stock en secuencia segura.
- Si una etapa falla, la cadena aborta y se informa el error sin avanzar con pasos posteriores potencialmente inconsistentes.
- Se muestra tambien el contador `Sin Match Local` cuando la accion lo reporta, mejorando la lectura del resultado en pantalla.

## Decisiones tomadas
- Se mantuvo arquitectura simple: una ruta nueva, un metodo controller y un metodo service orquestador, sin jobs ni colas adicionales.
- El orden elegido fue `Articulos -> Precios -> Stock` porque precios y stock dependen de que el catalogo local exista primero.
- Como mejora de ejecucion, `syncTodo()` reutiliza una sola lectura de configuracion de empresa para las etapas de precios y stock, evitando trabajo redundante en la orquestacion.
- El boton se resolvio con un gradiente naranja intenso para diferenciar la accion compuesta y advertir visualmente que no es una sincro menor.
