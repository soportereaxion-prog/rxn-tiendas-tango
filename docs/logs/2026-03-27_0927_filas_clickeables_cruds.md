# [UI + CRUDS] - Filas clickeables en listados

## Que se hizo
- Se agrego un patron transversal de fila clickeable para listados CRUD mediante `data-row-link` y el nuevo script `public/js/rxn-row-links.js`.
- Se aplico el patron en los listados de `Clientes Web`, `Pedidos`, `Usuarios`, `Empresas`, `Articulos` y `Store/Mis Pedidos`.
- Se simplificaron las acciones visuales por fila dejando un acceso minimo `↗` como affordance secundaria, en lugar de textos largos tipo `Ver Detalle` o `Ver/Editar`.
- Se incorporo estilo compartido en `public/css/rxn-theming.css` para cursor, foco visible y comportamiento de affordance.

## Por que
- La navegacion por detalle desde tablas administrativas era mas lenta de lo necesario y obligaba a apuntar a botones demasiado verbosos.
- El usuario pidio que la fila completa funcionara como acceso natural al detalle/edicion en todos los CRUDs posibles.

## Impacto
- La experiencia de navegacion en backoffice queda mas directa: clic en cualquier parte de la fila y se abre el registro.
- Se preservan acciones secundarias dentro de la fila cuando existen (`mailto:` en clientes, checkboxes en articulos) evitando navegacion accidental.
- El patron queda reutilizable para futuros listados sin necesidad de reescribir logica por modulo.

## Decisiones tomadas
- Se uso un script opt-in via `data-row-link`, para no afectar tablas que no deban navegar por fila.
- Los elementos interactivos internos quedan excluidos por selector (`a`, `button`, `input`, etc.) o `data-row-link-ignore`.
- En `Articulos` se mantuvo especial cuidado con la seleccion masiva para no romper el checkbox ni el flujo de eliminacion.
