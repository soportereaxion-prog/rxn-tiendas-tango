# Implementación de Filtros Avanzados en RXN_LIVE

## Fecha
2026-04-05 15:13

## Modificaciones

Se implementó el motor centralizado de `AdvancedQueryFilter` directamente en el módulo analítico `RXN_LIVE`. 
Esto asegura consistencia visual y funcional en toda la aplicación, permitiendo que tanto tablas de reportes (Live) como de transacciones (CRM) compartan la misma semántica de filtrado avanzado.

### Backend (`RxnLiveService.php`)
- Se interceptó el uso de `$filters['f']` (enviado por `rxn-advanced-filters.js`).
- Se generó un mapa de columnas dinámico `(column => column)` que se pasa como base para inyectar en la sintaxis SQL de la vista delegada.
- Se concatenó el payload validado, garantizando que los filtros avanzados apliquen de manera segura a la capa del Engine Analítico sin romper las agrupaciones.

### Frontend (`rxn-advanced-filters.js` y `dataset.php`)
- **Bug Fix de Ciclo de Vida**: Al regenerarse dinámicamente el DOM en `RXN_LIVE` mediante JavaScript (para cambiar agrupaciones o mostrar/ocultar columnas), los listeners de `DOMContentLoaded` del filtrado perdían su anclaje.
- **Factorización**: Se modularizó `rxn-advanced-filters.js` exponiendo la función `window.initRxnAdvancedFilters()`.
- **Inyección**: El constructor de la tabla (`renderPlana`) en `dataset.php` ahora aplica la clase `rxn-filter-col` y llama de inmediato a la regeneración de los popups.

## Impacto
El módulo de Datasets (`RXN_LIVE`) ahora es totalmente capaz de lidiar con búsquedas multidimensionales profundas, lo cual servirá de base perfecta para las próximas vistas de inteligencia de negocio.
