# Restauración de Controles Avanzados en PDS (Fase Final)

## Referencia
- Fecha: 2026-03-31
- Contexto: Módulo CRM (Pedidos de Servicio)
- Objetivo: Reparar crashes por desencuentros de esquema SQL heredados en la tabla de adjuntos, y finalizar la estandarización global de atajos (shortcut) F10 para submit local, erradicando atajos redundantes.

## Problemas detectados
1.  **Crash de Edición (Noup, al dar clic):** `PedidoServicioRepository::getAdjuntos` intentaba hacer SELECT de `file_name` y `file_path` pero la tabla originaria (heredada de antes del reset) almacenaba las imágenes en columnas con los nombres `name` y `path`, y además requería sin default la columna `empresa_id` para los inserts en `syncAdjuntos`. Al dar clic en la lista para editar el formulario, producía un error fatal 500 no capturado en PHP.
2.  **Conflictos de Teclas Rápidas:** Habíamos reimplementado custom shortuts manuales (`F9` y `F10`) en `crm-pedidos-servicio-form.js`, lo cual contradecía la observación global `Pattern #122` que dicta que Todo el CRM debe depender de `rxn-shortcuts.js` donde `F10` o `Ctrl+Enter` dispara guardar cambios y `ESC` retorna al listado, promoviendo consistencia ERP.

## Cambios realizados
-   **Esquema Compatible y Resiliente (`PedidoServicioRepository.php`):**
    -   Se actualizó el `SELECT` a `SELECT id, name as file_name, path as file_path, label, created_at` apuntando a `pedido_servicio_id`.
    -   Se actualizó el INSERT en `syncAdjuntos()` para inyectar correctamente el `empresa_id` con `$empresaId` evitando el MySQL constraint failure.
    -   Bypass robusto del Create Table (`ensureSchema`) a los nombres legados (`name`, `path`).
-   **Estandarización de Interfaz e Input:** 
    -   **Global:** Eliminado el wrapper custom `setupShortcuts` en favor de incluir el script `rxn-shortcuts.js` oficialmente al DOM de los views (`index.php`, `form.php`).
    -   **Botonera:** Actualizadas las class de bootstrap y hotkeys presentacionales para que el Guardado Local indique `<kbd>F10</kbd>` y sea procesado correctamente por el listener de atajos, removiéndose por completo referenciamiento falso a `<kbd>F9</kbd>`.
-   **Validación Local-First:** El timestamp de finalización ("Ahora") y el event `paste` de imágenes (Clipboard) funcionaron transparentemente tras la depuración.
