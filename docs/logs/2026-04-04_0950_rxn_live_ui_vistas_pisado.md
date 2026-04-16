# Modificaciones RXN LIVE - DataSet UI (Lógica de Pisado de Vistas)

## Fecha y Cambio
- **Fecha:** 2026-04-04 09:50
- **Versión:** 1.1.52
- **Cambio:** Introducción de botones divididos para "Guardar" (pisado) o "Guardar como Nueva", más tracking de URL de vista seleccionada tras el guardado.

## Qué se hizo
- En `RxnLiveService.php` el método `saveUserView` ahora soporta un 5to parámetro (`$viewId`). Si se recibe, en vez del `INSERT` para crear una nueva configuración en la DB, ejecuta un `UPDATE` afectando únicamente al registro existente bajo ese `id` y validando que pertenezca al mismo `$userId` blindando su seguridad.
- Modificado `RxnLiveController.php` para interceptar `$_POST['view_id']` y retornarlo en el JSON del response. 
- En el layout de Frontend `dataset.php` se separó el botón central:
  - **Guardar:** Manda por POST el `$viewId` de la opción actualmente seleccionada en el combo, pisando silenciosamente el registro en MySQL. (Si se intenta pisar una Vista de Sistema o [ Vista Base ], la interfaz automáticamente redirige al prompt de "Crear Nueva").
  - **Nueva Vista:** Refleja la funcionalidad `promptSaveView()` tradicional solicitando un nuevo título para hacer un `INSERT` en DB.
- **Retención URL:** Cuando el `fetch` al servidor responde positivo, la página recarga automáticamente inyectando en la cabecera `?view_id=X` (History URL parameters). La rutina inicial DOMContentLoaded recupera este parámetro oculto obligando al selector a engancharse a la vista elegida en lugar de caer tontamente por default en la configuración inicial ("Vista Base").
