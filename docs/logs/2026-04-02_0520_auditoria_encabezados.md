# Auditoria de Encabezados y Topbars

## 1. Resumen Ejecutivo
Se realizó un escaneo completo de todas las interfaces (`views`) del ecosistema rxnTiendasIA (Tiendas, CRM y Admin). El objetivo fue identificar cómo se estructuran actualmente los encabezados, barras de navegación y bloques superiores de contexto (títulos, subtítulos, botones de acción).
Se ha detectado una alta dispersión en la construcción de estas piezas. Existen más de 4 variaciones principales para armar "la cabecera de la página", y la falta de un layout unificado en el entorno CRM/Admin provoca que la estructura base de HTML (`<!DOCTYPE...>`, `<head>`, `<body>`) se repita en decenas de archivos junto con el bloque superior.

## 2. Inventario de pantallas / vistas auditadas
Se relevaron exhaustivamente los siguientes módulos y sus respectivos directorios de vistas (`app/modules/*/views/` y `app/shared/views/`):
- **Store**: `layout.php`, `show.php`, `index.php`, `mis_pedidos/*` (Ecosistema público y carrito)
- **Usuarios**: `index.php`, `crear.php`, `editar.php`, `mi_perfil.php`
- **Dashboards**: `crm_dashboard.php`, `tenant_dashboard.php`, `admin_dashboard.php`
- **CRM Notas**: `index.php`, `show.php`, `form.php`
- **Módulos Generales CRUD**: Empresas, EmpresaConfig, PrintForms, Help, CrmPresupuestos, CrmPedidosServicio, CrmLlamadas, CrmClientes, Categorias, Articulos, Admin...

## 3. Inventario de encabezados encontrados

Se detectaron las siguientes estructuras de encabezados en el sistema:

1. **Header Administrativo Estándar (`.rxn-module-header`)**:
   - Usado en: Formularios (`Usuarios/crear.php`), Listados (`CrmNotas/index.php`).
   - Características: Utiliza una clase CSS dedicada que encapsula el título (`<h2>` o `h1`), el subtítulo (`.text-muted`) y un bloque de acciones junto (botones de ayuda, volver o crear).

2. **Header Administrativo Desarmado (Raw `.d-flex`)**:
   - Usado en: Listado de usuarios (`Usuarios/index.php`), y otros listados antiguos.
   - Características: En lugar de usar la clase semántica, implementa clases de utilidad directamente en el HTML: `<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">`.

3. **Dashboard Header con variaciones (`.rxn-module-actions`)**:
   - Usado en: `crm_dashboard.php`
   - Características: Utiliza `.rxn-module-header` pero le inyecta clases utilitarias drásticas en línea (`mb-5 pb-2 border-bottom border-secondary border-opacity-25`). En lugar de poner los botones sueltos, envuelve las acciones en `.rxn-module-actions` y hace un `require` al `user_action_menu.php` directamente dentro.

4. **Topbar Público / de Tienda (`.navbar-store`)**:
   - Usado en: `Store/layout.php`
   - Características: Una navbar pegajosa (`sticky-top`) de Bootstrap que actúa como contenedor global, con el logo, enlaces de categorías, botón de modo oscuro (inline JS), avatar del usuario (si está logueado) y el botón del carrito. Se centraliza bien dentro del layout.

5. **Breadcrumbs Contextuales (`.breadcrumb`)**:
   - Usado en: Store `show.php` (Detalle de Producto), `mis_pedidos/show.php`
   - Características: Bloque semántico `<nav aria-label="breadcrumb">` que se inyecta suelto en el body de la vista antes del contenido principal, sin conexión estructural con el header de la página.

6. **Banners de Sesión Inyectados**:
   - Usado en: Inicio del `body` en varios módulos (ej: `require '.../backoffice_user_banner.php'`).

