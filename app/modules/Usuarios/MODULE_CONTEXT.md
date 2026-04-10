# MODULE_CONTEXT: Usuarios

## Propósito
Administrar el ciclo de vida de los usuarios (operadores, administradores) dentro de un *Tenant* o a nivel global. Permite la creación, edición, asignación de roles, suspensión y también que cada usuario autogestione sus datos de perfil y credenciales.

## Alcance
- Listado unificado de usuarios de la plataforma con filtros avanzados.
- Alta, modificación de roles y baja lógica de usuarios.
- Pantalla de autogestión: "Mi Perfil" (`UsuarioPerfilController`).
- Manejo seguro de contraseñas (hashing y regeneración).

## Piezas Principales
- **Controladores:** 
  - `UsuarioController.php`: ABM y listado general.
  - `UsuarioPerfilController.php`: Gestión de perfil individual de la sesión activa.
- **Servicio:** `UsuarioService.php` (lógica de negocio de usuarios, validación de contraseñas y unicidad de correo).
- **Vistas (`views/`):**
  - `index.php`: Panel y listado de usuarios de la empresa.
  - `crear.php`: Formulario de creación.
  - `editar.php`: Formulario de actualización de permisos y roles de usuario.
  - `mi_perfil.php`: Interfaz gráfica para que el usuario actualice sus datos.

## Rutas y Pantallas
- `/usuarios`: (GET) Listado de usuarios.
- `/usuarios/crear`: (GET/POST) Interfaz y acción de creación.
- `/usuarios/editar?id=X`: (GET/POST) Interfaz de edición administrativa.
- `/perfil`: (GET/POST) Lectura y escritura del perfil del usuario en sesión.

## Persistencia
- Datos almacenados en la tabla principal `usuarios`.
- Usa la conexión global `App\Core\Database::getConnection()`.
- Estrictamente asociado a la columna `empresa_id` para garantizar multi-tenancy (salvo usuarios RXN Admin globales).

## Dependencias e Integraciones
- Módulo `Empresas` para asociación de entidades.
- `AuthService` para validación de roles cruzados (RXN Admin vs Admin Tenant vs Usuario regular).
- Core de seguridad PHP (`password_hash` / `password_verify`).

## Reglas Operativas y Seguridad (Política Base)
- **Aislamiento Multiempresa:** Es la regla de oro del módulo. Las consultas de `UsuarioService` deben filtrar siempre e incondicionalmente por `Context::getEmpresaId()`. Un administrador de Tenant no puede listar, editar, ni visualizar usuarios de otra empresa.
- **Diferenciación de Roles (RXN Admin vs Tenant Admin):** El flag `es_admin` puede ser gestionado por quienes pasen `canManageAdminPrivileges()` (RXN Admin o admin tenant). El flag `es_rxn_admin` sólo se persiste cuando `AuthService::isRxnAdmin()` retorna true, incluyendo el fallback legado del admin principal de `empresa_id = 1`.
- **Permisos (Guards):** `UsuarioController` expone un `requireAdmin()`, pero hoy ese método sólo delega a `AuthService::requireLogin()`. La contención real de privilegios y alcance vive en `UsuarioService` (`findAllForContext`, `getByIdForContext`, `create`, `update`) y en las banderas de UI (`isGlobalAdmin`, `canManageAdminPrivileges`). `UsuarioPerfilController` también exige únicamente sesión iniciada.
- **Mutación y GET:** Prohibido cambiar estado de usuario, rol o password por GET. Siempre POST.
- **Validación Server-side:** Validar formato de emails y comprobar unicidad global del email (los correos son login únicos en la suite). Aplicar reglas de fortaleza de contraseña en servidor.
- **Escape Seguro (XSS):** Nombres, emails y observaciones mostrados en vistas deben escaparse preventivamente (`htmlspecialchars`).
- **CSRF:** No se observó validación CSRF explícita dentro del módulo. Toda edición de credenciales, perfiles o alteración administrativa debe tratarse como deuda de seguridad activa hasta que el blindaje quede efectivamente integrado.

## Riesgos y Sensibilidad
- Es el módulo más crítico a nivel vulnerabilidades LFI (Logical Flaw Injections) por falta de chequeo de *Tenant*. Si el método `editar` no comprueba que el usuario a editar pertenece al `empresa_id` de quien lo edita, se genera una brecha crítica (Insecure Direct Object Reference - IDOR).
- Elevación de privilegios: riesgo de que un POST alterado eleve a un usuario raso a administrador enviando el flag `es_admin=1` si el backend no lo limpia.

## Checklist Post-Cambio
1. Realizar ataque interno de IDOR: intentar editar un ID de usuario de otra empresa estando logueado como Admin Tenant. Debe fallar por diseño (`403` o `404`).
2. Comprobar que el servicio nunca permita inyectar `es_rxn_admin=1` si el usuario que ejecuta la acción no es RXN Admin.
3. Asegurar que las validaciones de contraseñas no logueen contraseñas en claro bajo ningún concepto.
4. Validar el blindaje XSS en las celdas de las tablas de datos (`index.php`).
