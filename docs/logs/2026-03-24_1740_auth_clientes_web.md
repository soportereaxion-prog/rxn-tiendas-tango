# Clientes Web y Autenticación B2C

## Contexto
El negocio requería implementar una capa de autenticación para que clientes finales de la Tienda Pública pudieran tener un historial, registrarse y hacer checkouts más rápidos. Todo manteniendo estricta separación de los administradores y sin frameworks de terceros. 

## Problema
Los checkouts invitados no permitían seguimiento posterior del pedido ni gestión de datos desde el lado cliente, además se requería prevenir el cruce de sesiones entre múltiples empresas que co-existen en el sistema (multi-tenant).

## Decisión
- Agregar columna `password_hash` nullable a `clientes_web`.
- Introducir `ClienteWebAuthService` aislando la verificación/hasheo.
- Añadir `ClienteWebContext` administrando session scopes `store_cliente_id` y `store_empresa_id` con inmunidad sobre `admin_id`.
- Soportar registro de invitado "on-the-fly" activando un checkbox nativo en la página Checkout.
- Añadir paneles de "Mis Pedidos" públicos leyendo de `pedidos_web` e iterando con el ID autenticado.

## Archivos afectados
- `migrate_clientes.php` (DB Alteration: password_hash, Index uq_empresa_email)
- `App/config/routes.php` (Mapeo URI Store B2C)
- `App/Modules/Store/Context/ClienteWebContext.php` (Nuevo)
- `App/Modules/ClientesWeb/Services/ClienteWebAuthService.php` (Nuevo)
- `App/Modules/ClientesWeb/Controllers/ClienteAuthController.php` (Nuevo)
- `App/Modules/Store/Controllers/MisPedidosController.php` (Nuevo)
- `App/Modules/Store/Controllers/CheckoutController.php` (Precarga + Auto-Registro)
- `App/Modules/Store/views/layout.php` (Header dropdown stateful)
- `App/Modules/Store/views/auth/login.php` (Nueva)
- `App/Modules/Store/views/auth/registro.php` (Nueva)
- `App/Modules/Store/views/mis_pedidos/index.php` (Nueva)
- `App/Modules/Store/views/mis_pedidos/show.php` (Nueva)

## Implementación
1. DB actualizada bajo un bootstrapper directo.
2. Inyección de Session Helpers estáticos.
3. Ruteo de endpoints Vanilla PHP Controller/View.
4. Adaptación de autocompletado del array de Input values en Checkout front.
5. Inyección dropdown de estado en Navigation Header interactivo puro PHP/HTML.

## Impacto
Positivo. El flujo pre-existente de compras "Guest" (usuarios que no quieren registrarse) no fue interrumpido. Aquellos `clientes_web` generados anónimamente que decidan registrarse posteriormente con el mismo email heredan sus comprobantes huérfanos transparentemente.

## Riesgos
- Re-regeneraciones globales de Cookie que colisionaran `admin_id` y `store_cliente_id` (mitigado usando Session Keys explícitamente únicas).
- Multi-empresa Session Override (mitigado forzando un assert `(int)$_SESSION['store_empresa_id'] === $empresaIdActual` en todas las lecturas logueadas).

## Validación
Verificado pre-loading visual in-code y validación de Nullability.

## Notas
Las vistas se han enriquecido con CSS clases de Bootstrap para mantener simetría UI con la Tienda sin importar paquetes pesados de js.
