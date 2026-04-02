# Migración Masiva de Layout Administrativo

## Detalle
Con la prueba de concepto preaprobada, se diseñó e inyectó un script de automatización (`tools/migrator.js`) encargado de limpiar y centralizar todo el ecosistema de vistas del CRM.

### Estrategia de Sustitución
El script operó en las 35 vistas del backend:
1. Extrajo el bloque PHP nativo (`$basePath`, rutinas locales).
2. Purgó el esqueleto estático de Bootstrap (`<!DOCTYPE html>`, `<head>`, etc).
3. Purgó la declaración manual heredada del banner `require ...backoffice_user_banner.php`.
4. Envolvió dinámicamente todo en el buffer de salida mediante `ob_start()`.
5. Si existían modales o dependencias `<script>` al final del archivo, las empaquetó bajo el buffer opcional de `$extraScripts` definido por nuestro layout.
6. Despachó el volcado global requiriendo el `admin_layout.php`.

### Alcance Finalizado
- ✅ Usuarios (ABM y perfiles individuales)
- ✅ Dashboard (Admin, Home, Tenants, Global)
- ✅ Catálogo (Artículos, Categorias)
- ✅ Configuración Global (Módulo Empresas y Tenant base)
- ✅ Herramientas del CRM (Llamadas, Clientes CRM, Pedidos, Presupuestos)
- ✅ Pedidos Web
- ✅ Configuraciones operativas (SMTP, PrintForms, Operational Help)

### Salvedades e Implicaciones de Arquitectura
- El componente `page_header.php` no fue inyectado de forma invasiva a las pantallas donde la definición del título requería HTML inyectado severamente complejo (`d-flex` y actions anidados), para evitar quiebres. La directiva queda como *best-practice* para su refactor progresivo a demanda.

Con este cambio, logramos erradicar cerca de ~~400 líneas de código~~ redundantes que antes declaraban repetitivamente los CDN y la cabecera en cada vista de backend.
