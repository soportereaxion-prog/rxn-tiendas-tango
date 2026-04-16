# Eliminación de redundancias UI y Memoria Persistente en RXN_LIVE

## Qué se hizo
1. **Unificación de Filtros**: Se eliminó por completo el archivo `public/js/rxn-advanced-filters.js` y la lógica de creación dinámica de "popovers" de filtro avanzado. Las opciones de base de datos se movieron directamente adentro del Dropdown Manager de `buildDiscreteDropdown()` en `dataset.php`. El embudo solitario del título (Header Row 1) fue suprimido. 
2. **Reinicio Verdadero**: Se modificó el botón "Reinicio Total" de ser un ancla HTML `<a>` pura, a ser un ejecutador JS `fullReset()`. Esta función itera sobre la API `sessionStorage` nativa del navegador, haciendo un purge explícito de cualquier clave que contenga `rxn_live_volatile_` o `rxn_live_state_` perteneciente al Dataset en curso.
3. **Limpieza de URL (Bug-fix)**: Se aseguró de instanciar un purgado del objeto literal JSON en Javascript mediante asignaciones base, para alinear la desconexión total.

## Por qué
1. Al tener dos iconos de filtro "embudo", uno reaccionando para abrir un Popover (Backend) y otro abriendo un Dropdown menu custom (Local), no solo se generaba desorden físico en el CSS de la UI perdiendo lugar valioso y solapado en la zona del footer "Totales", sino que conceptualmente mareaba al usuario acerca de por donde buscar. El usuario propuso combinar la selectbox avanzada "bajo" el control discreto. 
2. El botón de Reinicio Total recargaba limpiamente la variable GET `$view_id` forzando el reseteo desde lo que veía el servidor de Render, PERO, LocalStorage volvía a popular masivamente en el DOM el mismo gráfico y la misma columna seleccionadas, haciendo caso omiso de que la intención original era borrarlo hasta que vuelva a ser su forma original "base".
3. El botón intermedio creado iteraciones pasadas fallaba porque la URL se limpiaba, pero los objetos `flatFilters` quedaban precargados en memoria viva.

## Impacto
- Menor footprint de carga (se remueve archivo JS remoto con cientos de líneas).
- Funcionalidades combinadas y predecibles en la ventana de columna.
- UI más ordenada, limpia y clara a prueba de recargas parciales.

## Decisiones tomadas
- Inyectar el HTML directamente concatenado de opciones avanzadas encima de la selección de Checkboxes dentro del ya existente `<div class="dropdown-menu">` de Bootstrap. Esto delega toda la responsabilidad inteligente de visualización y z-index sobre el motor genético de UI base, eliminando código artesanal de posicionamiento.
