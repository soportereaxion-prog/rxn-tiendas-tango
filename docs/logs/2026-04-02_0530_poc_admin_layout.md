# Diseño Base de Unificación de Layouts (Proof of Concept)

## Objetivo
Implementar la arquitectura base y los componentes para que el backend administrativo deje de repetir su capa estructural de HTML. Se trabajó sobre un pequeño subconjunto de vistas para validar qué tan robusta es la solución antes de realizar una migración masiva.

## Estructura Creada

### 1. `app/shared/views/admin_layout.php`
Se generó el layout contenedor oficial para las herramientas de backoffice. Su responsabilidad es inyectar la cabecera estándar de HTML5, las hojas de estilo del sistema, e importar automáticamente el banner del entorno operativo (`backoffice_user_banner.php`). 
Dispone de variables de hook:
- `$pageTitle`: Título explícito de la pestaña.
- `$extraHead`: Para tags `<style>` o scripts que deben ejecutarse prescriptivamente.
- `$extraScripts`: Para inyectar JS customizado al final (como modales de JS o fetchers locales).
- `$content`: Receptor del buffer principal.

### 2. `app/shared/views/partials/page_header.php`
Se aisló la generación técnica del encabezado visible `.rxn-module-header`. 
Recibe sus configuraciones por variable, como:
- `$title`: El título del módulo.
- `$subtitle`: La descripción (opcional).
- `$iconClass`: Un ícono (opcional).
- `$actionsHtml`: Botones principales o custom renderizados por la vista.
- `$backUrl`: Habilitación condicionada del botón volver.

## Ámbito de prueba (Vistas Migradas)

Se modificaron tres contextos radicalmente opuestos para garantizar versatilidad:

1. **Dashboard (`app/modules/dashboard/views/crm_dashboard.php`)**
   - El caso más complejo, con scripts de ordenamiento (`Sortable`), estilos inline y acciones que inyectaban modales u otros bloques PHP.
   - Pudo acoplarse con éxito reemplazando toda la cabecera e inyectando `extraHead` para el `<style>`, y `extraScripts` para su JS.

2. **Index CRUD estándar (`app/modules/CrmNotas/views/index.php`)**
   - Una pantalla simple de módulo. Demostró lo rápido que se vuelve reestructurar vistas básicas (simplemente abrir buffer, cerrar buffer y requerir el layout).

3. **Formulario (`app/modules/Usuarios/views/crear.php`)**
   - El formulario de alta. Al estar originalmente desprovisto del topbar (`backoffice_user_banner.php`), el layout le otorgó de forma automática el menú, el modo oscuro y la sesión de una forma uniforme, ganando un upgrade sin escribir nada extra.

## Evaluación para migración completa

**¿Es suficiente para escalar al resto del sistema?**
**Sí, con extrema robustez.** 
El mecanismo planteado usando `ob_start()`, `$extraHead` y `$extraScripts` permite migrar paulatinamente el sistema sin chocar. Cada vista que entra a funcionar dentro de este "admin_layout" recibe gratis todas las correcciones estéticas y mejoras del sistema.

### Ajustes sugeridos para migración masiva:
1. **Atención con los modales**: Los módulos que tienen inicialización inline de Modales Bootstrap (`CrmNotas`, por ejemplo) necesitan que se deje bien aislado el JS en `$extraScripts` antes de volcar el layout. Recomendable extraer todos los `confirm` a la vía nativa `data-rxn-confirm`.
2. **Revisar doble rendering**: Cuando se migró `crm_dashboard`, se detectó que originalmente incluía `user_action_menu.php`. Esta sección desapareció limpiamente dentro del `admin_layout` a través del banner principal. Habrá que borrar esos snippets esporádicos en otras áreas del código.

## Conclusión

El camino está totalmente preparado. Cuando sea requerido, todos los módulos restables (`Articulos`, `Empresas`, `Categorias`) podrán volcarse a esta base con tan solo unas ~10 líneas de modificación por archivo, logrando que el backend tenga finalmente un único punto de entrada HTML para su marco contextual.
