# [CLIENTES WEB + CONNECT] - Persistencia local first para overrides comerciales

## Que se hizo
- Se corrigio `app/modules/ClientesWeb/Controllers/ClienteWebController.php` para que guardar un cliente no vuelva a consultar Connect salvo que exista una accion remota explicita del usuario en esa misma edicion.
- Se ajusto `app/modules/ClientesWeb/views/edit.php` para que la pantalla cargue primero lo persistido en MySQL/MariaDB y no dispare sincronizacion automatica al entrar.
- Los selectores de `Condicion de venta`, `Lista de precios`, `Vendedor` y `Transporte` ahora pueden mostrar el valor local guardado aunque todavia no se hayan recargado los catalogos remotos.
- El panel lateral de estado se apoya primero en lo local y deja la resolucion/enriquecimiento contra Connect solo bajo demanda.
- Se alineo `app/modules/EmpresaConfig/views/index.php` con la misma regla: sin auto-hidratacion remota al entrar; Connect solo se consulta al validar.
- Se documento el patron general en `docs/architecture.md` bajo la regla `Local-First` para integraciones Connect.

## Por que
- La UI estaba pisando visualmente lo guardado porque al abrir la ficha volvian a cargarse defaults desde Connect.
- Eso hacia parecer que los overrides no persistian, aunque el problema real era de precedencia visual y resincronizacion implicita.

## Impacto
- Al guardar, los datos comerciales quedan persistidos localmente y al reabrir la ficha se ven desde BD, sin dependencia inmediata de Connect.
- Connect queda reservado para sincronizacion explicita via boton, que es el comportamiento deseado para primer ingreso y futuras ediciones.
- El mismo criterio ya queda asentado como norma para otras pantallas que dependan de Connect.

## Decisiones tomadas
- Se uso una bandera explicita de flujo remoto (`tango_remote_sync_requested`) en lugar de inferir sincronizacion por hidden fields ya precargados.
- No se agregaron columnas nuevas: en esta iteracion se priorizo persistencia local de IDs/codigos y una UX consistente local-first.
