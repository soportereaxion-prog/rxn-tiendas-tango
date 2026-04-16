# Módulo de Mantenimiento (Backups y Migraciones DB)

**Fecha:** 2026-04-03 09:33  
**Autor:** Agente IA  

## Objetivo del módulo
El despliegue de archivos de la aplicación se resuelve sobreescribiendo el contenido, pero no existía un mecanismo unificado, seguro y con trazabilidad para aplicar cambios en la estructura de la base de datos ni para centralizar la obtención de copias de seguridad. 
Este módulo tiene como propósito proveer un entorno técnico controlado dentro del módulo de Administración global (protegido bajo `requireRxnAdmin()`).

## Estrategia elegida
Se busca crear una base sólida y extensible sin acoplar dependencias enormes ni rediseñar la arquitectura base.
1. **Runner de Migraciones:** Un sistema PHP nativo que parsee archivos de un directorio designado y mantenga control del estado (ejecutada/pendiente/error) usando una tabla en la propia DB.
2. **Backups Nativos:** Dado que no se asumen capacidades nativas de shell sobre el SO/Plesk, la solución v1 se enfoca en backupear el contenido usando herramientas puras de PHP (`PDO` para el volcado DB, `ZipArchive` para la compresión de archivos).

## Arquitectura propuesta
- **Archivos fuente:** `database/migrations/`
- **Output de backups:** `storage/backups/`
- **Registro histórico:** Tabla `RXN_MIGRACIONES` auto-creada por la clase `MigrationRunner`.
- **Clases CORE:** `App\Core\MigrationRunner` y `App\Core\BackupManager`.
- **Controlador/Vista:** `App\Modules\Admin\Controllers\MantenimientoController.php` + `mantenimiento.php`.

## Decisiones sobre migraciones
- La tabla guardará: nombre_archivo (`migracion`), resultado (`SUCCESS` o `ERROR`), observaciones, ID del usuario (`usuario_ejecutor`), fecha_hora.
- El formato de script de migración será un array con un `callable` (closure/función anónima) que recibirá el objeto `$pdo` por defecto o será ejecutado internamente.
- Todo error se capturará para impedir que las migraciones siguientes pendientes se apliquen accidentalmente.

## Decisiones sobre backups
- **Archivo BD:** Se descarta el `mysqldump` directo vía `exec` a fin de asegurar operabilidad en hostings restringidos y asegurar independencia total en PHP. La primera iteración usa PDO estructurado e INSERTS por bloque.  
- **Archivo FS:** Se instancia un zip recursivo obviando `vendor/` y `.git/` para evitar redundancias o desbordes de recursos.
- Todo volcado caerá en `storage/backups/` con estampa temporal en sus nombres, e interfaz UI simple de listado descendente.

## Riesgos y limitaciones de esta primera etapa
- **Backup DB Nativo PHP:** Un PDO nativo en bases de datos con muchos megabytes o gigabytes colapsará la RAM (`memory_limit`). *Próximos pasos:* Validar la presencia de `mysqldump` y fall-back o iterar por lotes (offset).
- **Timeouts:** Operaciones web que toman tiempo largo son propensas a cancelaciones por PHP `max_execution_time`. *Próximos pasos:* Modificar la UI a async (AJAX).

## Estado de Modificación
- Módulo Creado con Éxito. Integrado a rutas admin.
