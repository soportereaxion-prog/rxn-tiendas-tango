# Depuración de Dashboards y Sistema de Búsqueda Estándar

## Qué se hizo
1. Se limpió de la vista inicial del CRM (`crm_dashboard.php`) un bloque o leyenda de texto fija explicativa que "ensuciaba" la interfaz operativa, cumpliendo la indicación explícita de evitar estas aclaraciones salvo instrucción en contrario.
2. Se construyó un componente unificado `dashboard_search.php` que consiste en un buscador para módulos (con lupa y de estilos minimalistas), y provee filtrado local vía un script JavaScript.
3. Este nuevo componente de búsqueda se inyectó transversalmente sobre todos los entornos de Dashboards habilitados: Launcher Principal, Admin Backend, Tenant Tiendas y Tenant CRM.
4. El script implementa un handler para los atajos del teclado `F3` y `/` que intercepta y enfoca instantáneamente el Search Bar sin importar la pantalla o módulo siempre que el operador no esté tecleando dentro de un input previo.
5. Se normalizó la regla de diseño y se documentó globalmente en el archivo `AGENTS.md`.

## Por qué
- Había una alerta fija poco amena en la cabeza del CRM ("CRM arranca con una base...") que ya no es necesaria y generaba ruido. La regla a partir de ahora prohíbe las leyendas en nuevas UI para maximizar limpieza.
- A medida que el número de módulos se expanda o si el perfil requiere acceso a más paneles, iterar o desplazarse entre 15 o 30 tarjetas empieza a ser impráctico para el operador. Tener una barra de búsqueda local estandarizada mediante atajos agiliza la navegación tipo "Power User" (una constante en este proyecto).

## Impacto
- Las cabeceras de CRM ahora son consistentes en limpieza con Tiendas y el Launcher.
- Cero tiempo perdido buscando módulo de forma visual; se provee una vía inmediata accionable desde el teclado para usuarios experimentados.
- El sistema cuenta con reglas base para futuros despliegues a acatar (referencia AGENTS.md + search bar siempre visible).

## Decisiones tomadas
- En vez de replicar código JavaScript idéntico en cuatro dashboards (Home, CRM, Tiendas, Admin) se consolidó un único snippet que busca elementos de manera difusa y abstrae que la estructura sea de una única columna `col-` (ej admin_dashboard) o una columna ordenable `.rxn-sortable-col` encontrando el sub-nodo genérico y escondiendo su elemento contenedor (`wrapper`), minimizando drásticamente la repetición.
- Se filtraron los eventos KeyDown para que el acceso con tecla `/` no dispare errores ni rompa el tipeo natural dentro de módulos futuros al evitar hacer "focus stealing" si el usuario ya interactúa con `<input>`, `<textarea>` o `<select>`.
