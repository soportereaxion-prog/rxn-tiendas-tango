# MODULE_CONTEXT: Empresas

## Propósito
Administrar el ciclo de vida y la información base de los *Tenants* (Empresas) del sistema. Es el módulo de facturación, alta, baja y parametrización de las entidades cliente que utilizarán RXN Suite.

## Alcance
- Listado general de empresas dadas de alta.
- Creación (alta), edición y desactivación de *Tenants*.
- Búsqueda y filtrado (CRMs de Empresas).
- Asignación de servicios de acceso y licencias (vía `EmpresaAccessService.php`).

## Piezas Principales
- **Entidades:** `Empresa.php` (Modelo principal).
- **Controlador:** `EmpresaController.php`.
- **Servicios:** `EmpresaService.php` (lógica de negocio ABM), `EmpresaAccessService.php` (lógica de licencias y validación de vigencia o bloqueos).
- **Repositorio:** `EmpresaRepository.php`.
- **Vistas (`views/`):**
  - `index.php`: Listado general con paginación y filtros.
  - `crear.php`: Formulario de alta de nuevo Tenant.
  - `editar.php`: Formulario de actualización de datos de una Empresa existente.

## Rutas y Pantallas
- `/empresas`: (GET) Listado paginado de empresas.
- `/empresas/crear`: (GET/POST) Interfaz y lógica de alta.
- `/empresas/editar?id=X`: (GET/POST) Interfaz y actualización de la empresa X.
- `/empresas/suggestions`: (GET) Endpoint JSON para buscador rápido de empresas (autocompletado).

## Persistencia
- Datos almacenados en la tabla principal `empresas`.
- Utiliza la conexión global `App\Core\Database::getConnection()`.

## Dependencias e Integraciones
- Módulo `AuthService` para validación de privilegios de acceso al módulo.
- Core de Filtros para las vistas.

## Reglas Operativas y Seguridad (Política Base)
- **Aislamiento y Roles:** Este módulo **no opera bajo aislamiento Multiempresa** porque justamente es el gestor de los propios *Tenants*. Su acceso está **estrictamente reservado** a `AuthService::requireRxnAdmin()`. Ningún usuario regular o administrador de Tenant puede acceder a este módulo.
- **Mutación y GET:** Queda terminantemente prohibido dar de alta, eliminar o suspender una empresa vía peticiones GET. Las mutaciones deben procesarse exclusivamente a través de verbos POST/PUT/DELETE.
- **Validación Server-side:** Validar fuertemente la información legal o fiscal (CUITs, correos) en el servidor antes del insert o update.
- **XSS y Control de Salida:** Los nombres de empresas, detalles o motivos de baja suelen ser textos libres. Deben escaparse (`htmlspecialchars`) obligatoriamente en `index.php`, `crear.php` y `editar.php`.
- **CSRF:** Toda acción de alta o edición crítica debe requerir y validar token CSRF para evitar falsificación de peticiones en nombre del administrador global.

## Riesgos y Sensibilidad
- Modificar el estado de una empresa (ej. darla de baja o expirar su licencia en `EmpresaAccessService`) dejará automáticamente inoperativos a todos los usuarios de dicho *Tenant*. Es la llave maestra.
- Una falla en la validación de roles expondría el listado completo de clientes del sistema (brecha de datos masiva).

## Checklist Post-Cambio
1. Comprobar que todos los endpoints (incluidos los asíncronos JSON) posean el guard `AuthService::requireRxnAdmin()`.
2. Verificar que no se hayan introducido dependencias de variables de entorno de un *Tenant* en el código (ya que el RXN admin opera por encima).
3. Asegurar validación CSRF en acciones de creación y actualización.
4. Validar el funcionamiento de `EmpresaAccessService` tras cambios en la estructura de la base de datos de Empresas para no romper accesos.