## 4. Problemas detectados
- **Escalabilidad y Mantenimiento**: Cada archivo `index.php`, `crear.php` o `editar.php` en el backend redefine su propio bloque de HTML, el tag `<title>`, los `<link>` a hojas de estilo, y el bloque de encabezado. Modificar el margen de los encabezados (ej: pasar de `mb-4` a `mb-5`) exige buscar y reemplazar en 40+ archivos.
- **Duplicidad Estructural**: Existen listas que usan `<div class="d-flex...">` y otras que usan `<div class="rxn-module-header">` para lograr exactamente el mismo aspecto visual y funcional.
- **Acoplamiento de Acciones**: En el backend, las acciones ("Volver", "Crear", "Importar") están hardcodeadas dentro del HTML de la cabecera en la vista principal, lo que facilita el desalineamiento visual.
- **Estilos Inline**: Abundan correcciones ad-hoc (ej: `style="border-bottom-width: 2px;"` o clases como `pb-3 border-bottom border-secondary border-opacity-25`) para separar el header del contenido.
- **Mezcla de Entidades**: Se mezclan datos de usuario (el menú de cuenta) junto a acciones de módulo (boton "Ayuda" o "Volver") en el mismo contenedor flex, forzando a crear sub-agrupaciones caprichosas en cada vista.

## 5. Patrones repetidos
- La gran mayoría de los módulos de la aplicación (fuera del ecosistema público "Store") consisten en un título principal, un texto descriptivo o label del entorno ("Base inicial de..."), y una botonera a la derecha.
- Los módulos de Tiendas/Público se apoyan consistenemente en `layout.php` (buena práctica) pero luego manejan los breadcrumbs localmente por vista.

## 6. Inconsistencias
- **Márgenes inferiores**: Algunos componentes usan `mb-4` (formularios estándar), otros usan `mb-5 pb-2` (dashboards).
- **Semántica**: A veces el título es un `<h2>`, a veces un `<h1 class="h3">`, a veces `<h1 class="hero-title">`.
- **Estructura del Backoffice**: No existe un archivo `admin_layout.php`. El header se repite archivo por archivo, impidiendo tener una "Topbar Global" o una barra lateral sólida sin reescribir docenas de vistas.

## 7. Posibles componentes base a unificar
1. **Admin / CRM Global Layout**: Un contenedor marco que inyecte `<head>`, incluya un "Topbar/Banner de Entorno" y un área central de contenido.
2. **Componente `PageHeader`**: Un bloque reutilizable al que se le inyecte `$titulo`, `$subtitulo` y un bloque opcional de `$acciones`.
3. **Componente `BreadcrumbNav`**: Un generador de migas de pan para el ecosistema público.
4. **Topbar Modular**: Un componente separado que mantenga los menús de usuario, el switch de temas (luna/sol) y el botón del entorno.

## 8. Propuesta de estrategia de unificación en una próxima iteración
**NO implementar aún. Estrategia propuesta:**
1. Crear el archivo `app/shared/views/admin_layout.php`.
2. Encapsular la lógica de estructura de la página y el banner de sesión global dentro del Layout, abstrayéndolo de las vistas finales (igual a cómo se estructuró `Store/layout.php`).
3. Crear un bloque o helper `UI::renderPageHeader($title, $subtitle, $actions_html)` que normalice la inyección del encabezado con clases seguras (`.rxn-module-header`, `mb-4`, etc.).
4. Migrar los módulos progresivamente: abrir cada vista, borrar la repetición de `<head>`, envolver el contenido en un `ob_start()`, llamar a `renderPageHeader` y requerir el nuevo `admin_layout.php`.
5. Estabilizar y unificar los márgenes (`mb-4` vs `mb-5`) con tokens CSS globales del sistema de theming.

## 9. Riesgos y observaciones
- **Riesgo Operativo del Refactor**: Migrar todo hacia un layout único implica tocar casi 50 archivos simultáneamente. Una mala etiqueta sin cerrar rompería las pantallas. Debe hacerse asegurando el backup o un branch limpio.
- **Riesgo Visual**: Al unificar títulos `h1`/`h2`, algunos módulos podrían sentirse temporalmente "distintos" si tenían ajustes personalizados finos.
- **Observación Positiva**: Pese a la repetición estructural, el marco de diseño es bastante regular. Bootstrap flexbox facilita que la migración a un componente único (ej: `PageHeader`) sea limpia y con poca fricción de código CSS personalizado.
