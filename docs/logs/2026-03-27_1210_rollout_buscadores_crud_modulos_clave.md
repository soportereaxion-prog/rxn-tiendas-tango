# [UX] - Rollout de buscadores CRUD en modulos clave

## Que se hizo
- Se desplego el patron completo de buscadores con sugerencias sin autofiltro en `usuarios`, `articulos`, `clientes` y `pedidos`.
- Se extrajo una base comun reutilizable en `public/css/rxn-theming.css` y `public/js/rxn-crud-search.js`.
- Se mantuvo `empresas` alineado con la misma base comun.

## Por que
- Hacia falta cerrar la experiencia de busqueda del backoffice con un comportamiento consistente antes de pasar a otros temas funcionales.

## Impacto
- Los CRUD principales del sistema ahora comparten una UX de busqueda parecida.
- Las sugerencias ayudan a encontrar registros sin disparar autofiltros agresivos.
- Queda terreno preparado para replicar el mismo patron en el futuro CRM.

## Decisiones tomadas
- Se mantuvo el enfoque simple del proyecto: listados server-rendered + endpoint minimo de sugerencias por modulo.
- Se separo valor editable y valor confirmado en todos los buscadores migrados.
