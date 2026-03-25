# ESTADO ACTUAL

## módulos tocados

* módulo: **infra** (Reescrito por completo `App\Core\Services\MailService` abandonando TCP Crudo hacia PHPMailer Vendor).
* módulo: **vendor** (Descargados y enlazados `PHPMailer\PHPMailer` vía Composer).

## decisiones

* **Desplazamiento Técnico (Vendor vs Vanilla):** Se aceptó la introducción de la librería `PHPMailer` por solicitud de la Jefa para destrabar restricciones OAuth2 (fundamental para integrar cuentas modernas de GMail / Google Workspace como Master RXN).
* **Consistencia Arch:** Las firmas de los métodos `send()`, `testConnection()` y los emails pre-formateados (`Welcome`, `Verification`, `PasswordReset`) se mantuvieron imperturbables. Esto aseguró que todo el trabajo anterior (UI, Validadores AJAX y Fallbacks) siguiera funcionando de forma *Plug n' Play*. 

## riesgos

* **Autoloader Composer:** Para este update es mandatorio que el comando `composer install` corra en Staging, de lo contrario el `App\Core\Services\MailService` colapsará buscando el namespace de PHPMailer.

## próximo paso

* Testear el envío formal de un "Password Reset" usando la interfaz Master alimentada con credenciales App Password de Gmail o JWT.
