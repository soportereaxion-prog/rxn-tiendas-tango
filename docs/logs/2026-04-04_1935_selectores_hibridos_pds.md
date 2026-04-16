# 2026-04-04 19:35 - Selectores Híbridos PDS y Llamadas

## Qué se hizo
- Se convirtió el sistema de sugerencias de búsqueda (`.rxn-search-suggestions`) en componentes híbridos permitiendo que luzcan y funcionen como un `<select>` tradicional de HTML con ratón y teclado, pero manteniendo la potencia dinámica del Typeahead/Ajax.
- Se actualizaron los JS (`crm-pedidos-servicio-form.js` y `CrmLlamadas/views/index.php`) para disparar eventos directos al hacer `click` o entrar en `focus`, sin requerir longitud de cadena de caracteres.
- Se optimizaron las consultas de `PedidoServicioRepository` (`findClientSuggestions`, `findArticleSuggestions`) y de los endpoints de backend, subiendo el techo de devolución a 50 resultados rápidos para poder scrollear por ellos.

## Por qué
- La dinámica de escribir siempre (al menos) 2 caracteres antes de recibir opciones reducía la agilidad operativa para listas lógicas y de alta repetición (como Clasificaciones PDS, o Clientes en "Vincular").
- Se buscaba conservar la coherencia estética del diseño cápsula Rxn Dark, por lo que el `max-height` solucionaba integralmente la UX.

## Impacto
- **Módulo PDS:** Interfaz mucho más liviana e interactiva de cara al data entry manual, impactando en selectores de *Artículos*, *Clientes* y *Clasificaciones*.
- **Módulo CrmLlamadas:** El proceso de `Vincular Llamada` a un cliente indexado en la base adopta la misma rapidez visual.
- **Frontend CSS:** Inyectada capacidad `overflow-auto` unificada en el esquema del theme.

## Decisiones tomadas
- Se mantuvieron las API sin rediseños drásticos. Sólo se relajó el validante estricto (`mb_strlen($term) < 2`), y en SQL se by-passearon las cláusulas restrictivas cuando se omite palabra.
- Se incluyó la meta actualización en el registro central para que viaje vía OTA y comunique la innovación a los operadores comerciales en la Vista Frontal de Store.
