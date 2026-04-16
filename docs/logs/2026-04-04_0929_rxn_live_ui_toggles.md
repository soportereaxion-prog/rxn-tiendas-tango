# Modificaciones RXN LIVE - DataSet UI (Toggles de Paneles)

## Fecha y Cambio
- **Fecha:** 2026-04-04 09:29
- **Versión:** 1.1.52
- **Cambio:** Implementación de toggles para control espacial en vistas Dataset.

## Qué se hizo
- Se agregaron dos botones ("Ocultar Gráfico" y "Ocultar Tabla") a la barra de controles junto a los botones de Filtrar y Limpiar en `app/modules/RxnLive/views/dataset.php`.
- Se programó la lógica `toggleViewSection()` permitiendo apagar secciones.
- Cuando una sección es apagada, la otra automáticamente adopta la clase `col-lg-12` para aprovechar el 100% del ancho del viewport disponible.
- Se agregó el relanzamiento asíncrono de eventos `resize` a la ventana para forzar que el SVG/Canvas de `Chart.js` abarque correctamente el nuevo espacio extendido.

## Por qué
- Para permitir a los analistas utilizar todo el ancho del monitor cuando se enfocan únicamente leyendo los resultados planos extendidos (sábana) o al presentar los gráficos analíticos en pantallas de proyección.
