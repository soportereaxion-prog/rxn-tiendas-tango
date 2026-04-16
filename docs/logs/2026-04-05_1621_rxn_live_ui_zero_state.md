# Corrección UX Filtros y Zero-State en RXN_LIVE

## Qué se hizo
1. **Fijación de Z-Index para Popovers de Filtro (`dataset.php`)**: Se eliminó la declaración dinámica `position: relative` de las celdas de filtros discretos en el thead secundario.
2. **Rescate Estructural en Búsqueda Vacía (`dataset.php`)**: Se modificó `renderPlana()` para construir los theads leyendo metadata desde `pivotMetadata` cuando `rawDatasetRows` llega con longitud cero. Se movió el banner de "Cero coincidencias de backend" al `<tbody>`.
3. **Botón Intermedio (Limpiar Filtros)**: Se agregó un botón transparente para limpieza de URL Params vía javascript en vez de botar por completo la configuración con el motor PHP. Se renombró el "Limpiar Dataset" a "Reinicio Total" con una apariencia más restrictiva.
4. **Agregado de Operadores Matemáticos**: Integrados `<` (Menor que) y `>` (Mayor que) en `rxn-advanced-filters.js` y `AdvancedQueryFilter.php` construyendo el bloque base de `SQLFragment`.

## Por qué
1. El usuario reportó (Imagen 4) que durante la expansión del filtro checkbox dropdown (Bootstrap Popper), una banda oscura solapaba visualmente por encima de los textos del select desplegado. Al establecerse `position: relative` de forma individual en cada cabecera adyacente, creaban contextos de apilamiento superiores invisibles que colisionaban con el desplegable posicionado de forma absoluta del `input-group`.
2. Las peticiones SQL que devolvían éxito pero arrojaban "0 records" (como buscar letras en un id numérico) daban al array `rawDatasetRows` longitud 0. `renderPlana` evaluaba la longitud e insertaba el mensaje al div cortando la ejecución temprana con `return`, abortando por completo la pintura del `<thead>`. Como el `<button>` para borrar el filtro resida bajo la jurisdicción visual del `<thead>`, el usuario se quedaba "encerrado" en una tabla vacía sin botones para restablecer la búsqueda.
3. Se deseaba tener reinicio estricto limitando sus opciones sin perder la Vista actualmente en uso (ej. sin romper las agrupaciones).
4. Solicitud directa de extender utilidad de filtros.

## Impacto
- Menús drop-down sin superposiciones en cabeceras.
- Tablas indestructibles; si la BD devuelve nulo, la tabla sostiene sus cabeceras y sus respectivos funnels habilitados para volver atrás.
- Mayores opciones comparativas.
- Controles de UI más finos.

## Decisiones tomadas
- Limpieza vía URL URLSearchParams en Javascript sin intervención de backend para la limpieza temporal.
- Uso de `pivotMetadata` (que viene completo para el dataset) como oráculo secundario en escenarios de vaciado de query.
