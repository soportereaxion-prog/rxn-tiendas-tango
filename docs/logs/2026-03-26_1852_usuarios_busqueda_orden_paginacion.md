# [Usuarios] - Busqueda, orden y paginacion server-rendered

## Que se hizo
- Se extendio `app/modules/auth/UsuarioRepository.php` con consultas paginadas y conteos filtrados, tanto globales como acotados por `empresa_id`.
- Se ajusto `app/modules/Usuarios/UsuarioService.php` para normalizar `search`, `sort`, `dir` y `page`, y devolver `items`, `filters`, `total`, `filteredTotal` y `pagination`.
- Se actualizo `app/modules/Usuarios/UsuarioController.php` para pasar los filtros GET al servicio y renderizar el nuevo payload del listado.
- Se refactorizo `app/modules/Usuarios/views/index.php` con buscador, headers ordenables, contador y paginacion server-rendered manteniendo el ABM existente.
- Se agrego una nota breve en `docs/estado/current.md` para reflejar la nueva capacidad del modulo.

## Por que
El listado de usuarios necesitaba alinearse con otros CRUD del backoffice para mejorar navegacion y operacion diaria sin salir del modelo server-rendered actual.

## Impacto
- Admins tenant ahora pueden buscar y navegar solo usuarios de su empresa activa.
- RXN admin mantiene la vista global sin romper el aislamiento por empresa en contexto tenant.
- Se conserva `requireAdmin()` y el uso de whitelists para orden, evitando interpolacion insegura en SQL.

## Decisiones tomadas
- Parametros GET soportados: `search`, `sort`, `dir`, `page`.
- Busqueda limitada a `nombre` y `email`.
- Orden permitido solo por `id`, `nombre`, `email`, `es_admin`, `activo`.
- Paginacion fija en 10 registros por pagina.
- No se agrego AJAX ni nuevas dependencias para sostener simplicidad y coherencia con la arquitectura vigente.
