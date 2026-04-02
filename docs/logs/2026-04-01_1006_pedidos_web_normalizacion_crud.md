# Estandarización del Módulo Pedidos Web

## Qué se hizo
Se normalizó la interfaz y la lógica subyacente del módulo **Pedidos Web** para que cumpla con el estándar "gold standard" de la aplicación (definido previamente en `ClientesWeb`).

Los cambios incluyen:
1.  **Migración de Base de Datos**: Se añadió la columna `activo` (TINYINT) a la tabla `pedidos_web` usando un script propio (`migrate_pedidos.php`) en lugar de depender de herramientas externas no compatibles.
2.  **Interfaz Gráfica (UI)**:
    - Se incorporó la navegación por pestañas (Activos / Papelera).
    - Se reestructuró la Action Bar utilizando el layout estándar `rxn-toolbar-split`.
    - Se eliminaron forms individuales dispersos a favor de la arquitectura de formulario encapsulado global `hiddenFormBulk`.
    - Se implementaron los botones estandarizados para operaciones masivas ("Restaurar Seleccionados", "Destruir Seleccionados", "Eliminar Seleccionados") protegidos vía componentes `rxn-confirm-form`.
3.  **Lógica del Repositorio**:
    - Limitación operativa: Los métodos `findByIdWithDetails`, `findPendingIds` y funcionales ahora aplican `activo = 1` obligatoriamente previendo el correcto flujo del sistema.
    - Se crearon los métodos de borrado blando y permanente tanto individuales como masivos (`softDelete`, `restore`, `forceDelete`, `softDeleteBulk`, `restoreBulk`, `forceDeleteBulk`).
    - Se incluyó el filtro extra `$status` en las peticiones de indexado de tablas (`countAll` y `findAllPaginated`).
4.  **Lógica del Controlador**:
    - Se añadieron los endpoints `.POST` (`eliminar`, `restore`, `forceDelete` y parientes masivos) de forma idéntica al estándar.
    - Se parsea el estado de `status` desde GET y se envía hacia Repository y View.
5.  **Rutas**:
    - Se registraron todas las nuevas rutas operativas referentes a reciclar y eliminar envíos de pedidos dentro de `app/config/routes.php`.

## Por qué se hizo
El usuario nos pidió homogeneizar el flujo del grid de "Pedidos Web" en relación a los mismos estándares fijados para el módulo de CRM -> Clientes Webb. Esto unifica cómo operan visual y arquitectónicamente las opciones de eliminar y restaurar listas de elementos.

## Impacto
El módulo de Pedidos Web ahora soporta borrado lógico completo. Operativamente, los usuarios pueden eliminar solicitudes viejas sin perder la integridad histórica del sistema, así como reciclarlas dinámicamente. La estructura es ahora 100% consistente con la visión futura de escalado.

## Decisiones Tomadas
*   El script JS inline `syncBulkState` sobrevivió (aunque purgado) debido a que es un caso especial local donde requería interactuar con el status `disabled` exclusivo del botón "Reenviar a Tango Seleccionados".
*   Las búsquedas sugieren todo (sugerencias globales), pero las operaciones en masa sólo ven elementos Activos por validación y filtrado.
