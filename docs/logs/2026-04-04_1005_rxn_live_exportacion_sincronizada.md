# Modificaciones RXN LIVE - Exportación y Sincronización UI-SQL (Excel + Spout)

## Fecha y Cambio
- **Fecha:** 2026-04-04 10:05
- **Versión:** 1.1.52
- **Cambio:** Integración de metadata de vistas y Theme Dark mode hacia el File Builder de OpenSpout para generar listados de Excel responsivos y fieles a lo que ve el usuario en pantalla.

## Por qué
- Hasta ahora la tabla UI de `dataset.php` procesaba sus filtros personalizados por columnas (los cajoncitos que aparecen debajo el header) y el ordenamiento local (`flatFilters`, `flatSortCol`) utilizando puramente Javascript de forma `Client-Side`.
- Al apretar el botón de "Exportar a Excel", la herramienta en Backend iba a buscar un Select de SQL fresco, ignorando caprichosamente todos esos micromovimientos que había hecho el operador en frontend.
- Además de ignorar el ordenamiento y los filtros, los Excel salían crudos y en blanco, no reflejando la paleta de modo Dark u oscuro corporativa del sistema.

## Qué se hizo
- **Sincronización Form:** Al escuchar el botoncito de "Exportar CSV/Excel", inyectamos programáticamente `<input type="hidden">` con las llaves extraídas de la variable `flatFilters` y la configuración de `sort` + el modo visual predominante de Bootstrap.
- **SQL Backend Soportante:** En `RxnLiveService.php` le enseñamos a la función constructora de sentencias dinámicas `buildQuery()` cómo interpretar `sort_col` y `sort_asc`, insertándolos a prueba de inyecciones (usando limpiezas RegExp) en la cláusula base de datos `ORDER BY` del `SELECT`.
- **Estetización Spout v4:** El generador centralizado de Spreadsheet (OpenSpout v4) fue retocado en `RxnLiveController.php` utilizando las factorías inmutables `withBackgroundColor` y `withFontColor` desde `OpenSpout\Common\Entity\Style\Style` para estampar celdas negras/blancas o claras/grises basándonos en si el usuario trabaja de noche o de día con el sistema operativo.
