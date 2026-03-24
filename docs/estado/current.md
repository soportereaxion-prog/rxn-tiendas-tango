# ESTADO ACTUAL

## módulos tocados

* módulo: store (Se extendieron controladores de Checkout, Dashboard "Mis Pedidos")
* módulo: **clientes_web** (Implementación de Autenticación, Hasheos, Contexto Sesión)
* módulo: infra (Migración de DB `password_hash`, Unique Email Index)

## decisiones

* Conservar inmutabilidad entre clientes "guest" vs clientes "registrados", compartiendo el mismo esquema `clientes_web` y mutándose mediante la inyección del `password_hash`.
* Sesiones Client-Side (`store_cliente_id`) completamente desacopladas de las instancias BackOffice (`admin_id`) empleando wrappers dedicados en `ClienteWebContext`.
* Autocompletado transversal desde Base de Datos al form de Checkout de un usuario si navega con sesión activa.

## riesgos

* **PELIGRO DEPLOY LINUX**: Composer ya advirtió un Classmap conflictivo por incompatibilidad de Case-Sensitive en las carpetas base de módulos (Auth != auth). Debe ser solventado vía `git mv` temporal antes del pase final a productivo Unix.
* **Colisión de URLs (Slug Router)**: A nivel MVC, las URLs exclusivas con keyword (ej: `/{slug}/login`) se apilaron intencionalmente en el router **antes** del index maestro `{slug}` para evitar canibalizaciones de URI.

## próximo paso

* Testear en Staging la subida formal.
* Avanzar con cualquier otro requerimiento de la administración.
