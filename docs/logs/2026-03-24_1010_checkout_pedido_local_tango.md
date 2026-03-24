# rxnTiendasIA — Checkout, Pedido Local y Envío a Tango

## Fecha
2026-03-24 10:10

## 1. Objetivo
Implementar la primera versión seria de checkout para concluir el circuito operativo de la tienda pública: confirmar la compra, generar o reutilizar un Cliente Web Local, persistir un Pedido Local con sus renglones y enviar dicho pedido a Tango Connect usando el process `19845`.

## 2. Auditoría Inicial
Se analizaron `routes.php`, `CartController.php` y `CartService.php`. El carrito persistía correctamente en sesión pero no tenía mecanismo de salida. El módulo de integraciones con Tango ya contaba con `TangoService` y `TangoApiClient` orientados a sincronizar artículos (GET), por lo que fue necesario desarrollar una nueva capa para POSTs.

## 3. Decisión sobre Cliente Web Local
Se decidió no forzar el alta obligatoria de clientes en Tango ni exigir login. Se crearon tablas `clientes_web` a nivel local para capturar y asegurar los datos de contacto y de envío.  
*Regla Comercial Clave*: Si el cliente ya poseía una vinculación manual, usa su `codigo_tango`. Si es un nuevo cliente de la tienda, `codigo_tango` se inserta NULL, derivando en `000000` (Cliente Ocasional) al mapear el payload para Tango.

## 4. Decisión sobre Reutilización de Cliente
Para evitar duplicaciones innecesarias, la creación de Cliente Web verifica primero dentro del ámbito `empresa_id` coincidencia por documento o email. De existir, se asocia el nuevo pedido a ese ID local, actualizando (update) campos como teléfono o dirección a los provistos en el nuevo checkout.

## 5. Diseño del Pedido Web Local
El pedido local es el resguardo inquebrantable de la voluntad comercial, aislando la solución en caso de caída de Tango.
- `pedidos_web`: cabecera, montos, link a `cliente_web_id`.
- Campos de Integración Tango Mantenidos: `estado_tango` (`pendiente_envio_tango`, `enviado_tango`, `error_envio_tango`), `payload_enviado` y `respuesta_tango` para trazabilidad.
- `pedidos_web_renglones`: detalle snapshot por artículo en el momento de la confirmación.

## 6. Investigación del process 19845
Se determinó que el process 19845 de la API /Api/Create de Tango Connect consiste en peticiones POST con un esquema base equivalente a Comprobantes de Venta de Tango REST. La directiva exigió no enviar payloads inválidos; por tanto, se mapearán con estructura típica (Client Code, Date, Note numeration, y Renglones con Code, Quantity, UnitPrice).

## 7. Payload, Respuesta y Pruebas
Se implementó `TangoOrderMapper::map` para traducir desde modelo local relacional al formato de GVA21. El resultado se persiste como JSON en la tabla `pedidos_web` para ser debuggeado o reprocesado. Para pruebas iniciales manuales quedó disponible en la raíz `test_tango_create.php` para inferir validaciones de Axoft.

## 8. Archivos Tocados / Creados
**DB & Schemas**
- Creación de tabla `clientes_web`, `pedidos_web` y `pedidos_web_renglones` (migración en `/database_migrations_checkout.php`).

**Módulos / Dominio**
- `[NEW] app/modules/ClientesWeb/ClienteWebRepository.php`
- `[NEW] app/modules/Pedidos/PedidoWebRepository.php`
- `[NEW] app/modules/Tango/TangoOrderClient.php`
- `[NEW] app/modules/Tango/Mappers/TangoOrderMapper.php`
- `[NEW] app/modules/Store/Controllers/CheckoutController.php`
- `[NEW] app/modules/Store/Services/CheckoutService.php`

**Presentación y Ruteo**
- `[NEW] app/modules/Store/views/checkout.php`
- `[NEW] app/modules/Store/views/checkout_success.php`
- `[MODIFY] app/modules/Store/views/cart.php` (Cambio dummy link por anchor real)
- `[MODIFY] app/config/routes.php` (Rutas agregadas)

## 9. Riesgos Controlados
- **Pérdida de Orden**: Se transacciona primero contra la DB local, obteniendo un Pedido ID (`RXN_{id}`) que se mapea como NOTA_PEDIDO_WEB en Tango. Si el POST falla, el local queda como `error_envio_tango` intacto.
- **Pérdida de Info en Ocasional**: La regla `000000` está vigente, pero los campos nombre, apellido y documento van a las Observaciones del GVA21. Adicionalmente, todo radica en el modelo relacional `clientes_web`.

## 10. Próximos pasos sugeridos
1. Desarrollar una grilla / panel administrativo en el BackOffice para poder re-enviar manualmente los pedidos que quedaron en `error_envio_tango`.
2. Habilitar la pasarela de pagos, interrumpiendo el flujo tras el pedido local y gatillando el call a Tango en un webhook o controlador success.
3. Evaluar e investigar la especificación detallada del payload del process `19845` con un caso exitoso en producción (Axoft).
