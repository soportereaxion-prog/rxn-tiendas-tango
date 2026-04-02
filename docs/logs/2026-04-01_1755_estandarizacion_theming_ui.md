# Estandarizacion Global de Theming y Preparacion Dark/Light Mode

## Qué se hizo
Se refactorizó y consolidó la capa visual global en `rxn-theming.css`. Se inyectaron CSS Variables (Tokens) en el bloque raíz (`:root`) y su correspondencia en `html[data-theme="dark"]`, permitiendo reaccionar independientemente a cómo se ven las tarjetas (cards), tablas, formularios e inputs. Adicionalmente, se purgó en todo el DOM de las vistas modulares y layouts la presencia de hardcodes visuales incompatibles (principalmente la clase `bg-white` y sobreescrituras en línea como `style="background: #FFF"`).

## Por qué
El "Theming Engine B2B/B2C" poseía limitantes en los formularios operativos y vistas complejas (como Pedidos de Servicio o Presupuestos CRM), dado que los textareas, inputs y algunos fondos estaban estáticos en esquemas Bootstrap claros. Para poder alternar libremente un Tema Oscuro y Claro, es imprescindible que la interfaz base fluya sobre tokens, unificando la estética de todo el proyecto.

## Impacto
El cambio es drástico en términos de DOM local pero transparente si el theme está activo. Afecta positivamente a todas las pantallas ABM, grillas y módulos transversales (Tiendas, CRM, Listados compartidos y Configuraciones), las cuales respetan ahora las jerarquías de superficie y borde impuestas por el CSS principal.

## Decisiones Tomadas
1. **Paso a variables atómicas:** Mapear `--card-bg`, `--input-bg`, `--table-bg`, entre otros, permitiendo que un componente no pise su color de texto, sino que Bootstrap tome la variable del padre oscuro correctamente.
2. **Purgado de `<element class="bg-white">`:** Fue eliminado en decenas de vistas al igual que sobreescrituras estáticas de fondo, permitiendo que hereden el color correcto de tema.
3. **Mapeo directo en componentes Bootstrap:** `.form-control`, `.card`, y `.table` fueron mapeados en el CSS maestro al `--bg-color` / `--surface-color` asegurando que cambien incluso si Bootstrap pre-renderizó su utility classes. Para las celdas de tabla cabecera `table-light` se dispuso un re-mapeo forzado de opacidad.
4. **Resguardo Dark Checkboxes:** Los selectores `form-check-input` ahora respetan nativamente sus fondos bajo `html[data-theme="dark"]`.
