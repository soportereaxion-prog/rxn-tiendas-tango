# Ajustes de Compactación Visual del Frontend ERP (System Spacing)

Se ha ejecutado una fase de densificación del layout maestro en la aplicación para optimizar el **State-Area** (espacio en pantalla utilizable) sin romper el patrón responsive ni causar un efecto apretado visual.

## Criterio Adoptado
El espaciado predeterminado de Bootstrap estaba originando *gaps* muy altos en pantallas que portan decenas de controles de formulario unidos, toolbars largas o grillas de acciones de datos densas (como un ERP real requiere). Se adoptó una reconfiguración orientada primariamente sobre variables sistémicas de nuestro backend: todo control o contenedor que descienda del dom de la aplicación es compactado mediante el framework híbrido, y NO modificando mil *tags inline*.

## Componentes Globales Afectados
1. **Controles de Formulario (`.rxn-page-shell .form-control`, `.form-select`)**:
   - Sus alturas y padding interior se redujeron a medianos (`padding: 0.32rem 0.65rem`), achicando levemente el font.
   - Las etiquetas (`.form-label`) perdieron su desmesurado remate inferior (`margin-bottom: 0.25rem !!important;` sobre 0.5 default) uniéndolas visualmente al input correspondiente.
2. **Layout Base (`admin_layout.php`)**:
   - Se ajustó el acolchado de *topbar* a contenido: el padding-top del body es menor (`pt-2`), el espaciado antes del final se acortó (`mb-4`).
3. **Secciones de Formularios (`.rxn-form-section`, `.rxn-form-grid`, etc)**:
   - Se bajó el *gap* en grillas de `.rxn-form-grid` (de `1..1.25` a `0.75..1rem`).
   - El espacio entre bloques consecutivos de formulario de `1.5rem` a `1.1rem`.
   - Las toolbars inferiores de acciones (`.rxn-form-actions`) pasaron de tener `1.75rem` de margen superior a `1.25rem`.
4. **Header Estandar Base (`page_header.php`)**:
   - Tanto la variante 'standard' como la 'compact' redujeron en un punto de REM sus márgenes inferiores, devolviéndole varios pixeles a la tabla o formulario que lo suceden.

## Ajustes Específicos Excepcionales
Las pantallas pesadas han recibido una sintonía fina de compresión de gap:
- **CrmPedidosServicio**: padding de card interna reducido (`0.95 1.05`), padding de chips resumidos, grilla hiper-densa apretada a `0.65 0.85`.
- **CrmPresupuestos**: `margin-top` del componente exterior resumido, padding de grid `0.35 0.45` de manera segura, permitiendo mucha carga sin scroll abusivo.

## Validaciones
No hubieron sobreescrituras en las piezas puras del entorno (`Tango`, `Auth`, `Landing Publico`) dado que la contención operó inteligentemente enganchada a la bandera `.rxn-page-shell`.
