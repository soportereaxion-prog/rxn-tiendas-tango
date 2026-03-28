# [UI] - Capa responsive B2B conservadora

## Que se hizo
- Se incorporo una capa responsive global y reutilizable en `public/css/rxn-theming.css`.
- Se adaptaron los launchers y dashboards administrativos para que sus encabezados, contenedores y tarjetas respiren mejor en mobile.
- Se ajustaron headers, toolbars, filtros, tablas y paginaciones de modulos clave del backoffice para evitar desbordes horizontales en pantallas chicas.

## Por que
- Varias pantallas del panel estaban pensadas principalmente para escritorio y en celular quedaban descuadradas por anchos fijos, toolbars en linea y tablas sin estrategia comun de compactacion.

## Impacto
- El backoffice mantiene el look actual en desktop pero ahora colapsa de forma mas ordenada en mobile.
- Formularios, acciones y buscadores pasan a apilarse cuando el ancho no alcanza.
- Las tablas siguen siendo legacy-friendly mediante scroll horizontal controlado, sin reescribir los listados a componentes complejos.

## Decisiones tomadas
- Se mantuvo Bootstrap 5 + CSS propio, sin frameworks adicionales.
- Se eligio una estrategia conservadora: clases reutilizables (`rxn-module-header`, `rxn-filter-form`, `rxn-table-responsive`, `rxn-pagination-wrap`) y media queries acotadas.
- Se priorizaron primero vistas B2B de mayor uso: launchers, dashboards, configuracion, empresas, usuarios, articulos, pedidos, clientes y SMTP global.
