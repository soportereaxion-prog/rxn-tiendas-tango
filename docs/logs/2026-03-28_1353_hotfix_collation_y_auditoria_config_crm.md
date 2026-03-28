# Hotfix de collation y auditoria de configuracion CRM

## Fecha y tema
2026-03-28 13:53 - Correccion de error SQL por collation al abrir Articulos/Categorias y relevamiento de independencia entre Configuracion de Tiendas y CRM.

## Que se hizo
- Se corrigieron los joins por SKU entre `articulos`/`crm_articulos` y sus tablas de mapeo de categorias usando collation explicita compatible (`utf8mb4_unicode_ci`) en consulta.
- Se aplico el ajuste en `app/modules/Articulos/ArticuloRepository.php` y `app/modules/Categorias/CategoriaRepository.php` para evitar el `Illegal mix of collations` al navegar modulos relacionados.
- Se auditó el modulo `EmpresaConfig` para medir el desacople real entre Tiendas y CRM.

## Por que
- Las tablas nuevas de categorias quedaron con una collation distinta a la de articulos existentes y MySQL rechazaba los joins por igualdad entre `codigo_externo` y `articulo_codigo_externo`.
- El usuario pidio verificar si la Configuracion de Tiendas y CRM ya eran independientes o si aun comparten persistencia/logica.

## Impacto
- Articulos y Categorias vuelven a poder consultar datos sin disparar error fatal por collation mezclada.
- El fix es compatible con la separacion actual de Articulos CRM porque usa el mismo repositorio dinamico.
- La auditoria confirma que CRM aun reutiliza la misma configuracion base del tenant (`empresa_config`) y que la independencia completa todavia no esta implementada.

## Hallazgos sobre Configuracion
- Hoy Tiendas y CRM entran por rutas distintas, pero ambas persisten sobre `empresa_config`.
- La vista de CRM solo cambia textos y rutas; no cambia fuente de datos.
- Para independizar sin romper el store ni Tiendas, el camino minimo recomendado es crear una tabla propia para CRM (por ejemplo `empresa_config_crm`) y hacer que `/mi-empresa/crm/configuracion` use repositorio/servicio propios.
- En una segunda etapa habria que desacoplar lecturas directas a `empresa_config` que hoy siguen apareciendo en integraciones y fallbacks de articulos.
