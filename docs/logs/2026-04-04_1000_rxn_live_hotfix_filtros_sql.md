# Modificaciones RXN LIVE - Hotfix de Filtros de Base de Datos y Retención de Vistas

## Fecha y Cambio
- **Fecha:** 2026-04-04 10:00
- **Versión:** 1.1.52
- **Cambio:** Se aplicó un hotfix en `RxnLiveController.php` para evitar que parámetros de UX interfieran con consultas SQL ("Unknown Column").

## Problema Detectado
- Al guardar una vista exitosamente, la interfaz en el paso anterior inyectaba `?view_id=X` en la URL del navegador para recordar permanentemente el ID desplegable mediante historial.
- Sin embargo, el endpoint analítico `/rxn_live/dataset` en PHP asume por defecto (*Catch-All*) que TODO lo que provenga del arreglo `$_GET` son filtros para las columnas de la vista MySQL. El intentar matchear el parámetro técnico de GUI `view_id` con una columna que no existe en las tablas de negocio, provocaba que PDO arrojara una excepción SQL ("Unknown column 'view_id'").
- Para proteger al usuario, el bloque `try/catch` central interceptaba esto y devolvía arreglos de respuestas limpios e impecables vacíos `[]`. Resultado: El visor devolvía gráficas extintas o "Sin datos tabulables", rompiendo la funcionalidad.

## Qué se hizo
- En `RxnLiveController.php`, rutinas `dataset()` y `exportar()`: se agregó expresamente el comando `unset($filters['view_id']);` limpiando el parámetro de navegación de UX antes de delegarlo al `getDatasetData()` para ensamble de SQL dinámico.
