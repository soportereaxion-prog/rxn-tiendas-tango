# Modificaciones RXN LIVE - DataSet UI (Persistencia de Toggles)

## Fecha y Cambio
- **Fecha:** 2026-04-04 09:44
- **Versión:** 1.1.52
- **Cambio:** Integración de estados de diseño (toggles de visibilidad pantalla dividida) en el motor de persistencia de vistas.

## Qué se hizo
- En `app/modules/RxnLive/views/dataset.php`, se modularizó la lógica de visibilidad aislando el mutador del DOM en una nueva función `applyViewVisibility()`.
- Se incorporaron las variables operacionales `chartVisible` y `tableVisible` al diccionario devuelto por `extractViewConfig()`, serializándolas junto al resto de configuraciones al ejecutar el guardado de la vista.
- Se modificó la rutina de carga `loadSelectedView()` para que, en caso de detectar configuraciones de estado visual pre-grabadas, asigne las variables y dispare `applyViewVisibility()` inmediatamente, recuperando el layout expandido o constreñido tal como lo dejó el usuario.
- Como control de fallback, retornar a la "Vista Base" o cargar una vista muy antigua que no posea estos parámetros forzará un reseteo de la visión `(true, true)` encendiendo de nuevo ambos paneles simultáneamente.
