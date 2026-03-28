# Bitacora flotante con capturas pegadas

## Que se hizo
- Se transformo `app/shared/views/components/module_notes_panel.php` en un widget flotante y redimensionable, manteniendo el alcance solo para administradores.
- Se agrego `public/js/rxn-module-notes.js` para manejar apertura/cierre del widget, drag and drop de imagenes, seleccion manual y pegado de capturas desde portapapeles.
- Se extendio `app/modules/Admin/Controllers/ModuleNotesController.php` para aceptar una captura opcional, validarla y guardarla en `public/uploads/module-notes/...`.
- Se amplio `app/shared/services/ModuleNoteService.php` para persistir `attachment_path` y `attachment_name`, permitiendo notas con texto, captura o ambas.
- Se actualizo `app/modules/Admin/views/module_notes_index.php` y la documentacion para mostrar las capturas dentro del centro de auditoria.

## Por que
- La bitacora inline resolvia el registro minimo, pero faltaba una experiencia mas natural para ir anotando sobre la marcha sin mover el flujo del modulo.
- Poder pegar un screen tipo chat reduce friccion y deja mejor contexto visual para auditar mejoras despues.

## Impacto
- Los administradores ahora pueden dejar una nota flotante desde cualquier modulo sin perder de vista la pantalla que estan revisando.
- Las capturas quedan asociadas a cada anotacion y se pueden abrir luego desde el centro interno de auditoria.
- Los archivos subidos quedan fuera de git y siguen sin exponerse al store ni a usuarios sin privilegios.

## Decisiones tomadas
- Se mantuvo persistencia local simple: JSON para metadata y carpeta de uploads publica solo para renderizar las capturas internas.
- Se eligio `Ctrl+V` + `DataTransfer` en frontend para no sumar dependencias ni complejidad extra.
- El widget quedo fijo abajo a la derecha y sin arrastre libre para mantener una implementacion minima y estable en esta etapa.
