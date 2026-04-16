# Implementación de Exportación a Excel en Módulo Notas CRM

## Qué se hizo
- Se agregó el botón "Exportar a Excel" en la vista principal del módulo Notas CRM (`app/modules/CrmNotas/views/index.php`).
- Se implementó el método `export()` en `CrmNotasController` utilizando `OpenSpout` para generar y descargar un archivo XLSX al vuelo (sin guardar temporalmente en disco).
- Se respetaron los filtros y estado actuales (Activos o Papelera, Orden y Búsqueda) usando los parámetros disponibles en `$_GET`.
- Se agregó la definición de la ruta de exportación en `app/config/routes.php` (`/mi-empresa/crm/notas/exportar`).

## Por qué
- Requerimiento del usuario para complementar la funcionalidad de importación actual con una herramienta para descargar la información del módulo en el mismo formato.

## Impacto
- Mejora la experiencia y usabilidad del CRM.
- No requiere esquemas de base de datos nuevos ni modificaciones severas en arquitectura. Se aprovecha la librería instalada previamente (OpenSpout).

## Decisiones tomadas
- Se estableció un límite de iteración de `999,999` filas al llamar al repositorio subyacente para recuperar todos los registros sin lidiar con los offsets de la paginación tradicional de cara a la exportación masiva rápida.
- Se pasan el `search`, el estado de la papelera y la configuración de ordenamiento actual directamente por los parámetros en query string para que lo que el usuario esté viendo (filtros) coincida con la información descargada.
