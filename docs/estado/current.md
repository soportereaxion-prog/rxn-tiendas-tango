# ESTADO ACTUAL

## módulos tocados

* módulo: Admin / auth (AuthService, AuthController, UsuarioService)
* módulo: Store / clientes (ClienteWebAuthService, ClienteAuthController)
* módulo: Core (MailService PHPMailer Integration)
* módulo: DB Schema (clientes_web, usuarios)

## decisiones

* Se bloquea el login a usuarios con `email_verificado = 0`.
* Se implementó `VerificationController` para activar los `verification_token` enviados vía mail.
* Se implementó `PasswordResetController` para reestablecer credenciales bajo validación de expiración 30 minutos.
* Se estandarizaron las alertas `$_GET['msg']` para notificaciones unificadas en pantallas de login.
* **Desplazamiento Técnico (Vendor vs Vanilla):** Se aceptó la introducción de la librería `PHPMailer` por solicitud de la Jefa para destrabar restricciones OAuth2 (fundamental para integrar cuentas modernas de GMail / Google Workspace como Master RXN).
* **Consistencia Arch:** Las firmas de los métodos `send()`, `testConnection()` y los emails pre-formateados (`Welcome`, `Verification`, `PasswordReset`) se mantuvieron imperturbables. Esto aseguró que todo el trabajo anterior (UI, Validadores AJAX y Fallbacks) siguiera funcionando de forma *Plug n' Play*.

## riesgos

* Si falla el SMTP Global, la emisión de confirmaciones se atascara y los nuevos usuarios no podrán operar, hasta que cliquen "Reenviar".
* **Autoloader Composer:** Para este update es mandatorio que el comando `composer install` corra en Staging, de lo contrario el `App\Core\Services\MailService` colapsará buscando el namespace de PHPMailer.

## próximo paso

* Testear el envío formal de un "Password Reset" usando la interfaz Master alimentada con credenciales App Password de Gmail o JWT.
