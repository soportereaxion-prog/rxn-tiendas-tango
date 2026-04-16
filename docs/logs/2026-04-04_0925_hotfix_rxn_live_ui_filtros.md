# Modificaciones RXN LIVE - DataSet PDS Tiempos (Hotfix filtros en Vista Plana)

## Fecha y Cambio
- **Fecha:** 2026-04-04 09:25
- **Versión:** 1.1.50
- **Cambio:** Hotfix de lógica UI para filtros locales en vista plana de RXN_LIVE.

## Qué se hizo
- Se corrigió el archivo `app/modules/RxnLive/views/dataset.php` (Frontend JS).
- Se modificó la función Javascript `renderPlana()`.
- En vez de reemplazar todo el contenedor de la tabla por un `<div>` cuando los filtros locales arrojan 0 resultados, ahora se renderiza normalmente la cabecera (`thead`) con los inputs de búsqueda y la advertencia de "No hay resultados" se inyecta dinámicamente como una fila (`tr > td[colspan]`) dentro del `tbody`.
- Se corrigió chequeo de seguridad inicial usando `rawDatasetRows` para diferenciar cuando un set de datos viene verdaderamente vacío del backend vs cuando queda vacío localmente por aplicación de filtros.

## Por qué
- La lógica anterior destruía el elemento HTML `<table>` completo (incluyendo su `thead` con los `<input>` correspondientes) si el usuario tipeaba algo que no hacía match con nada, lo que provocaba que el usuario se quedara atascado sin la posibilidad de borrar la letra o el término que filtró salvo que recargue la página completa.

## Impacto
- Mejora de usabilidad directa sin tocar backend. El filtrado de columnas en la matriz plana ahora preserva las cajas de búsqueda en todo momento, de manera idéntica a cómo se comporta en los CRUDS de la suite.
