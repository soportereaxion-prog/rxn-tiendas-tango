# Servicios area-aware para Tiendas y CRM

## Fecha y tema
2026-03-28 14:14 - Preparacion de servicios compartidos para resolver configuracion por area (`tiendas` / `crm`).

## Que se hizo
- Se agrego soporte `forArea()` en `EmpresaConfigRepository` y `EmpresaConfigService` para reutilizar la misma logica con la tabla correcta segun entorno.
- Se preparo `MailService` con constructor inyectable y factories por area, manteniendo por defecto el comportamiento actual de Tiendas.
- Se volvió `TangoService` area-aware para leer configuracion desde `empresa_config` o `empresa_config_crm` segun el area explicitamente indicada.
- Se volvió `TangoSyncService` area-aware para elegir el `ArticuloRepository` correcto (`articulos` o `crm_articulos`) y la configuracion del entorno correspondiente.
- Se evitó la autodeteccion por URL/referer dentro de los servicios para no romper CLI, cron o llamados internos futuros.

## Por que
- La fase anterior separó la persistencia de configuracion, pero algunos servicios compartidos todavia nacian atados implícitamente a Tiendas.
- Era necesario dejar preparada la capa de servicios para que CRM pueda consumir su propia configuracion cuando se habiliten integraciones reales.

## Impacto
- Tiendas no cambia su comportamiento actual: todo sigue entrando por default a `empresa_config`.
- CRM ya puede instanciar servicios de Tango o Mail con scope propio sin contaminar la configuracion de Tiendas.
- `TangoSyncService` ya está listo para operar sobre `crm_articulos`, aunque todavía no se habilitaron rutas visibles de sync CRM.

## Decisiones tomadas
- Se eligió resolución explícita por área en constructor/factory, no inferencia automática desde request.
- No se tocaron aún `StoreResolver`, `PedidoWebController` ni `ClienteWebController` porque siguen perteneciendo al circuito Tiendas.
- No se expusieron rutas nuevas de sync CRM para evitar abrir funcionalidad incompleta en el árbol operativo visible.
