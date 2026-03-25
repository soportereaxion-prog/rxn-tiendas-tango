# Tooling Validador de Handshakes SMTP

## Problema Detectado
El Administrador Master o los Tenants introducían configuraciones de transporte SMTP ("Host, Puerto, Usuario") totalmente a ciegas. Un mínimo error de tipeo (Ej: puerto 465 en vez de 587) no se notaba hasta que un comprador intentaba registrarse y no recibía nada, colapsando el túnel transaccional sin feedback claro.

## Solución Funcional
Se instalaron botones de validación inmediata (`Test Connection` / `Probar Seguro SMTP`) a escasos píxeles del botón principal de Guardar.

### Interacciones Técnicas Principales
1. **Core Service Extension:** `App\Core\Services\MailService::testConnection()` abre un Socket PHP puro contra los datos volátiles del Form. Replicando los Handshakes protocolizarios de comandos `EHLO`, negociaciones `STARTTLS` cifradas, y autenticaciones `AUTH LOGIN`. Corta el túnel en cuanto la autorización sea un `235` de éxito, o extrae el `string` de error subyacente de red para feedback transparente.
2. **AJAX Endpoints:**
    - `POST /admin/smtp-global/test`
    - `POST /mi-empresa/configuracion/test-smtp`
    Capturan las peticiones serializadas por `FormData` consumiendo los Request Header de JS vanilla, emulando la inyección al `MailService` y esquivando cualquier intento de persistencia DB indeseado.
3. **JS Listeners (Front):** `smtp_global.php` y `app/modules/EmpresaConfig/views/index.php`. Ambos escuchan clics de prueba, mutan su InnerText visualmente (`⏳ Ejecutando...`), y muestran un cuadro de alerta nativo con el Reporte JSON consumido.

## Archivos Afectados
- `app/core/Services/MailService.php`
- `app/config/routes.php`
- `app/modules/Admin/Controllers/GlobalConfigController.php`
- `app/modules/Admin/views/smtp_global.php`
- `app/modules/EmpresaConfig/EmpresaConfigController.php`
- `app/modules/EmpresaConfig/views/index.php`

## Próximos pasos
El stack transaccional de Correo del B2C se considera maduro. Queda validarlo en Staging Real contra Mailgun, Sendgrid y un Exchange privado simulando Casos Uso Extremos.
