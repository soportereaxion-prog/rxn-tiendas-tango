# Hotfix: Elemento no cerrado en Backoffice User Banner

**Fecha**: 2026-04-03
**Módulo afecado**: UI Global (`backoffice_user_banner.php`)

## Qué se hizo
1. Se cerró correctamente un tag `<div>` de agrupamiento de flexbox que se introdujo en la subida anterior durante la adición del `topbar-left-zone`.

## Por qué
El tag `<div>` de flexbox que envolvía los botones de sesión y tienda a la derecha no tenía clausura final, lo que provocaba que el `</div>` del `container-fluid` externo cerrara primero el container flex, y a su vez, dejara abierto el container del header. Esto causaba un efecto cascada rompiendo el flujo del árbol DOM, tragándose a la estructura principal `main` debajo de sí mismo, comprimiendo la grilla del dashboard y arruinando el layout de todas las vistas del nivel superior.

## Result
El árbol DOM ya se encuentra re-equilibrado.
