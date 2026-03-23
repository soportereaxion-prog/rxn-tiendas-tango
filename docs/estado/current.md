# ESTADO ACTUAL

## módulos tocados

* módulo: empresas (Fase 1 y Fase 2 - Alta, Listado, Edición, Gestión de Estado)
* módulo: core (Router regex y **Contexto Multiempresa Fase 0**)

## decisiones

* Se implementó el módulo Empresas garantizando MVC limpio según el estándar del proyecto.
* Se agregó un "fallback" Regex al constructor del Router en `app/core/Router.php` para soportar parámetros URL.
* **Fase 0 Multiempresa**: Se incorporó la clase `App\Core\Context` en el arranque (`App::run()`). Esto establece el concepto de "empresa activa en contexto" (`empresa_id`). Hoy lee estáticamente o por `$_GET['empresa_id']` a fines estructurales, preparando la cancha para la integración de Usuarios y Sesiones sin romper el backoffice (que es agnóstico a esto).

## riesgos

* Las futuras entidades (productos, pedidos) deben ahora diseñarse pensando en poseer la FK `empresa_id`.
* La ruta `/rxnTiendasIA/public` sigue inyectada duramente en redirecciones `header()`. Hay que modularizar esto.

## próximo paso

* Comenzar el desarrollo de entidades dependientes del contexto empresarial (ej: Usuarios de cada Empresa) o refinar el sistema de autenticación real.
