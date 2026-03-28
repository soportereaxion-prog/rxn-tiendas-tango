# Split de Configuracion Tiendas / CRM - MVP

## Fecha y tema
2026-03-28 13:53 - Separacion operativa de la configuracion entre Tiendas y CRM.

## Que se hizo
- Se agrego persistencia propia para CRM mediante `empresa_config_crm` y el script `database_migrations_empresa_config_crm.php`.
- Se volvieron area-aware `EmpresaConfigRepository`, `EmpresaConfigService` y `EmpresaConfigController` para que Tiendas siga usando `empresa_config` y CRM use `empresa_config_crm`.
- Se ajusto la vista `app/modules/EmpresaConfig/views/index.php` para que CRM oculte branding publico de tienda y muestre una configuracion operativa propia.
- Se conecto `crm_articulos` al nuevo origen de fallback visual mediante `app/modules/Articulos/ArticuloRepository.php`.
- Se mantuvo sin cambios el consumo de configuracion del lado Store, clientes web, pedidos web y servicios compartidos de Tiendas.

## Por que
- El sistema ya tenia dashboards y rutas separadas para Tiendas y CRM, pero la configuracion seguia persistiendo en una sola tabla compartida.
- Se necesitaba evitar que un guardado desde CRM pisara valores del circuito Tiendas.
- La implementacion se hizo con el minimo riesgo posible, aislando CRM sin romper el store ni los modulos operativos actuales de Tiendas.

## Impacto
- Guardar desde `/mi-empresa/crm/configuracion` ya no modifica la configuracion de `/mi-empresa/configuracion`.
- El branding publico, slug y visual de tienda siguen administrandose unicamente desde Tiendas.
- CRM ahora dispone de su propia base para datos generales, Tango, SMTP y fallback visual.
- `crm_articulos` puede resolver su imagen fallback desde la configuracion CRM sin depender de `empresa_config`.

## Decisiones tomadas
- Se reutilizo la entidad `EmpresaConfig` para no duplicar modelo innecesariamente.
- Se eligio tabla dedicada (`empresa_config_crm`) en lugar de meter `scope` en `empresa_config`, para reducir impacto sobre consumidores existentes.
- No se desacoplaron todavia `StoreResolver`, `MailService`, `TangoService`, `PedidoWebController` ni `ClienteWebController`, porque siguen siendo parte del circuito Tiendas.
- La migracion copia datos iniciales desde `empresa_config` para evitar que CRM arranque vacio en ambientes ya operativos.
