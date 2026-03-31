# Backoffice con usuario visible y acceso rapido a tienda

## Que se hizo
- Se creo `app/shared/services/BackofficeContextService.php` para resolver desde sesion el usuario actual, su empresa y la URL publica de tienda cuando corresponde.
- Se agrego el componente reutilizable `app/shared/views/components/backoffice_user_banner.php` para mostrar arriba en el backoffice el saludo `Hola, {usuario}` y un acceso rapido a la tienda.
- Se integro el bloque en `app/modules/dashboard/views/admin_dashboard.php`, `app/modules/empresas/views/index.php`, `app/modules/empresas/views/crear.php`, `app/modules/empresas/views/editar.php`, `app/modules/Admin/views/smtp_global.php` y `app/modules/Admin/views/module_notes_index.php`.
- Se incorporo Bootstrap Icons en esas vistas para mantener el mismo lenguaje visual del saludo y del CTA de tienda.

## Por que
- Hacia falta identificar rapido con que usuario esta abierta la sesion dentro del backoffice.
- Tambien hacia falta exponer el enlace publico de la tienda de la empresa asociada al usuario actual sin obligar a buscar el slug manualmente.

## Impacto
- El backoffice ahora muestra de forma consistente quien esta logueado en la cabecera de las pantallas principales.
- Si la empresa en sesion tiene `Tiendas` habilitado, esta activa y posee `slug`, aparece un boton para abrir su tienda publica en una nueva pestaña.
- Si la empresa no tiene tienda publica disponible, el sistema informa el motivo con un texto corto en lugar de mostrar un link roto.

## Decisiones tomadas
- Se resolvio con un servicio liviano de lectura y un componente reusable para no duplicar condiciones en cada vista.
- La URL publica se expone solo cuando hay condiciones minimas reales: empresa valida, activa, modulo `Tiendas` habilitado y slug cargado.
- Se mantuvo el alcance en pantallas de backoffice y administracion, sin tocar el flujo operativo tenant ni la tienda publica.
