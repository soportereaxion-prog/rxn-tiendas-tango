# [Distribución Base] — [Reestructuración Modular y Cliente REST]

## Contexto de la Iteración
Traspasamos la fase de seguridad y entramos a la proyección. El sistema presentaba una fuerte base MVC nativa, pero carecía de una separación real en "Capas de Arquitectura". Para posibilitar la llegada de Tango (o cualquier API), se requería impedir que los Controladores o Repositorios de base de datos se transformaran en ejecutores sucios de túneles de res (cURL/HTTP).

## Propuesta Adoptada (Modular Limpio)
Se procedió a fragmentar el ecosistema definiendo tres estratos claros:
1. **Core:** Mantiene su jurisdicción de Router, Auth general y Contexto de Sesión (Inalterado).
2. **Modules:** Refugio de los dominios semánticos del negocio (`Auth`, `Empresas`, y la novel inauguración de `Tango`).
3. **Infrastructure:** Nueva capa destinada a piezas agnósticas (Adaptadores genéricos, librerías, conectores base).

## Responsabilidades y Capas Creadas
* `App\Infrastructure\Http\ApiClient` -> Cliente cURL genérico. Su única responsabilidad es resolver HTTP Códigos y devolver respuestas decodificadas o Exceptions de Red. Jamás conocerá el concepto "Multiempresa" o "Tango". Es mudo.
* `App\Modules\Tango\TangoApiClient` -> Cliente adaptado para consumir. Recibe por constructor un Token y URL inyectando los Headers de autorización. Expone métodos concretos (Ej: `getArticulos()`).
* `App\Modules\Tango\TangoService` -> El Arquitecto del Dominio. Pide autorización al Contexto `Context::getEmpresaId()`, consulta los tokens guardados en DB para esta empresa, instancia el Cliente y extrae los Datos crudos.
* `App\Modules\Tango\DTOs\TangoResponseDTO` -> Contrato de Transferencia. Asegura que el Controlador final solo reciba información estandarizada (Éxitos, Fallas y Payload Mapeado) blindando a la WebApp de cualquier sorpresa que mande la API externa.

## Validaciones y Composer
* Inscribimos `"App\\Infrastructure\\"` en los mapas PSR-4 de `composer.json`.
* Comprobamos mediante Script CLI de prueba el comportamiento e instanciación de todas las capas, constatando que un Fallo TCP por URL inventada devuelve prolijamente el DTO informando la traza atrapada sin romper el proceso vital de PHP.

## ⚠️ Deuda Técnica Inminente (Riesgo Producción Linux)
Al ejecutar the _composer dump-autoload_ saltó a la luz una disonancia:
Actualmente el framework tiene directorios en minúsculas (ej: `app/modules/auth`), pero los archivos exigen Namespaces en Capital (`App\Modules\Auth\`). 
Dado que el repositorio de RXN reside hoy en un SO **Windows**, que ignora las mayúsculas en el disco duro, no hay crash. Sin embargo, al deployar en Servidores Linux/Docker (Case-Sensitive), el autoload será incapaz de localizar las rutas, derribando el sistema generalizado como `Class Not Found`. 
**Siguiente Paso Forzado:** Una pequeña iteración puramente dedicada al rename con comandos Git nativos (`git mv`).
