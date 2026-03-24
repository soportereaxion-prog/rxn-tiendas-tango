# ESTADO ACTUAL

## módulos tocados

* módulo: **infra** (Incorporación de `MailService` nativo bajo Sockets TCP/IP. Variables globales `.env`).
* módulo: **empresa_config** (Ampliación DB para soporte tenant-specific SMTPOverrides; UI renovada para Admins).
* módulo: clientes_web (Inyección del Dispatcher Transaccional `sendWelcomeEmail` y estructuración DB de `reset_token`).

## decisiones

* **Agnosticismo de Vendor:** Se evitó instalar `PHPMailer` para respetar las directivas *Vanilla-first*. `App\Core\Services\MailService` ejecuta la transmisión construyendo buffers de Sockets con soporte explícito `STARTTLS` y `SSL`, cubriendo casuística universal.
* **Jerarquía de Fallback (Rxn Global):** La regla inquebrantable de orquestación dicta que el sistema buscará primeramente un override local (`usa_smtp_propio = 1` en `empresa_config`). Si falla o es inexistente, consumirá las constantes `MAIL_HOST` y afines inyectadas preventivamente en el fichero superglobal `.env`.

## riesgos

* **Disponibilidad de Puertos Outbound:** Algunos ISPs locales (Ej: WAMP/XAMPP defaults) bloquean el puerto 25 y el 587. Mitigado delegando los testeos formales a los entornos Staging de hosting profesional.
* **Seguridad (Auth Bypass):** Las claves introducidas en Panel Empresa no se devuelven rellenadas al DOM HTML (`value=""`) protegiendo el hash en inspector de elementos.

## próximo paso

* Testear triggers de correo en vivo una vez mergeado a Staging.
* Acoplar la construcción definitiva de los frontends de Recuperación ("Olvidé mi contraseña") leyendo el nuevo esquema `reset_token`.
