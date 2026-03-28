# Bitacora interna de modulos para administradores

## Que se hizo
- Se creo `app/shared/services/ModuleNoteService.php` para persistir notas internas por modulo en `app/storage/module_notes.json`, con lectura, agregado, formateo y agrupacion simple.
- Se agrego `app/modules/Admin/Controllers/ModuleNotesController.php` y la vista `app/modules/Admin/views/module_notes_index.php` para revisar todas las anotaciones desde `/admin/notas-modulos`.
- Se incorporo el componente reutilizable `app/shared/views/components/module_notes_panel.php` en dashboards y pantallas principales de los modulos operativos y de backoffice.
- Se sumaron rutas nuevas en `app/config/routes.php` y se ignoro el archivo de storage en `.gitignore` para no versionar anotaciones locales de trabajo.
- Se actualizo `docs/estado/current.md` para reflejar la nueva mecanica transversal de bitacora interna.

## Por que
- Hacia falta una forma liviana de dejar observaciones in situ dentro de cada modulo, sin depender de memoria ni de documentos externos.
- La bitacora tenia que servir solo para administradores y quedar lista para futuras auditorias y refinamientos desde el propio proyecto.

## Impacto
- Los administradores ahora pueden dejar notas cortas de tipo `Idea`, `Ajuste`, `Bug` o `Dato` desde el modulo que estan revisando.
- Cada modulo muestra sus ultimas anotaciones y enlaza a un centro de auditoria interno con el historial agrupado.
- El contenido no se expone al store ni a perfiles sin privilegios administrativos.

## Decisiones tomadas
- Se eligio persistencia en JSON local para mantener simplicidad y evitar meter una tabla nueva en esta etapa.
- La UI se resolvio como panel desplegable reusable dentro de las pantallas, para no interrumpir el flujo principal del operador.
- El centro de auditoria se ubico bajo `/admin/notas-modulos`, accesible a cualquier perfil con privilegios administrativos mediante la misma regla ya usada en backoffice.
