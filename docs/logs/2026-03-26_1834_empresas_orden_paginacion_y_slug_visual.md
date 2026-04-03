# Empresas - Orden, paginacion y ajuste visual de slug

## Que se hizo
- Se extendio el CRUD de `empresas` para aceptar orden server-rendered por `id`, `codigo`, `nombre`, `slug`, `razon_social`, `cuit` y `activa` usando GET con `sort` y `dir`.
- Se agrego paginacion server-rendered de 10 registros por pagina con parametro `page`, manteniendo la busqueda existente por `search` y `field`.
- Se ajusto el contador del listado para distinguir `total` general y `filteredTotal` real, sin depender de la cantidad de items de la pagina actual.
- Se actualizo la vista `app/modules/empresas/views/index.php` para conservar filtros y orden en headers clickeables, paginacion y autosubmit del buscador, reseteando pagina al escribir o cambiar el campo.
- Se simplifico la visual del slug en `app/modules/EmpresaConfig/views/index.php` removiendo el prefijo duro `/rxn_suite/public/` y dejando solo el valor del slug alineado a la izquierda.
- Se registro el entorno local de referencia en `docs/estado/current.md`: Wampserver 3.3.7 x64, Apache 2.4.62.1, PHP 8.3.14, MySQL 9.1.0, MariaDB 11.5.2, DBMS default `mysql`.

## Por que
- El listado de empresas ya permitia buscar, pero necesitaba navegacion operativa minima para escalar sin salir del esquema PHP server-rendered.
- El prefijo visual fijo del slug agregaba ruido y mezclaba una ruta local del proyecto con el valor real que el usuario necesita reconocer.

## Impacto
- El administrador RXN puede ordenar columnas y recorrer resultados paginados sin perder el contexto de filtros actuales ni introducir AJAX nuevo.
- La pantalla de configuracion de empresa muestra el slug de forma mas limpia y consistente con el alcance actual de solo lectura.
- La documentacion queda alineada con la iteracion y el entorno local tomado como referencia tecnica.

## Decisiones tomadas
- Se mantuvo `AuthService::requireRxnAdmin()` y las rutas actuales sin cambios de arquitectura.
- Se uso whitelist cerrada para columnas ordenables y para la direccion `asc|desc`, evitando interpolaciones abiertas en SQL.
- El limite de paginacion se fijo en 10 por pagina como valor simple y razonable para esta etapa.
- La busqueda conserva `sort` y `dir`, pero reinicia `page` cuando cambia el termino o el campo, segun el alcance definido por Lumi.
