# Corrección Crítica: SQL Injection involuntario en RXN_LIVE y Layout UI

## Qué se hizo
1. **Fijación de Controlador (`RxnLiveController.php`)**: Se agregaron las variables de control del frontend (`reset_filters`, `b_query`, `query`, `estado`, `razon_social`) a la exclusión explícita del pipeline de captura GET antes de ser enviadas a `RxnLiveService`.
2. **Mejora UI/UX (`dataset.php`)**: Se añadieron márgenes interiores estructurales (`pe-4` y `ms-2`) a las celdas de cabecera (`<th>`) dinámica y texto en la tabla plana.

## Por qué
1. Al vaciar todos los filtros avanzados desde la interfaz de popovers, el script introducía `?reset_filters=1` a la URL. El backend interpretaba erróneamente esto como una orden de buscar registros donde la columna virtual `reset_filters` se asemeje a `1`. Al no existir la columna, el motor SQL lanzaba un `Exception` letal de manera silenciosa que devolvía 0 registros. La página cargaba "limpia", pero creía que no existían registros por la falla de PDO, evaporando también los encabezados de tabla (el bug fantasma de la "tabla invisible").
2. El Rey notó que los iconos SVG (sortear, filtros y título) estaban superpuestos porque la clase `float-end` colisionaba físicamente ante una falta de espacio definido en un contenedor shrink-to-fit para títulos cortos (ej: "Código").

## Impacto
- Recuperada la estabilidad absoluta al despejar filtros avanzados.
- Tablas completamente funcionales.
- Mejor legibilidad.

## Decisiones tomadas
- Limpiar el `$_GET` a nivel de Controlador en vez de atrapar la excepción SQL para evitar cargar un proceso inválido al servicio.
- Utilizar Bootstrap native classes (`pe-4` y un wrapper en HTML) en lugar de agregar CSS extra a `rxn-advanced-filters.js` para mantener la flexibilidad del header.
