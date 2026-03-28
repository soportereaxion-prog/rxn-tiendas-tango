# [CRM/CLIENTES] - Implementacion de Clientes CRM independientes

## Que se hizo
- Se adapto `ClienteWebRepository` y `ClienteWebController` para resolver el entorno (`tiendas` o `crm`) dinamicamente, igual que Articulos.
- Se creo la capacidad de generar la tabla `crm_clientes` de manera automatizada bajo el mismo esquema de `clientes_web`.
- Se agrego la tarjeta `Clientes CRM` al `crm_dashboard.php` y se generaron sus rutas.
- Se modificaron las vistas `index.php` y `edit.php` de clientes para reutilizarlas con variables dinámicas pasadas desde el controlador (`$basePath`, `$dashboardPath`, etc.).
- Se modifico `PedidoServicioRepository` y `PedidoServicioController` para que consuman `crm_clientes` al sugerir y buscar clientes, actualizando `cliente_fuente` a `'crm_clientes'`.

## Por que
- El usuario solicito replicar el modelo de clientes en el entorno CRM, igual que se habia hecho previamente con Articulos.
- La implementacion debia conservar la misma UX ya validada (buscadores server-rendered, sugerencias en vivo, ficha de edicion de integracion local-first).
- Se debia lograr aislar los clientes del ERP que operan en Tiendas de los que operan en CRM.

## Impacto
- El CRM cuenta con un directorio de Clientes Web separado.
- Los Pedidos de Servicio de CRM ahora conectan con clientes propios del entorno CRM y guardan el snapshot adecuado.
- La base de datos escalo de manera limpia, sin replicar todo el codigo MVC para clientes y manteniendo un mantenimiento centralizado.

## Decisiones tomadas
- Se reutilizaron las vistas `index.php` y `edit.php` de `ClientesWeb`, inyectando `$ui` (`pageTitle`, `$basePath`, etc.) desde un `buildUiContext` en el controller, evitando triplicar vistas.
- Se agrego un metodo `ensureSchema` en `ClienteWebRepository` para que si se instancia con `forCrm()` como true, este revise/cree la tabla `crm_clientes` en MySQL automaticamente.