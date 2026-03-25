# Seguridad — Validaciones de Cuenta y Recovery Criptográfico (B2B & B2C)

## Contexto
El sistema permitía el ingreso directo post-registro y creaciones manuales de usuarios admin sin requerir la validación de propiedad del correo electrónico. Además, carecía de un mecanismo de recuperación de contraseñas funcional y seguro.

## Problema
El ingreso sin validación compromete la de cuentas y expone las tiendas B2C a registros apócrifos. La carencia de un reseteo de clave generaba carga administrativa y riesgosa.

## Decisión
Bloquear absolutamente el proceso de inicio de sesión de cualquier cuenta (`clientes_web` o `usuarios`) cuyo campo físico en la DB `email_verificado` sea diferente a `1`. 
Implementar endpoints universales orientados a la gestión criptográfica, blindando la creación de token. Nunca mezclar el Token de Activación con el Token de Recuperación de Passwords.

## Archivos afectados
- `migrate_auth_lifecycle.php` (Migración DB Schema, ejecutada y removida)
- `app/modules/Auth/AuthService.php` (Intercepción Login Admin)
- `app/modules/Auth/AuthController.php` (Refactor try/catch de excepciones de Auth Admin)
- `app/modules/Auth/Usuario.php` (Propiedades extendidas de modelo)
- `app/modules/Auth/UsuarioRepository.php` (Soporte de escritura de flags de verificación)
- `app/modules/Usuarios/UsuarioService.php` (Forzado de validación en creación manual admin)
- `app/modules/ClientesWeb/Services/ClienteWebAuthService.php` (Inyección de tokens hex32 en B2C Register y barreras login)
- `app/modules/ClientesWeb/Controllers/ClienteAuthController.php` (Extracción de bypass login B2C hacia notice screen)
- `app/core/Services/MailService.php` (Mutación de PIN code a Tags A href Click-Through)
- `app/modules/Auth/VerificationController.php` (NUEVO - Endpoint Receptor /auth/verify universal)
- `app/modules/Auth/PasswordResetController.php` (NUEVO - Lógica de Reseteos unificados)
- `app/config/routes.php` (Registro Universal endpoints de Recupero)
- `app/modules/Auth/views/forgot.php`, `reset.php`, `resend.php` (Vistas universales en Bootstrap 5 vanilla)
- `app/modules/Auth/views/login.php` (Añadidas anclas de recuperación)
- `app/modules/Store/views/auth/login.php` (Añadidas anclas de recuperación)

## Implementación
1. Se despachó el script `migrate_auth_lifecycle.php` inyectando 4 columnas base en `clientes_web` y 6 en `usuarios`.
2. Las cuentas *legacy* previamente activas fueron "verificadas" forzosamente durante la misma migración DB para no bloquear la operatividad existente.
3. El `attempt` (Admin) y el `login` (Store) en servicios transaccionales ahora lanzan explícitamente `\Exception("Cuenta pendiente de verificación")` que rebota hacia las UIs mediante `try...catch` sin romper el stack de ejecución estandarizado.
4. Generadores `bin2hex(random_bytes(16))` fueron insertados en el registro B2C de Checkout y de Alta Manual Admin, adjuntando la llamada inmediata al `SendVerificationEmail()`.
5. Se redactaron las UIs para soportar la navegación directa "Olvidé Contraseña" y "Reenviar Enlace".
6. Ambas jerarquías de entidades de usuarios pueden consumir libremente los endpoints unificados `VerificationController` y `PasswordResetController` de forma transversal usando la URI canonica y su Token Hex32, autodetectando a qué DB enrutar su redirección en base a su origen.

## Impacto
El login de credenciales reales pasa a ser infructuoso hasta presionar el enlace de confirmación por PHPMailer transportando OAUTH2. Las APIs de Auth ahora soportan recuperos remotos bajo expensas del gestor de tiempo nativo (+24hrs Expira Activación / +30mins Expira Recuperación).

## Riesgos
Si el SMTP (Global o Empresa) fallara, el usuario no podrá crearse la cuenta y loguearse. Esto está mitigado por la fallback machine previamente construida en `MailService` y el botón UI de "Reenviar Enlace", empoderando la autocorrección.

## Validación
- Flujo B2B / Admin: Al crear usuario arroja a `/auth/verify` vía email.
- Flujo B2C / Tienda Pública: Al crear un perfil rebota a la `login?msg=revisar_correo`, imposibilitado de agregar compras al token JWT.
- Olvidé Mí Contraseña: Validado link activo por 30 minutos sobre /auth/reset con hash final directo.

## Notas
Todo token temporal ha sido anulado asignándole formato `NULL` tras su validación de barrido, imposibilitando los exploits de *Replay Attack*.
