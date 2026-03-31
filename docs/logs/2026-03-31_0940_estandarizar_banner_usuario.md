# Estandarización de Banner de Usuario

## Qué se hizo
Se reemplazó el renderizado condicional del usuario (`$_SESSION['user_name']`) aislado en los dashboards por un componente reutilizable `user_action_menu.php` a través de los diversos módulos del proyecto. Se inyectó este componente contiguo a los botones de acción dentro del contenedor `.rxn-module-actions`. 

## Por qué
El usuario indicó que "arriba se ve el usuario, pero solo se ve apenas entrás, en los otros módulos no es consistente, habría que estandarizar el ver usuario y que apareza al lado el botón de cerrar sesión". Al navegar a módulos como Artículos o Clientes Web, no se veía quién estaba logueado ni había una forma ágil de cerrar sesión.

## Impacto
- Se estandarizó transversalmente la experiencia de navegación para administradores.
- Todos los módulos que extienden hacia el backend (Dashboards, CRUDs, Configuración, Mi Perfil, PrintForms, Ayuda) cuentan ahora con un banner consistente que refleja la sesión actual e incorpora la opción de `logout`.

## Decisiones tomadas
1. Abstracción del elemento: Se creó el componente `app/shared/views/components/user_action_menu.php`.
2. Estructura visual: Se diseñó como un elemento _pill_ con flexbox, empleando clases nativas de Bootstrap 5 (`bg-light`, `border`, `rounded-pill`) para no sobrecargar el `rxn-theming.css` e integrarlo prolijamente al contenedor `rxn-module-actions`.
3. Despliegue masivo: En vez de un refactor manual, se realizó un barrido sobre las vistas internas, insertando la abstracción como el primer _child_ de la barra de acciones.
