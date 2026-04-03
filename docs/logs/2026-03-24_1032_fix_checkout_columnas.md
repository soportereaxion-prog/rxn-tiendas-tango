# rxn_suite — FIX CONFIRMACIÓN DE PEDIDO

## Fecha
2026-03-24 10:32

## 1. Objetivo
Corregir el error de persistencia disparado al finalizar la compra en el Checkout ("Column not found: 1054 Unknown column 'codigo_articulo' in 'field list'"). Asegurar que los nombres reales de la BD se usen en las capas de servicio al momento de estructurar el renglón.

## 2. Causa Raíz
El error, a pesar de manifestarse como una falla de persistencia al grabar el pedido, no se encontraba en la tabla `pedidos_web_renglones`, sino en el servicio `CheckoutService` pre-persistencia. 
Previo a guardar, el CheckoutService requiere el código (SKU) del artículo para adjuntarlo al renglón y luego dárselo a Tango. La consulta en `CheckoutService` hacía un `SELECT codigo_articulo FROM articulos` pero, tras auditar `ArticuloRepository`, el esquema y modelo dictan que el nombre real de la columna es `codigo_externo`.

## 3. Columnas reales vs Código
- **En Código (`CheckoutService`)**: Se interpelaba a la BD pidiendo `codigo_articulo`.
- **En DB (`articulos`)**: La columna se llama verdaderamente `codigo_externo`.
- **Estructura Pedidos Web Renglones**: Fue auditada y está correctamente definida sin depender directamente del esquema de artículos.

## 4. Archivos Tocados
**`app/modules/Store/Services/CheckoutService.php`**
Se corrigió la línea 73: `$stmtArt = $pdo->prepare("SELECT codigo_externo FROM articulos WHERE id = :id");`

## 5. Pruebas Realizadas
- Se auditó transversalmente la capa de repositorios de Articulos.
- Se verificó que la clase `CheckoutService` reciba adecuadamente el DTO mapeando "codigo_externo" que el repo devolverá.

## 6. Riesgos
- Al solucionar esta consulta, la persistencia de renglones está subsanada, y se envían correctamente los datos a `TangoOrderMapper` bajo la propiedad `$codArtObj`. Las pruebas subsecuentes certificarán el mapping.

## 7. Próximos pasos
- El commit debe ser encapsulado ahora bajo este único cambio vital y seguro. 
- Hacer seguimiento del registro en la base de datos `pedidos_web_renglones`.
