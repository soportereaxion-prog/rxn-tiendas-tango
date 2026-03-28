# Categorias en Store - MVP local-first

## Fecha y tema
2026-03-28 12:48 - Incorporacion de categorias comerciales para ordenar articulos y habilitar navegacion publica por rubros.

## Que se hizo
- Se creo el modulo tenant `Categorias` con controlador, servicio, repositorio y vistas CRUD simples.
- Se agrego soporte de persistencia para `categorias` y `articulo_categoria_map`, junto con el script `database_migrations_categorias.php` y el DDL base en `database/schema.sql`.
- Se extendio `Articulos` para asignar una categoria por SKU desde el formulario de edicion y para mostrar/filtar la categoria en el listado backoffice.
- Se extendio el store publico con bloque "Comprar por categorias", links rapidos, filtro `?categoria=slug` y badges de categoria en listado y detalle.
- Se ajusto la invalidez de cache del catalogo para contemplar categorias publicas y cambios de asignacion.

## Por que
- El ecommerce ya tenia catalogo, carrito y checkout, pero no un concepto operativo de categorias.
- Se necesitaba mejorar la entrada al catalogo sin rediseñar la arquitectura actual ni acoplarse prematuramente a Tango.
- La implementacion toma la referencia funcional de una tienda externa, copiando lo util: acceso rapido por categorias, tarjetas visuales y filtro amigable.

## Impacto
- El tenant ahora puede crear categorias propias y ordenar el catalogo para el frente publico.
- La clasificacion se guarda por `empresa_id + codigo_externo`, por lo que sobrevive a purgas o resincronizaciones del maestro de articulos.
- El store mantiene carrito y checkout intactos; solo cambia la navegacion y el filtrado del catalogo.
- Se agrega una nueva migracion obligatoria para ambientes existentes.

## Decisiones tomadas
- Se eligio categoria plana unica por articulo en esta etapa; no hay subcategorias ni multi-categoria.
- Se mantuvo criterio local-first: las categorias no vienen de Tango en el MVP.
- Se uso `articulo_categoria_map` en lugar de `articulos.categoria_id` para no depender de IDs locales volatiles.
- Se asume estabilidad operativa de `codigo_externo` por empresa como ancla de la clasificacion local.
- Se invalida cache de catalogo y cache de categorias visibles cuando cambian articulos, categorias o syncs relacionados.
