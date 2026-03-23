# ESTADO ACTUAL

## módulos tocados

* módulo: empresas (Fase 1 y Fase 2 - Alta, Listado, Edición, Gestión de Estado)
* módulo: core (Router - matching regex para variables en URLs)

## decisiones

* Se implementó el módulo Empresas garantizando MVC limpio según el estándar del proyecto.
* Se agregó un "fallback" Regex al constructor del Router en `app/core/Router.php` para soportar `GET /empresas/{id}/editar` y `POST /empresas/{id}`.
* Se integró el checkbox `activa` procesado desde formulario y un servicio simple de validación contra código de base de datos (`findByCodigo`) bloqueando repetición.
* Integración visual de los mensajes de feedback de transacciones operativas a lo largo de las vistas de Empresas.

## riesgos

* La ruta `/rxnTiendasIA/public` fue inyectada duramente en redirecciones `header()`. Hay que modularizar esto en una variable genérica.
* Sigue pendiente un mecanismo de Soft Delete o la eliminación física.

## próximo paso

* Expandir hacia panel de seguridad y logueo, o adentrarse en la sub-arquitectura por sucursal / empresa de contexto interno.
