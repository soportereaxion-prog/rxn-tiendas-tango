# Fix metadata por empresa Connect y buscador del selector

## Que se hizo
- Se corrigio `app/modules/EmpresaConfig/EmpresaConfigController.php` para que el catalogo de empresas Connect se consulte siempre con `Company: -1`, pero las listas de precios y depositos solo se pidan cuando ya existe una empresa Connect seleccionada.
- Se evito asi el falso exito con arrays vacios cuando Connect devolvia `succeeded=false` para listas/depositos al consultar sin empresa efectiva.
- Se reemplazo el primer selector visible de empresa en `app/modules/EmpresaConfig/views/index.php` por una entrada con sugerencias locales sobre el catalogo Connect ya cargado, manteniendo el `<select>` real oculto para el POST.
- La seleccion de una sugerencia dispara automaticamente la recarga de `Lista de Precio 1`, `Lista de Precio 2` y `Deposito`, sin boton de busqueda.

## Por que
- Operativamente los selectores dependientes ya reaccionaban al cambio, pero no se llenaban porque el backend seguia intentando resolver catalogos comerciales incluso cuando todavia no habia una empresa Connect valida en el request.
- El catalogo de empresas puede ser largo; el select nativo hacia lenta la busqueda manual y no seguia el patron UX ya usado en articulos y otros CRUDs.

## Impacto
- Validar Connect sin empresa elegida ahora trae solo el maestro de empresas y deja claro que falta elegir una para resolver listas y deposito.
- Elegir una empresa desde el nuevo buscador carga los catalogos dependientes correctos para esa empresa.
- Tiendas y CRM quedan alineados porque ambos reutilizan el mismo modulo `EmpresaConfig`.

## Decisiones tomadas
- Se mantuvo un enfoque simple: filtrado local en frontend para empresas ya cargadas, sin sumar un endpoint nuevo de sugerencias remotas.
- El `<select>` real no se elimino; queda oculto y sigue siendo la fuente del valor enviado al backend para no romper compatibilidad con la persistencia actual.
