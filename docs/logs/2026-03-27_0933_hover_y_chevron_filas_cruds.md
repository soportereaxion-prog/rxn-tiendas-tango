# [UI + CRUDS] - Hover reforzado y chevron minimo en filas navegables

## Que se hizo
- Se reforzo el feedback visual de `public/css/rxn-theming.css` para filas navegables con hover mas evidente, borde lateral y foco visible mas claro.
- Se agrego una columna minima tipo chevron en los listados ya convertidos a fila clickeable.
- Se reemplazo el encabezado textual `Acciones`/`Accion` por una columna visual compacta en `Clientes Web`, `Pedidos`, `Usuarios`, `Empresas`, `Articulos` y `Mis Pedidos`.

## Por que
- La navegacion por fila ya existia, pero faltaba una pista visual mas fuerte para que el usuario la percibiera sin pensar demasiado.
- Los botones con texto quedaban redundantes una vez que la fila entera ya funcionaba como acceso principal.

## Impacto
- Los CRUDs ahora se sienten mas directos y consistentes: hover claro + chevron final = patron mas obvio y mas limpio.
- Se reduce ruido visual en tablas administrativas sin sacrificar accesibilidad ni affordance de navegacion.

## Decisiones tomadas
- Se mantuvo un acceso secundario minimo (`›`) al final de la fila como respaldo visual y teclado-friendly.
- No se quitaron elementos interactivos especiales (`mailto`, checkboxes) y siguen protegidos contra navegacion accidental.
