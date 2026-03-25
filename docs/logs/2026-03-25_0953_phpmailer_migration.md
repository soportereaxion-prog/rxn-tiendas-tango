# Reemplazo Arquitectónico: De Sockets Nativos a PHPMailer

## Contexto y Petición
La implementación transaccional base usando `fsockopen` funcionaba excelentemente para puertos locales 25/587 en SMTPs regulares de hostings tradicionales, pero la Jefa advino una limitante tecnológica estricta: conectar las alertas del Master RXN hacia servidores GMail y sus requerimientos modernos de OAuth2/Cifrado Avanzado. La solicitud exigió reemplazar el *Vanilla-Client* por **PHPMailer**.

## Trabajo Efectuado
1. **Instalación de Vendor:** Ante la falta global del binario en la consola, se bajó un `composer.phar` purgado al root y se requirió `phpmailer/phpmailer:^7.0`.
2. **Reescritura del `MailService.php`:** 
   - Se demolió el motor `sendViaSocket()` y el lector de buffers `readSmtpResponse()`.
   - Se crearon las factory methods nativas de `PHPMailer ($mail->isSMTP())`, manejando el TLS via las flags `ENCRYPTION_SMTPS` y `ENCRYPTION_STARTTLS`.
3. **Mantenimiento del Validador AJAX:** En el sprint pasado construimos `/test-smtp`. Gracias a la prolijidad arquitectónica, la firma `testConnection(array $config)` se respetó a la perfección. Ahora, invoca al Objeto de PHPMailer inyectando el flag `SMTP::DEBUG_SERVER`, atajando el output por un buffer de callback dinámico sin derramarse en los headers HTTP del cliente, y retornando un JSON prístino.

## Archivos Impactados
- `composer.json` / `composer.lock` / `vendor/`
- `app/core/Services/MailService.php`

## Pruebas de Estabilidad
* La mutación se hizo sobre el *Service Pattern* del backend, logrando una cohesión cero sobre la Base de Datos o las Vistas. Los *Tenants* no sufrieron downtime lógico durante la reconstrucción del túnel del correo. 

## Next Steps
Test de Estrés conectando puertos TLS contra Google Workspace en pruebas locales.
