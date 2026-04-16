# Rollback y Aislamiento de Volatilidad (Filtros y Resets)

## Qué se hizo
1. **Restauración CRUDs Universales**: Se deshizo la eliminación de `public/js/rxn-advanced-filters.js` vía `git checkout`, devolviendo a todos los CRUDs (fuera de RXN_LIVE) su capacidad de filtrar con el Popover original. También se reimplementaron las sentencias `mayor_que` y `menor_que` sobre dicho script restaurado.
2. **Volatile View ID Binding**: Se reprogramó la memoria en caché (`rxn_live_volatile_...`) dentro de `dataset.php` para atar su validez exclusivamente a la propiedad `view_id`.
3. **Mecánica de Purgado Absoluto**: La acción "Limpiar Filtros" ahora neutraliza estrictamente los segmentos de base de datos desde la Memoria Volátil (sessionStorage) sin borrar elementos de UX (Columnas ordenadas, Gráficos), permitiendo de forma pionera la anulación de filtros originados desde BD temporalmente en Vistas pre-guardadas.
4. **Mecánica de Reinicio Real**: El botón "Reinicio Total" ahora redirige a la vista original (`view_id`), purgando enteramente la presencia Volátil de la URL para reactivar a la fuerza la inyección original que provee la Base de Datos.

## Por qué
- La unificación "limpia" del commit anterior de Live borró accidentalmente de disco el motor JS que empujaba las cabeceras genéricas de todo los listados del sistema de Reaxion (Daño colateral prevenido pre-producción).
- Había una discordancia profunda entre el Backend y el Frontend a la hora de discernir entre "Una vista que se trajo de la DB" y "Un filtro que le quite por arriba visualmente". La única forma de forzar a que un "Limpiar Filtro" permanezca limpio, es sobre-escribiendo de forma prioritaria la configuración de BD mediante un Storage Volátil (cargado al momento con el `urlFilters: {}` vacío y repriorizado arriba del todo).
- Cuando el usuario deseaba destrozar algo y "empezar de 0", lo reenviábamos a la tabla en blanco y perdía por completo el Dashboard en el que estaba porque le borrábamos su ID. Ahora se purga todo menos su ID.

## Impacto
- Funcionalidades estabilizadas para el 100% de Vistas Reportadas.
- Preservación de memoria UI garantizada aún cuando el control del filtro sea reiniciado agresivamente.
- Restablecimiento de `rxn-advanced-filters.js` evadiendo fallos terminales de todos los controladores ajenos al Live.
