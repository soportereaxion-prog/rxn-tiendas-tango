# MODULE_CONTEXT: Admin

## Propósito
Este módulo provee la interfaz y lógica administrativa global de todo el sistema (RXN Admin). Está estrictamente reservado para los super administradores del sistema, gestionando herramientas cruzadas de mantenimiento, migraciones, configuración de entorno y notas técnicas de los módulos.

## Alcance
- Gestión y configuración del servicio SMTP Global (configuración de `.env`).
- Herramienta de Mantenimiento Integral: control de migraciones, motor de backups de BD, actualización de sistema base y generador de releases.
- Administración del Centro de Notas Técnicas del Módulo (`ModuleNotes`).
- Exclusivo para usuarios con rol o permisos de "RXN Admin". No pertenece a ningún *Tenant* particular.

## Piezas Principales
- **Controladores:**
  - `GlobalConfigController`: Gestión de configuraciones globales (ej. `.env`).
  - `MantenimientoController`: Orquesta `MigrationRunner`, `BackupManager`, `SystemUpdater` y `ReleaseBuilder`.
  - `ModuleNotesController`: Gestión de las notas del sistema para administradores.
- **Vistas (`views/`):**
  - `mantenimiento.php`: Panel de mantenimiento avanzado.
  - `module_notes_index.php`: Panel de visualización de notas técnicas de módulos.
  - `smtp_global.php`: Panel de configuración de correo global.

## Rutas y Pantallas
- `/admin/mantenimiento`: (GET) Renderiza el panel de backups y migraciones.
- `/admin/config/smtp_global`: (GET/POST) Interfaz de lectura y actualización del SMTP base.
- `/admin/module-notes`: (GET/POST) Lectura y escritura de notas.
- (Endpoints internos y POST para procesar las acciones anteriores).

## Persistencia
- Las notas de los módulos se persisten en base de datos.
- Las configuraciones del entorno (`GlobalConfig`) impactan el archivo de variables locales (por ej. `.env`).
- El módulo de mantenimiento interactúa directo con el archivo de base de datos o el motor vía `MigrationRunner` y `BackupManager`.

## Dependencias e Integraciones
- `App\Core\EnvManager`: Para edición del archivo global.
- `App\Core\MigrationRunner`, `App\Core\BackupManager`, `App\Core\SystemUpdater`, `App\Core\ReleaseBuilder`: Motores para la herramienta de mantenimiento.
- `AuthService`: Para comprobación obligatoria de rol RXN Admin.

## Reglas Operativas y Seguridad (Política Base)
- **Aislamiento:** Este módulo rompe la barrera del *Tenant* (no filtra por `empresa_id`) porque opera a nivel servidor o base global. Su acceso debe estar blindado exclusivamente por superadministradores.
- **Permisos (Guards):** Obligatorio el uso de `AuthService::requireRxnAdmin()` en TODOS los controladores y métodos.
- **Mutación y GET:** No mutación de estado a través de verbos GET. Toda alteración de configuración o ejecución de mantenimiento debe hacerse vía POST u otro método semántico adecuado.
- **Validación Server-side:** Validar entradas antes de aplicar en el `.env` o en persistencia.
- **Seguridad y Accesos Locales:** El controlador `MantenimientoController` ejecuta procesos pesados (backups, migraciones), por ende, tiene un impacto directo sobre el acceso local del sistema de archivos o la DB base. Blindar rigurosamente.
- **XSS y CSRF:** Empleo obligatorio de escape preventivo en salida de texto. Toda acción destructiva o de configuración global debería protegerse del CSRF y de inyecciones de comandos.

## Riesgos y Sensibilidad
- Modificar este módulo presenta un riesgo crítico global. Un acceso indebido o fallo de seguridad permitiría la descarga de la base de datos completa o la sobrescritura del `.env`.
- Cuidado extremo con los métodos que ejecutan `.bat` o utilerías del SO vía exec, o migraciones directas de BD.

## Checklist Post-Cambio
1. Verificar que el guard `AuthService::requireRxnAdmin()` siga intacto en cada método y ruta afectada.
2. Confirmar que la acción desarrollada no impacte o corrompa el archivo `.env` al guardarlo.
3. Probar herramientas de migraciones y backups solo en entorno de testing, no sobre producción.
4. Validar el escape de salida en la visualización de `module_notes`.
