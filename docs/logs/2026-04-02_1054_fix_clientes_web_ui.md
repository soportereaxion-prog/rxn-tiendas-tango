# Fix UI en Clientes Web

## Qué se hizo
- Se actualizaron los botones de acciones de la vista principal del módulo de "Clientes Web". 
- Se reemplazaron imágenes SVG ( `<img src="...pencil-square.svg">` y `trash.svg`) que no resolvían correctamente la ruta y no respetaban el estándar visual, por el uso de Bootstrap Icons (`<i class="bi bi-pencil-square"></i>`, etc) consistentes con el diseño de módulos como CRM.
- Se corrigió el trigger de los mini-formularios `rxn-confirm-form` de eliminación, donde los botones debían ser `type="submit"` para poder activar el hook de borrado y el modal, además de unificar los atributos de confirmación a `data-msg`.

## Por qué
- Había un reporte de inconsistencias donde no aparecían los iconos (se rompían en producción).
- El botón eliminar / mandar a papelera no hacía nada al hacerle click ya que estaba definido como un simple `type="button"` dentro de un `<form>` donde no disparaba ningún evento.

## Impacto
- Las funcionalidades de mandar a papelera, borrar y recuperar operan con normalidad usando el sistema global de `rxn-confirm-modal`.
- El diseño vuelve a coincidir con el del resto del sistema.
