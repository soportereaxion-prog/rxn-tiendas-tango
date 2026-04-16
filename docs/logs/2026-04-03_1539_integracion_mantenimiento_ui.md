# Integración Visual del Módulo de Mantenimiento al Entorno EmpresaConfig

**Fecha**: 2026-04-03
**Módulos Afectados**: `AuthService`, `EmpresaConfig`, `Admin (Mantenimiento)`

## ¿Qué se hizo?
Se abordó la refactorización visual y arquitectónica a nivel interfaz del módulo técnico de Mantenimiento (`/admin/mantenimiento`).
En lugar de dejar este componente "suelto" o poco documentado para el usuario final (administrador central de RXN), se enlazó como una **6ta sección dentro del módulo Configuración de Empresa**.

### Pasos ejecutados:
1. **Helper Auth**: Se estructuró `AuthService::isRxnAdmin(): bool` para evaluar condicionalmente si el usuario en sesión actual posee el flag `es_rxn_admin`.
2. **Enganche Frontal (UI)**: Se editó `/app/modules/EmpresaConfig/views/index.php` para renderizar condicionalmente una tarjeta de acceso hacia las "Actualizaciones de Base de Datos y Backups" en en final de la vista, **solamente disponible si la comprobación `isRxnAdmin()` pasa**. Un usuario no global nunca verá esta configuración.
3. **Consistencia Visual (UX)**: Se sobreescribió el layout en `/app/modules/Admin/views/mantenimiento.php`, descartando los wrappers vanilla, y adoptando las clases orgánicas del Backoffice: `rxn-form-shell`, `rxn-responsive-container` y las cards standarizadas `rxn-form-card`. El header posee un Breadcrumb visual compatible e incluye la vuelta ("Volver a Configuración").

## ¿Por qué?
El backoffice requiere crecer manteniendo estandarizaciones visuales firmes. Si cada nuevo bloque de administración de sistema adoptara estéticas "crudas", el producto carecería de cohesión de marca. Ahora Mantenimiento se siente como un apéndice natural de configuración de empresa, protegido bajo la sombra del Auth.

## Riesgos y Consideraciones
- **Seguridad**: El link sólo se expone para RXN Admins en el front, y en el back `/admin/mantenimiento`, el controlador mantiene intacta su validación dura de `requireRxnAdmin()`, impidiendo saltos de barrera.
- **Mantenibilidad**: No se rediseñó ni tocó ni un método base de los Managers (Backup / Migrations), previniendo regresiones de funcionalidad.
