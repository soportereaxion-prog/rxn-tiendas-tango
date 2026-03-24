# Iteración Corta: Edición Local de Stock de Artículos

## Fecha y Tema
2026-03-24 04:15 - Implementación del campo `stock_actual` dentro del panel de edición administrativa de Artículos.

## 1. Objetivo
Completar la experiencia de interfaz operativa ofreciendo la visibilidad o edición del Stock Actual dentro de `/mi-empresa/articulos/editar`, determinando previamente la pertinencia de mutar el dato.

## 2. Auditoría del Contrato
Se auditó la fuente de verdad del `stock_actual`:
- La persistencia del atributo en la base SQLite/MySQL es gobernada exclusivamente por `TangoSyncService::syncStock()`, apuntando al Endpoint del Proceso 17668 de Tango Connect.
- El repositorio efectúa un simple `updateStock()` ciego cuando baja el payload de Connect. 
- Por lo tanto, cualquier guardado manual que ejecutáramos desde `ArticuloController::actualizar()` generaría una **"falsa edición"**. El operador vería el número cambiar en su UI momentáneamente, solo para ser destruido silenciosamente en la próxima ráfaga de cron de Sincronización, induciendo al caos en las ventas del Carrito Público.

## 3. Decisión Funcional
Regla de negocio inferida: **El Stock pertenece estructuralmente a la macro del ERP.**
Se seleccionó la **OPCIÓN B — STOCK DERIVADO / SINCRONIZADO**.
- Se optó por un campo tipo informativo (`readonly disabled`).
- Se descartó inyectar lógicas de update en el `ArticuloController` para este parámetro.
- Se preserva la consistencia de los remitos bajados desde el túnel hacia la base local.

## 4. Archivos Tocados
* [MOD] `app/modules/Articulos/views/form.php` (Agregado del componente visual UI readonly block con disclaimers operativos).

## 5. Pruebas Realizadas
* Se visualiza la inyección del campo flotando entre Precios Alternos de Lista y el Toggle de Activo, preservando la simetría del Layout de Edición.
* El valor visualizado coincide en un 100% con la hidratación base desde MySQL. Los ceros absolutos o vacíos degradan amigablemente a "0".
* Un envío del Custom Form actualizando Precio Manual o Descripción respeta la no remisión del `stock_actual`, aislando y protegiendo la columna SQL de pisotones transaccionales. 

## 6. Riesgos Evitados
- Inducción a falsa promesa operativa.
- Polución del Controlador de update con lógicas muertas.

## 7. Próximo Paso
Dado que toda la cadena técnica de artículos maduró (sincronía, visualización pública, catálogo, cache file-based y readonly de administración), podemos pasar agresivamente a la transaccionabilidad del sistema (Checkout base y Pedidos).
