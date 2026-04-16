# Correcciones en UI y Persistencia RXN_LIVE

## Qué se hizo
1. **Persistencia Backend**: Se corrigió y completó el endpoint POST `/rxn_live/guardar-vista` en `RxnLiveController.php` invocando al servicio de acuerdo a las variables de entorno de la sesión global actual `$_SESSION['usuario_id']`. Se resolvió además una condición de Fatal Error (TypeError) generado por pasaje inseguro de tipos durante la llamada al MVC, utilizando compatibilidad con base `Throwable`.
2. **UI de Búsqueda (Foco)**: Se solucionó el defecto donde la tabla perdía el foco al filtrar dinámicamente en Vanilla JS. Se implementó un algoritmo manual que guarda el ID de la columna enfocada antes de la recombinación del HTML (`innerHTML`) y la restaura utilizando `setSelectionRange()` post-reemplazo.
3. **Política Cero Alertas**: Se eliminaron las alertas nativas clásicas que se presentaban al momento de guardar el prefiltro o nombre de la vista en RXN_LIVE (e.g. `prompt()`, `alert()`). Se construyó dinámicamente un Modal Bootstrap 5 limpio que no altera los flujos UX.

## Por qué
- UX y fluidez.
- Completar la implementación de vistas dinámicas RXN_LIVE.
- Requisito de operador: "No usar alerts para nada y reemplazar por modales nativos de bootstrap".

## Impacto y Decisiones
- En lugar de modificar los core functions como el router o el motor de renderer, se reconciliaron inputs del front mediante una memoria temporal de foco durante los microsegundos del redibujado de la tabla para no romper el ciclo de uso original.
- El guardado asíncrono se resolvió mediante Fetch y JSON puro al backend MVC.
