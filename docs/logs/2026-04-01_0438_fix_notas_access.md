# 2026-04-01_0438_fix_notas_access

## Qué se hizo
- Se eliminaron visualmente los campos reservados de "Razón Social" y "CUIT" del formulario de creación de empresas en `app/modules/empresas/views/editar.php`, reemplazando el bloque por inputs hidden.
- Se agregaron los métodos estáticos `hasTiendasNotasAccess`, `hasCrmNotasAccess`, `requireTiendasNotasAccess` y `requireCrmNotasAccess` en `App\Modules\Empresas\EmpresaAccessService.php` para validar los flags configurables de ambos submódulos.
- Se implementó validación en `app/modules/dashboard/views/crm_dashboard.php` que oculta (unset) la tarjeta "Notas CRM" listada en la vista si el módulo no se encuentra activo para la empresa.
- Se protegieron todas las rutas vinculadas a `mi-empresa/crm/notas*` por medio del guard `$requireCrmNotas` en el archivo de rutas principal `app/config/routes.php`.

## Por qué
- Se requería ocultar la tarjeta del submódulo Notas dentro de las aplicaciones cuando la empresa actual tiene deshabilitado dicho módulo desde su configuración particular.
- El panel se mostraba indebidamente pese a estar deshabilitado, exponiendo acceso visual a la opción inhabilitada.
- Se protege también desde backend cualquier acceso directo mediante URL si el módulo Notas no está activo para el CRM de la empresa.

## Impacto
- Mejora de consistencia en el despliegue del Dashboard CRM, donde solo muestran los componentes operativos habilitados.
- Restricción de navegación estricta para asegurar que Notas en CRM no pueda consumirse si no pertenece al plan operativo.

## Decisiones tomadas
- Se mantuvo el esquema condicional actual del dashboard agregando un `unset` selectivo al bloque en lugar de rehacer todo el array de dashboards.
- Se extendió el `EmpresaAccessService` manteniendo el estándar actual del uso de las validaciones de acceso de nivel superior (Tiendas / CRM).
