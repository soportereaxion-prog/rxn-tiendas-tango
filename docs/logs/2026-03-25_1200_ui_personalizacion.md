# [UI] — Capa de Personalización Visual (Theming & Branding)

## Contexto
El sistema multiempresa necesitaba permitir la personalización de la interfaz sin introducir frameworks pesados ni romper los layouts existentes. La UI base debía adquirir soporte para Dark/Light modes y escalas de fuentes por usuario en el Admin (B2B), y variables CSS de Branding (colores, logo, footer) por Empresa en el Store (B2C).

## Problema
Los estilos estaban hardcodeados en los layouts (especialmente `Store/views/layout.php`) y en las etiquetas `<html>`.  Las tablas de base de datos no tenían soporte nativo para almacenar custom colors o metadata extensa del pie de página. 

## Decisión
Se decidió derivar la inyección de estilos a un Helper unificado (`UIHelper.php`) que intercepta si estamos en sesión Admin (leyendo el User pref de BDD/Session) o en el layout del Tenant (inyectando variables CSS CSS-Custom-Properties dinámicas extraídas desde la Empresa). En el B2C, el Dark Mode no requiere login, es guardado temporalmente en `LocalStorage` vía Javascript nativo y gatillado con un toggle switcher en el Header. Las rutas pre-existentes se mantienen limpias y sólo muta la semántica global asimilando `var(--color-primary)` y `<html data-theme="dark">`.

## Archivos afectados
- `migrate_ui_theming.php` (DB Alter tables)
- `app/config/routes.php` (Bindings para `mi-perfil`)
- `app/core/Helpers/UIHelper.php` (NUEVO core interceptor de UI CSS)
- `public/css/rxn-theming.css` (NUEVO CSS Global Theming matrix)
- `app/modules/Usuarios/UsuarioPerfilController.php` (NUEVO)
- `app/modules/Usuarios/views/mi_perfil.php` (NUEVO)
- `app/modules/EmpresaConfig/EmpresaConfigController.php` (Uploads & Colors)
- `app/modules/Empresas/EmpresaRepository.php` (`updateBranding()`)
- `app/modules/EmpresaConfig/views/index.php` (Añadido UI form "Identidad B2C")
- `app/modules/Store/views/layout.php` (Inject logo, colors, footer, LS Dark Toggle)
- `app/modules/dashboard/views/home.php`
- `inject_ui_helper.php` (Script iterador sobre 24 vistas inyectando html attributes)

## Implementación
1. Migración SQL en base de datos.
2. Programación de `UIHelper`.
3. Auto-Iterador para reemplazar `<html lang="es">` de todas las Vistas del sistema previniendo reescribir ecos PHP a mano.
4. Alta de Controlador aislado para Personal Preferences (`Mi Perfil`).
5. Mejora de `EmpresaConfig` sumando subida de imagenes binarias de Logo/Favicon localmente hacia directorios indexados.
6. Overhaul del Layout del Consumidor B2C, conectando los tokens DB contra tags `<style>` in-line.

## Impacto
Positivo. El layout del administrador asume modo Claro/Oscuro dinámicamente en vivo. El Store Público renderiza logotipos y paletas corporativas asiladas por Tenant sin colisionar con otros Operadores.

## Riesgos
- Módulo sin riesgos core. La lógica de layouts se ha modificado únicamente adjuntando variables CSS y directivas localstorage sin afectar enrutamientos o transacciones del sistema Tango.

## Validación
- Navegación inter-portal verificada ok. Las flags `data-theme` responden instantáneamente a LocalStorage. Los archivos multimedia del Tenant se escriben y leen del path global relativo.
