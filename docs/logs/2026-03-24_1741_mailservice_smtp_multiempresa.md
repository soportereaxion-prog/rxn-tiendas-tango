# MailService y Configuración SMTP Desacoplada

## Auditoría Inicial
* La configuración global de infraestructura ya yacía orientada al uso del `.env` (credenciales de DB).
* La configuración particular de la firma comercial residía en la tabla `empresa_config`.
* Conclusión: Separación de Concerns directa -> `.env` para Globales Fallback; `empresa_config` para Overrides Locales.
* Ausencia de librerías SMTP: Creación mandatoria de un Socket-Client puro (`App\Core\Services\MailService`).

## Ubicaciones Elegidas
* **SMTP Global RXN:** Declarado explícitamente en el root `/.env` (MAIL_HOST, MAIL_PORT, MAIL_USER, etc).
* **SMTP Por Empresa:** Alternado en base de datos `empresa_config` mediante las columnas `usa_smtp_propio`, `smtp_host`, etc.
* **Panel de Configuración:** `app/modules/EmpresaConfig/views/index.php`.

## Regla de Fallback Transaccional
1. Evaluar si tenant `usa_smtp_propio` es True. Si lo es, validar la integridad del string host/user e invocarlo.
2. Si falla o es False, invocar nativamente `getenv('MAIL_HOST')`.
3. Nunca romper peticiones HTTP a causa de Timeouts remotos: Wrap principal envuelto en `try/catch` devolviendo silenciosamente un Booleano falso mientras lo reporta en Error_log genérico.

## Decisiones de Seguridad
* **Invisibilidad Constante:** El campo `Contraseña SMTP` en la UI de Administración es ciego. Solo procesa mutaciones sobre `$_POST` si el string no viene puramente vacío, conservando la clave anterior intacta y nunca escribiendo texto plano en el source page.
* **Zero Trust:** Formularios Auth no advierten explícitamente qué fase SMTP falla limitando vectores de enum testing. La caída de SMTP previene colapsos frontales.
* **Recuperación Estanca:** El módulo `clientes_web` ahora dispone de `reset_token` y `reset_expires`. La semilla está expuesta pero envuelta en validaciones de timestamps.

## Archivos Tocados
* `.env` (Alterado global config).
* `App\Core\Services\MailService.php` (Servicio Orquestador Socket Native).
* `App\Modules\EmpresasConfig\EmpresaConfig.php | Repository | Service | /views/index.php`.
* `App\Modules\ClientesWeb\Services\ClienteWebAuthService.php` (Inyectado el Dispatch transaccional).
* `migrate_smtp.php` y `migrate_reset_token.php` (Alter scripts consumidos vía Binario).

## Riesgos y Consideraciones
El firewall nativo de Windows (WAMP) podría demorar el `fsockopen` TLS Handshake desencadenando lags de 10 segundos por post, el entorno Staging Linux es imperioso para validar responsividad.

## Próximos pasos
Desarrollar vistas FormFront-End de "Olvidé mi contraseña" para acaparar e invocar formalmente el método construido `requestPasswordReset()`.
