# Reubicación UX de Módulo de Mantenimiento

**Fecha:** 2026-04-03 16:30
**Propósito:** Mover el acceso a Mantenimiento del dashboard de Configuración de Empresa hacia el panel de inicio del RXN BackOffice, logrando coherencia semántica en la herramienta orientada a administradores globales.

### Modificaciones de UX (Reubicación)

- **Criterio Anterior:** El módulo formaba parte del final de las tarjetas de `EmpresaConfig`, lo que forzaba a un Administrador RXN a ingresar dentro del contexto de una empresa para realizar mantenimiento del sistema global.
- **Nuevo Criterio UX:** El módulo ha sido retirado de `EmpresaConfig` y añadido como tarjeta de primer nivel al Launcher Dashboard de RXN Backoffice (`/admin/dashboard`).
- **Visibilidad:** Limitada implícita y nativamente a RXN Admin, puesto que todo el endpoint `/admin/dashboard` está protegido por el interceptor `AuthService::requireRxnAdmin()`.
- **Naming:** La tarjeta ha sido bautizada "Sistema y Mantenimiento", descrita como 'Actualizaciones de BD, migraciones y ejecución de dumps operativos.'
- **Estilos:** Se homologó el DOM para coincidir milimétricamente con el standard de Backoffice: `.rxn-module-card`, `<i class="bi bi-tools text-danger">`, `stretched-link`.

### Archivos Afectados
1. `app/modules/EmpresaConfig/views/index.php` (Se purgaron las líneas referidas a la card de mantenimiento).
2. `app/modules/Dashboard/views/admin_dashboard.php` (Se incorporó la nueva card en la grilla nativa).
