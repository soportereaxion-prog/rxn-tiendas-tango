# Fase 1 — Perfil de pedido Tango en EmpresaConfig

## Que se hizo
- Se reemplazo la configuracion operatoria de `lista_precio_1`, `lista_precio_2`, `deposito_codigo` y el input visible de `tango_pds_talonario_id` por un unico selector compartido `Perfil de pedido Tango` en `app/modules/EmpresaConfig/views/index.php`.
- Se agrego persistencia por area en `empresa_config` / `empresa_config_crm` para `tango_perfil_pedido_id`, junto con snapshot local opcional de codigo y descripcion (`tango_perfil_pedido_codigo`, `tango_perfil_pedido_nombre`).
- Se adapto `app/modules/EmpresaConfig/EmpresaConfigController.php` y `app/modules/Tango/TangoApiClient.php` para que la metadata de este flujo consuma solo el maestro de empresas y el catalogo `process=20020`.
- Se dejo preservada la data legacy en base para no romper consumidores existentes mientras la migracion funcional del payload se hace en otra fase.

## Por que
- La direccion acordada ya no quiere que el operador configure piezas comerciales sueltas que luego pueden desalinearse entre si.
- El perfil de pedido es la fuente mas coherente para avanzar hacia una futura migracion del payload Tango sin seguir expandiendo el camino tactico del talonario visible.

## Impacto
- Tiendas y CRM comparten ahora la misma UX searchable para elegir un perfil de pedido Tango.
- Cambiar la empresa Connect ya no dispara carga de listas/deposito en esta pantalla; solo recarga perfiles de pedido de la empresa elegida.
- Los consumidores legacy siguen funcionando con sus valores actuales porque esta fase no migra aun la generacion de pedidos ni el uso transaccional de listas/deposito/talonario.

## Decisiones tomadas
- Se guardo `ID_PERFIL` como campo canonico (`tango_perfil_pedido_id`) y se agrego snapshot liviano de codigo/descripcion para rehidratar la UI aunque Connect no responda.
- No se borraron columnas legacy ni se migraron payloads: primero se estabiliza la nueva fuente de seleccion y despues se reengancha la logica transaccional.
- Se actualizo `app/config/version.php` porque el cambio es visible para administradores y constituye una tajada funcional coherente publicada dentro del backoffice.
