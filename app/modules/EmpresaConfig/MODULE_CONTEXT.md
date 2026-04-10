# MODULE_CONTEXT: EmpresaConfig

## Propósito
Gestionar las configuraciones específicas de negocio, operativas y de integración para cada empresa (Tenant) en diferentes áreas del sistema (ej. CRM, Pedidos, Presupuestos, Integraciones con ERPs como Tango).

## Alcance
- Parámetros operativos y de negocio vinculados a un `empresa_id` (numeración base de comprobantes, perfiles de integración, plantillas de impresión, casillas de correo emisor).
- Estructura su visualización y persistencia en "áreas operativas" (ej. área `crm`, etc.).
- Permite a los administradores del Tenant (o RXN Admin) adaptar el comportamiento del software a las reglas de cada empresa cliente.

## Piezas Principales
- **Entidades:** `EmpresaConfig.php` (Modelo).
- **Controlador:** `EmpresaConfigController.php`.
- **Servicio:** `EmpresaConfigService.php` (lógica de negocio de la configuración, resolución de áreas vía `OperationalAreaService`).
- **Repositorio:** `EmpresaConfigRepository.php` (persistencia directa sobre `empresa_config`).
- **Vistas (`views/`):**
  - `index.php`: Interfaz gráfica unificada con pestañas según el área operativa cargada.

## Rutas y Pantallas
- `/empresa/config`: (GET) Renderiza el panel de configuraciones.
- `/empresa/config/save`: (POST) Recibe y persiste la actualización de parámetros de configuración.

## Persistencia
- Datos almacenados en la tabla principal `empresa_config`.
- Utiliza la conexión global `App\Core\Database::getConnection()`.
- Incorpora mecanismos (aún en repositorio) de adición de columnas dinámicas (ej. `ALTER TABLE ... ADD COLUMN tango_perfil...`), siendo un punto de atención técnica.

## Dependencias e Integraciones
- `App\Shared\Services\OperationalAreaService`: Define en qué área operativa se encuentra el usuario trabajando.
- `App\Modules\Empresas\EmpresaRepository`: Para corroborar la existencia del tenant.
- Integración con el Módulo `PrintForms` para la configuración de plantillas de impresión por defecto de la empresa.

## Reglas Operativas y Seguridad (Política Base)
- **Aislamiento Multiempresa:** Es **CRÍTICO**. Todas las consultas de lectura y acciones de escritura (`save`) deben estar vinculadas ineludiblemente al `Context::getEmpresaId()`. Un tenant nunca debe poder visualizar o alterar configuraciones de otro tenant.
- **Permisos (Guards):** Uso obligatorio de `AuthService::requireLogin()`. Generalmente restringido a roles administrativos (ej. Admin Tenant o RXN Admin).
- **Mutación y GET:** Prohibida la modificación de configuración de empresa mediante peticiones GET. Exclusivo de endpoints de escritura (POST).
- **Validación Server-side:** Tipado estricto e higiene de datos en `EmpresaConfigRepository`. Validar correos, numéricos base de comprobantes y validación de entidades (PrintForms existentes).
- **Escape Seguro:** Escape HTML en la vista `index.php` para prevenir ataques de Cross-Site Scripting (XSS) al leer parámetros (ej. correos, descripciones, claves de API ofuscadas).

## Riesgos y Sensibilidad
- Modificaciones en este módulo afectan el núcleo del flujo operativo de la empresa (ej. si se rompe la configuración de Tango, se frena la generación de pedidos).
- Riesgo de inyección SQL si `EmpresaConfigRepository` maneja mal sus constructores dinámicos y adiciones de columnas.
- Exposición de secretos de integración si se muestran sin ofuscar en la vista (contraseñas de APIs de ERPs).

## Checklist Post-Cambio
1. Validar que la query SQL en `EmpresaConfigRepository` siempre condicione implacablemente por `empresa_id`.
2. Verificar que el guard de autenticación y rol proteja el guardado (`save`).
3. Comprobar que ninguna entrada del usuario logre bypassear el tipado fuerte al actualizar las configuraciones de la tabla.
4. Asegurar que configuraciones sensibles no sean expuestas directamente en peticiones GET no autenticadas ni alterables por inspectores del navegador (DOM manipulations).
