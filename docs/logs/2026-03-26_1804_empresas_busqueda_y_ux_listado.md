# Empresas - Busqueda y UX del listado

## Que se hizo
- Se extendio el listado de `empresas` para aceptar filtros GET mediante `search` y `field`.
- Se agrego soporte de busqueda para `all`, `codigo`, `nombre`, `slug`, `razon_social` y `cuit` en `EmpresaRepository`, `EmpresaService` y `EmpresaController`.
- Se actualizo `app/modules/empresas/views/index.php` con toolbar de filtros, contador total/filtrado, columna `slug`, estado visual mas claro, tabla responsive, empty state y accion para limpiar filtros.
- Se sumo autosubmit server-rendered con debounce al escribir y submit inmediato al cambiar el campo de busqueda, sin AJAX ni dependencias nuevas.
- Se ajusto la UX para que el estado de "filtros activos" y el contador de coincidencias solo aparezcan cuando existe un termino de busqueda real.

## Por que
- El CRUD base de empresas necesitaba una forma simple de localizar registros sin cambiar la arquitectura actual del backoffice.
- El listado era funcional pero corto para operar varias empresas desde administracion.

## Impacto
- Los administradores RXN ahora pueden filtrar rapidamente el listado sin salir del flujo server-rendered existente.
- Se mantiene el enfoque multiempresa del modulo: solo mejora el acceso al listado central, sin mezclar datos ni tocar rutas o permisos.

## Decisiones tomadas
- Se uso `LIKE` con whitelist de campos y placeholders diferenciados para mantener compatibilidad con PDO nativo al buscar en multiples columnas.
- El total general se obtiene con `COUNT(*)` y el total filtrado se calcula desde el resultado renderizado para evitar sobrearquitectura.
- Se conservaron `AuthService::requireRxnAdmin()` y las rutas actuales.
