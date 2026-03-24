# ESTADO ACTUAL

## módulos tocados

* módulo: **admin** (Nuevo endpoint `GlobalConfigController` y vista `smtp_global.php`).
* módulo: **infra** (Incorporación del `EnvManager` para mutar variables de entorno en runtime).
* módulo: empresas (Inyección UI del botón "SMTP Master RXN" en el listado central).
* módulo: empresa_config (Refuerzo textual UX aclarando Fallbacks transparentes).

## decisiones

* **Persistencia Root (.env):** Para la jerarquía RXN Master, en vez de crear tablas `config_global` que ensucian el esquema DB, construí la clase `EnvManager` que lee y sobrescribe de forma Regex-safe el archivo `.env`. Esto mantiene el proyecto *Cloud-Native* al estilo Laravel.
* **Separación de Concerns:** Los administradores accederán a configurar el Master RXN desde la misma vista en la que listan las empresas activas, sin mezclarse nunca con el formulario de "Mi Empresa".

## riesgos

* **Permisos Unix (.env):** `EnvManager::updateVariables` requiere que PHP-FPM o www-data tengan permiso de escritura (`chmod 664` o `666`) sobre el archivo `.env` en los subidones a Linux. Fallar arrojará una Exception capturada visualmente en pantalla por el Controller advirtiendo el Denied Access.
* **Restricción de Rutas:** Actualmente `AuthService::requireLogin()` protege `/admin/smtp-global`. A futuro se deberá condicionar a un pseudo-rol `is_superadmin` si se escala el staff.

## próximo paso

* Testear en vivo la mutación del `.env` cuando el repo aterrice en un entorno Staging productivo.
* Consolidar flujos adicionales que dependan de super-administración.
