# Store con toggle frontal y ofertas por articulo

## Fecha y tema
2026-03-28 19:53 - Mejora del Store con alternancia de vista, retorno contextual y ofertas comerciales por SKU.

## Que se hizo
- Se agrego un toggle frontal `?vista=categorias|catalogo` para alternar el modo de visualizacion del Store sin tocar rutas.
- Se incorporo en el detalle de producto un boton de vuelta contextual, preservando `vista`, `search`, `categoria` y `page` del recorrido previo.
- Se implemento la logica comercial de ofertas para Tiendas usando `precio_lista_1` como precio normal y `precio_lista_2` como precio promocional.
- Se creo la tabla `articulo_store_flags` y la migracion `database_migrations_store_flags.php` para guardar por `empresa_id + articulo_codigo_externo` si un producto se muestra en oferta.
- Se extendio el ABM de articulos de Tiendas con un switch `Mostrar como oferta en Store` y se clarifico el uso comercial de listas en configuracion y formulario.
- Se actualizo carrito y checkout para respetar el snapshot del precio promocional cuando el articulo entra como oferta.

## Por que
- El frente ya tenia categorias y catalogo, pero faltaba una forma clara de priorizar uno u otro segun el momento de compra.
- El detalle necesitaba una vuelta real al recorrido anterior para no cortar la experiencia comercial.
- La operatoria ya contaba con dos listas de precios y hacia falta una interpretacion simple: normal vs oferta, controlada localmente por articulo.

## Impacto
- El Store ahora puede abrir en modo categorias o catalogo y el usuario puede alternar desde el frente sin perder contexto.
- Los productos en oferta muestran badge comercial, precio anterior tachado y precio promocional vigente.
- El flag de oferta sobrevive a purgas o resincronizaciones porque queda atado al SKU y no al `id` local del articulo.
- CRM no usa esta logica y queda intacto, sin UI ni lecturas cruzadas de ofertas comerciales.

## Decisiones tomadas
- Se eligio una tabla local `articulo_store_flags` en lugar de una columna dentro de `articulos` para preservar el flag aun cuando se regenere el maestro.
- El toggle de vista se resolvio con query params simples para no introducir estado extra en configuracion por ahora.
- La vista de producto usa retorno contextual propio y no depende de `history.back()` para evitar comportamientos impredecibles.
- El precio promocional solo se activa si el switch local esta encendido y `precio_lista_2` tiene un valor valido menor al precio normal.
