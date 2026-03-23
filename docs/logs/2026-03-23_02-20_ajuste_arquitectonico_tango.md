# [Ajuste Arquitectónico] — [Tango API Integración]

## Contexto
Siguiendo los lineamientos estrictos dictados por la Jefatura para la Arquitectura de RXN, se procedió a refinar el contrato interno del módulo REST Tango y de la Infraestructura HTTP genérica. El objetivo fue garantizar el desacople absoluto entre los errores de negocio y las fallas de red, y fortalecer la composición de clases.

## Decisiones y Optimizaciones Aplicadas
1. **Composición sobre Herencia:** Se examinó la relación entre `TangoApiClient` y la base abstracta. Se ratificó el uso del patrón **Composición** (`private ApiClient $client`), lo cual permite moldear el cliente final inyectándole autorización Bearer sin hacerlo heredar métodos cURL expuestos.
2. **Independencia del Contexto:** El `ApiClient` crudo y el `TangoApiClient` específico son totalmente agnósticos a qué sistema o cliente los invoca. Todo el peso de averiguar el `$empresaId` (vía `Context::getEmpresaId()`) recae netamente en la capa de Orquestación (`TangoService`), cumpliendo con la separación de responsabilidades.
3. **Manejo Granular de Errores Técnicos:** Se inauguró el set de `App\Infrastructure\Exceptions`. El generador de peticiones abandonó los `RuntimeException` genéricos transicionando a un sistema identificable:
    - `ConnectionException`: Fallas nativas de cURL (Timeouts, DNS Resolvers).
    - `UnauthorizedException`: Trappers específicos para HTTP Códigos `401` y `403`.
    - `ConfigurationException`: Disparado tempranamente cuando un Servicio intenta invocar a la API pero carece de llaves vitales extraídas de la DB.
    - `HttpException`: Cobertura general para errores Server `5xx` y Malformed Requests `4xx`.
4. **Contrato de Configuración Explícito:** El `TangoService` deja asentado en la superficie de su núcleo de inyección que aguarda las llaves `tango_api_url` y `tango_api_token` derivadas de la DB Corporativa. De no encontrar configuración viable, eyecta una `ConfigurationException`.

## Ausencia deliberada de Patrones Innecesarios
* **Sin Repository:** Se acató la directriz de no introducir clases Repositorio en este bloque, ya que el Módulo Tango es hoy por hoy un Consumidor/Productor REST y no muta el modelo relacional propio.
* **Pureza de Shared:** Se evitó alojar Helpers HTTP en carpetas transversales y quedaron encapsulados donde les corresponde (Infrastructure).

## Resultados en Código y Repositorio
* Autoload de Composer refrescado integrando el nuevo conjunto de Namespaces en `Infrastructure`.
* Commit `refactor: ajuste arquitectonico excepciones y contratos` enviado a Branch de distribución productiva.
