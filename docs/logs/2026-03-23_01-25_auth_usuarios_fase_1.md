# Auth Usuarios — Fase 1 (Autenticación Operativa)

## Contexto
Teníamos un sistema de contexto funcional pero dependiente de parámetros estáticos o querystrings. Era indispensable madurar el `Contexto` insertándole estado para que reconozca orgánicamente en nombre de qué empresa trabaja el Request activo sin abrir brechas.

## Problema
PHP no retenía el entorno entre las vistas, la seguridad no existía para el módulo `EmpresaConfig` ni se tenían entidades `Usuario` atadas a un backoffice local.

## Solución Propuesta y Diseño
Se construyó el primer eslabón formal del entorno operativo: La Autenticación Multitenant.
Inyectamos `session_start()` en la primer capa del Framework (`App::run()`).
El `Context` fue refactorizado para priorizar la lectura de sesión, desestimando URL querystrings por defecto (marcado como obsoleto).
Aterrizó el MVC `Auth` que lee desde `usuarios` y, si la semilla acierta el `password_verify()`, el `AuthService` le impregna tres sellos a la sesión: `user_id`, `user_name` e irremediablemente, su `empresa_id` de por vida (hasta el Logout).

## Usuario de Prueba
* **Email**: `admin@empresa.test`
* **Contraseña**: `RxnTest2026!` (Temporal, hasheada por motor BCrypt nativo).
* **Empresa Mapeada**: Se vinculó al registro nº1 extraído automáticamente de la tabla `empresas` en la DB `rxn_tiendas_core` (Ej: `Empresa Demo RXN`).

## Archivos Afectados
- `app/core/App.php` (Session Layer)
- `app/core/Context.php` (Prioridad Env Session)
- `app/modules/Auth/` (Usuario, UsuarioRepository, AuthService, AuthController + views)
- `app/config/routes.php` (Bindings in/out login)
- `app/modules/dashboard/views/home.php` (Dynamic session rendering)
- `app/modules/EmpresaConfig/EmpresaConfigController.php` (Inyección de Guard nativo)

## Pruebas
* **Test Core Service (CLI PHP)**: Se validó que el modelo arroje "Acceso Denegado / Bool:False" si se erra el password y "Auth:Success" en acierto. La lectura del context pre-logout reporta el `EmpresaId = 1`, mientras que post-logout estalla a Null / Denied correctamente, sellando los datos.
* **Test Visual (Home)**: Si no hay ID, ofrece Iniciar Sesión. Si hay, revela nombre de usuario, Entorno activo y lanza al dashboard secundario protegido.

## Riesgos y Consideraciones
* Por ahora el Login no recupera contraseñas ni restringe fallos repetidos (Rate Limiting).
* Backoffice RXN Central sigue abierto sin Guard porque no se estipuló su resguardo particular. Seguimos protegiendo y desarrollando el *Entorno Operativo* (Sub-Empresas).
