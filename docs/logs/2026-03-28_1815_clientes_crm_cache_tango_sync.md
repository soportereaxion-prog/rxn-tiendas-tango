# [CRM/CLIENTES] - Rehacer Clientes CRM como cache local sincronizable de Tango

## Que se hizo
- Se reemplazo el wiring equivocado de `Clientes CRM` que reutilizaba `ClienteWebController` y `ClienteWebRepository` por un modulo dedicado `CrmClientes` con controlador, repositorio, vistas server-rendered y tabla local `crm_clientes`.
- Se agrego `Sync Clientes` para CRM usando Tango/Connect `process=2117`, con paginacion remota, upsert local, flash de metricas y trazabilidad en `tango_sync_logs`.
- Se ajustaron rutas CRM, dashboard y `Pedidos de Servicio` para que consuman la nueva cache local `crm_clientes` y sus IDs Tango internos al enviar PDS.
- Se actualizo la release visible, `docs/estado/current.md` y el historial para dejar trazabilidad del cambio funcional.

## Por que
- El modulo anterior habia interpretado mal el objetivo y replicaba el ABM manual de `ClientesWeb`, cuando el pedido real era un espejo operativo del patron `Articulos CRM` pero con fuente Tango clientes.
- CRM necesitaba una base local cacheada/sincronizable para listar, buscar, sugerir y reutilizar clientes en `Pedidos de Servicio` sin depender de una busqueda remota interactiva al editar.

## Impacto
- `Clientes CRM` ahora opera sobre `crm_clientes` como cache local propia del entorno y ya no contamina el circuito de `Tiendas/ClientesWeb`.
- `Pedidos de Servicio CRM` sugiere y valida clientes desde la nueva tabla local, conservando `cliente_fuente = crm_clientes` y mejores datos para el payload hacia Tango.
- El dashboard CRM muestra un modulo coherente con la realidad operativa actual: articulos y clientes cacheados desde Tango con syncs propios.

## Decisiones tomadas
- Se mantuvo un diseño simple: CRUD server-rendered, edicion local minima y sync separado solo para clientes, sin mezclarlo con `syncTodo`.
- La tabla `crm_clientes` conserva columnas minimas pedidas y suma IDs comerciales Tango ya usados por PDS para no romper el envio al ERP.
- El bootstrap del repositorio tolera una `crm_clientes` previa creada con el enfoque errado, agregando/normalizando columnas en vez de forzar una migracion destructiva.
