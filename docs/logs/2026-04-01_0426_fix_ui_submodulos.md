# Ajuste visual de submódulos en formulario de empresas

**Fecha:** 2026-04-01
**Módulo afectado:** Empresas (`app/modules/empresas/views/editar.php`)

## Qué se hizo
Se ajustó el layout visual de los checkboxes de configuración de los submódulos ("Módulo Notas en Tiendas" y "Módulo Notas en CRM"). 
Originalmente se presentaban como checkboxes estándar flotando en un contenedor con borde y fondo (`bg-light`), lo cual generaba ruido visual y problemas de legibilidad en modo oscuro (`rxn-theming.css`). 
Se cambió la diagramación para que ahora se presenten como un bloque tipo `flex-row` (`d-flex flex-wrap gap-3 mt-3 pt-3 border-top border-secondary border-opacity-25`) justo debajo del switch de la aplicación padre. 
Además, se adaptaron para usar la clase estándar `form-switch` de Bootstrap (igual que los switches principales) en lugar de checkboxes regulares.

## Por qué
- Para lograr mayor coherencia visual en todos los formularios de configuración de aplicación.
- Para evitar la rotura de estilo en el dark mode (el `bg-light` sobre el card grid no escalaba visualmente con el theming de RXN).
- Porque a futuro se irán agregando más submódulos (ej: "Catálogo extendido", "Turnos"), y el listado horizontal (con `flex-wrap`) en formato `form-switch` permite escalabilidad UI donde se ubican elegantemente uno al lado del otro.

## Impacto
UI más limpia, modular y consistente con las variables CSS de Bootstrap + rxn-theming. Solo cambios de presentación (frontend), la lógica de sincronización por JS usando los atributos `data-empresa-dependiente` y `data-empresa-subdependiente` se mantiene idéntica y completamente funcional.
