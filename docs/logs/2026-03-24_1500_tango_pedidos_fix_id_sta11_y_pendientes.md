# Pedidos Web — Corrección envío Tango (19845) y botón reintento

## Contexto
El envío de notas de pedido al ERP Tango fallaba devolviendo sistemáticamente un error "HTTP 500". Analizando el cuerpo del payload y haciendo peticiones experimentales para mockear la conexión de forma directa evadiendo las clases internas, se pudo auditar e identificar tres problemas de redacción sumamente bloqueantes.

## Problema
1. El mapeador de órdenes web `TangoOrderMapper` armaba el json bajo los headers `RENGLONES` e `ITEMS`, donde el ERP requería la etiqueta comercial estricta `RENGLON_DTO`.
2. Las líneas detalladas del pedido enlazaban la ID interna usando `COD_ARTICULO` pero Tango requería estrictamente proveer de su llave `ID_STA11` en pedidos, la cual no existía en nuestra base de datos ni caché.
3. El enlazador API `ApiClient` concatenaba dos veces seguidas la ruta `/Api` al dominio en su inicialización (Resultando en `.../Api/Api/Create...`), provocando que IIS devolviera un error HTTP 500 debido a rutas inaccesibles.

## Decisión
Se decidió emular el comportamiento del sistema heredado `modelo.php` insertando una consulta a la API de Tango a través de su proceso `87` que resuelve el string de un SKU transformándolo en su ID interno en tiempo de ejecución (`ID_STA11`) para poder adjuntarlo al renglón iterativo. 

Se activó en interfaz funcional la llamada masiva de pedidos por cliente usando el mismo servicio rediseñado de envíos.

## Archivos afectados
- `app/modules/Pedidos/Controllers/PedidoWebController.php` (Lógica de intercepción de productos ID)
- `app/modules/ClientesWeb/views/edit.php` (Botones UI POST)
- `app/modules/ClientesWeb/Controllers/ClienteWebController.php` (Nuevo Endpoint Controlador Masivo)
- `app/config/routes.php` (Enlazador URL)
- `app/modules/Tango/Mappers/TangoOrderMapper.php` (Nuevas nomenclaturas impuestas)
- `app/modules/Tango/TangoOrderClient.php` (Servicio de búsqueda ID y parcheo url)

## Implementación
Mapeador arreglado. Buscador ID habilitado. Endpoint ruteado. 

## Riesgos
La demora por resolución de ID es lineal O(N) lo que podría demorar carritos de compras demasiado exorbitados de items. La solución ideal a futuro es incorporar el arrastre de `ID_STA11` en el script inicial de sincronización de listados.

## Notas
Ninguna.
