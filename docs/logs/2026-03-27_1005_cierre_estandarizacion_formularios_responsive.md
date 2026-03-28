# [UI] - Cierre de estandarizacion responsive en formularios restantes

## Que se hizo
- Se terminaron de unificar los formularios restantes del backoffice y auth interno sobre el mismo patron responsive.
- Se ajustaron `EmpresaConfig`, `SMTP global`, `Mi Perfil`, `Login`, `Forgot`, `Reset` y `Resend` para usar contenedores, tarjetas y acciones consistentes.
- Se agregaron utilidades CSS complementarias para shells de autenticacion y formularios compactos.

## Por que
- Aun quedaban vistas con estilos aislados, alto fijo de viewport o maquetados angostos que rompian la consistencia visual y la experiencia mobile.

## Impacto
- Todo el circuito de formularios administrativos y de acceso conserva una misma logica visual en escritorio y telefono.
- Los formularios compactos de auth quedan centrados y respirables sin depender de anchos duros poco flexibles.
- `EmpresaConfig` y `SMTP global` ahora encajan mejor con la familia visual definida para el backoffice.

## Decisiones tomadas
- Se mantuvo el enfoque conservador: PHP server-rendered, Bootstrap 5 y CSS propio.
- Se evitaron redisenos funcionales; el trabajo se concentro en layout, jerarquia y adaptacion responsive.
