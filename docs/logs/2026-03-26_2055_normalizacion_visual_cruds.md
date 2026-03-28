# Normalizacion visual de CRUDs

- Que se hizo: se agrego una capa compartida en `public/css/rxn-theming.css` para cards, toolbars, tablas CRUD, headers ordenables, estados vacios y notas de paginacion. Tambien se alinearon los listados de `ClientesWeb`, `Articulos`, `pedidos`, `empresas` y `Usuarios` para usar el mismo patron visual de links ordenables con flechas `▲/▼`.
- Por que: habia dos patrones de orden distintos y varios estilos inline/locales repetidos, lo que generaba inconsistencias visuales entre modulos del backoffice.
- Impacto: los listados conservan su logica actual de filtros, orden y paginacion, pero ahora comparten una base visual mas consistente y facil de mantener.
- Decisiones tomadas: se mantuvieron closures locales por vista para no introducir helpers globales; `empresas` y `Usuarios` abandonaron los indicadores `<>`, `v`, `^`; y se registro en `docs/estado/current.md` la ruta del PHP CLI de referencia para validaciones locales.
