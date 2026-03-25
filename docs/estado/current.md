# ESTADO ACTUAL

## módulos tocados

* módulo: Theming Engine B2B/B2C (`UIHelper`, `rxn-theming.css`)
* módulo: Admin / dashboard (`home.php`, inyecciones `<html>` en todo el backoffice)
* módulo: Store (`layout.php`, LocalStorage toggle)
* módulo: EmpresaConfig (Configuración Backend Tenant)
* módulo: Usuarios (Nuevo sub-layout `mi-perfil`)
* módulo: DB Schema (`empresas`, `usuarios`)

## decisiones

* **Capa Theming B2B:** Se rechaza el themer centralizado de Boostrap clásico en pos de variables CSS puras inyectadas mediante un Helper dinámico (`UIHelper`) en runtime, evaluando la BDD o Sesión (Light/Dark Mode; Fuentes `sm/md/lg`).
* **Branding Tenant (Store):** El `layout.php` público extrae parámetros corporativos (Logo, Colores hexadecimales primario/secundario y metadatos sociales de Footer) hidratando de forma autónoma cada `/tienda` generada en tiempo real.
* **Separación de pre-conceptos de DB:** Para los Themes B2C (Colors/Logo) el `EmpresasRepository` afecta directamente las columnas nativas de la tabla en vez de un string JSON. Esto garantiza búsquedas rápidas si el motor debe evolucionar, reduciendo carga lógica.
* Las configuraciones del Dark Mode B2C se manejan en `LocalStorage` por lado cliente; no se registra en BDD, garantizando la velocidad sin queries redundantes para "usuarios invitados".

## riesgos

* El iterador generador inyectó rutas `<link>` en cabeceras dependientes del contexto `/rxnTiendasIA/public/`. Si el servidor cambia de ruta base a la raíz de dominio completa, es crítico ajustar `rxn-theming.css` URI y variables de upload.
* Si el Tenant sube logos `.svg` rotos, el Store frontend podría crashear su ratio visual temporalmente en cabeceras. (Validados por MIME superficial).

## próximo paso

* Testeo global en servidor DonWeb para asegurar compatibilidad con PHP 8.2 estricto alojado remotamente.
* Avanzar en siguientes características propuestas en la iteración del proyecto (Por ej: refinamientos de flujo transaccional Tango Connect si las hubiere).
