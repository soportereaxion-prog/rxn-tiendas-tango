# Modulo Empresas — Fase 2

## Contexto
El módulo raíz del sistema (el backoffice central) requiere permitir la edición de la empresa, gestionar su estado visualmente (`activa`) y bloquear duplicaciones del campo `codigo` sin dañar el comportamiento general del proyecto ni su minimalismo.

## Problema
El sistema contaba con herramientas sólidas de inserción básica pero el `Router` no aceptaba match sobre rutas dinámicas tipo `{id}`, el código se podía pisar y no había confirmación de la experiencia UX tras la persistencia.

## Decisión
* Se expandió el método `dispatch` del `Router.php` en unas 12 líneas aplicando Regex `preg_match` para habilitar el enrutado dinámico en caso de fallar como match estricto.
* Se extendió `EmpresaRepository.php` para integrar `update()`, `findById()` y `findByCodigo(?$excludeId)`.
* Se incluyó la validación de duplicación en el _Controller/Service_, de modo que si el código ya existe, rebote con un catch y pinte un render errorizado con la data vieja (`$old`). 
* El estado "Activa" pasó a gobernarse mediante un switch de Bootstrap recuperado en la inserción/des-inserción.

## Archivos afectados
- `app/core/Router.php` (Nuevo soporte regex match)
- `app/config/routes.php` (Agregadas `/empresas/{id}/editar` y `POST /empresas/{id}`)
- `app/modules/empresas/EmpresaRepository.php` (+ Funciones fetch y update)
- `app/modules/empresas/EmpresaService.php` (+ Reglas de validación contra código y active tracking)
- `app/modules/empresas/EmpresaController.php` (+ `$old` binding y update methods)
- `app/modules/empresas/views/index.php` (+ Feedback header & Acciones column)
- `app/modules/empresas/views/crear.php` (+ Switch status / old params)
- `app/modules/empresas/views/editar.php` (Nueva)

## Implementación
El control y visualización de empresas está unificado. El usuario visualiza `Activa` o `Inactiva` en etiquetas en el listado, e interactúa directamente en Alta o Edición. Al editar el nombre o apagar el status de la empresa con URL (`/14/editar`), se invoca al `EmpresaController::update` redirigiendo al home y pintando verde/rojo según éxito u error.

## Impacto
Piedra fundacional del CRUD lograda con la mínima inyección requerida. Preparada para replicarse a Usuarios, Permisos u otras tablas estáticas en el futuro sin reinventar rueda.

## Riesgos
* Las URI en los action de `form` y re-ubicadores de `header(...)` apuntan duramente al prefijo `/rxn_suite/public`. Esto funcionará mientras dicho prefijo represente el Root Project del entorno.
* Posibles edge-cases inyectando carácteres especiales aún no evaluados al no tener un _Sanitizer Service_.

## Validación
- Exitoso crear sin Activa y Crear con Activa en CLI e invocaciones simuladas.
- Exitoso denegación por código repetido evaluado tanto en Save como en Update.
- Log CLI reporta 100% Ok.
