# Fix de Normalización de Casing para Linux en Deploy

## Qué se hizo
Se ajustó el script de preparación de despliegue (`tools/deploy_prep.php`) para manejar correctamente la conversión PSR-4 y el casing del sistema de archivos al generar la carpeta `/build`. 

## Por qué
En la versión anterior del script, se estaba forzando el casing a formato `TitleCase` de forma demasiado general (por ejemplo convirtiendo `app/shared/views` en `app/shared/Views`), lo cual rompía las referencias de los `include()` internos físicos en el servidor Linux de producción (ej: `BASE_PATH . '/app/shared/views/...'`), dando errores de `Failed to open stream`.

Además, de forma preventiva, se detectaron inconsistencias en cómo Windows reportaba el sistema de archivos raíz, por lo que podía aparecer `App/` en lugar de `app/`.

## Impacto
* Ahora las carpetas que contienen código de negocio (`core`, `modules`, `Repositories`, `Controllers`, `Services`, `Helpers`) se exportan estrictamente con convención PSR-4 para asegurar el autoloader correcto en un file system Ext4.
* Las carpetas que NO son namespace sino componentes estructurales (`views`, `middleware`, `config`, `components` así como los roots `app`, `public`, `storage`, `vendor`) se fuerzan a su versión **lowercase** explícitamente y no son modificadas por la capitalización.
* Se confirma que **ya no existe el término `/rxnTiendasIA` en el código fuente de desarrollo**. Cualquier registro residual provenía de la carpeta `/build` anterior desactualizada.
* La ejecución de `php tools/deploy_prep.php` en adelante generará una salida totalmente compatible con el nuevo base path (`/rxn_suite`).

## Archivos modificados
- `tools/deploy_prep.php`

## Decisiones Tomadas
* Preservar la mecánica de sanitización cruzada en el deploy_prep en vez de forzar convenciones ineludibles sobre vistas y assets estáticos, previniendo que una regla estricta PSR-4 corrompa rutas relativas y strings de inclusión.
