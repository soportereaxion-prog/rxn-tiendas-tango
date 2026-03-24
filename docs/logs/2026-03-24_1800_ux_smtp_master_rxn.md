# Ajuste UX / Administración SMTP Master RXN

## Contexto y Motivo
El servicio transversal `MailService` ya orquestaba perfectamente el Fallback de variables asumiendo que el alojamiento principal de secretos globales radicaba en `.env`. La Jefa solicitó urgentemente una interfaz *usuario-céntrica* que permita a la cúpula administrativa configurar ese Mailer Global de forma expedita y divorciada de cualquier configuración "Por Empresa" (Tenant).

## Criterio UX Adoptado
* Las configuraciones Tenant siguen en su Dashboard (`/mi-empresa/configuracion`) resguardadas bajo un Switch Toggle, con un disclaimer mucho más crudo y explícito: *"Si no configurás SMTP propio, el sistema utilizará el SMTP master de RXN"*.
* La visualización Root nace de un botón dorado y robusto `⚙️ SMTP Master RXN` colocado hombro a hombro junto a *Nueva Empresa* en la pantalla `/empresas` inicial.

## Archivos Tocados
* `App\Core\EnvManager.php` (Motor de Parseo y Mutación de dot-envs preservando comentarios).
* `App\Modules\Admin\Controllers\GlobalConfigController.php` (Controlador protegido que inyecta parámetros SMTP).
* `App\Modules\Admin\views\smtp_global.php` (UI Master en Dark Mode para separar fuertemente la jerarquía visual respecto a las empresas).
* `app/config/routes.php` (Binding HTTP GET/POST de `/admin/smtp-global`).
* `app/modules/empresas/views/index.php` (Inyección HTML del Botón Admin).
* `app/modules/EmpresaConfig/views/index.php` (Alteración en copywriting para UX alert).

## Pruebas y Simulaciones
1. **Piso de Inviolabilidad:** El campo "Password" de Master RXN está encriptado en sesión HTML y, si el administrador despacha el form sin escribir una nueva contraseña, el Mutator omite alterar `MAIL_PASS` y resguarda la credencial subyacente intacta.
2. **Escritura Segura:** El `EnvManager` retiene `$key=$val` escupiendo un buffer in-memory array garantizando que otras variables (`DB_PASS`) no se corrompan por error de regex.

## Riesgos Detectados
* Entornos Linux con bloqueos drásticos Chown/Chmod en la raíz `/.env` van a disparar un error por pantalla *"Permiso denegado al intentar escribir sobre .env"*. El Servidor FPM necesitará `rw-` flag para posibilitar esta feature web.

## Próximos pasos
El acceso actual a `/admin/smtp-global` exige login. En un escenario futuro multi-nivel, la directiva `AuthService::requireSuperAdmin()` será lo adecuado para impedir que un usuario nivel tienda pise el Master.
