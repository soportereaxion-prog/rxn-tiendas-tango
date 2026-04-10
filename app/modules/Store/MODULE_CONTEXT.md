# MODULE_CONTEXT — Store

---

## Nivel de criticidad

**ALTO**

Este módulo es la cara pública del negocio B2C. Expone la vitrina de productos, el carrito de compras, el checkout, la autenticación de clientes y la vista de pedidos del comprador. Cualquier error aquí impacta directamente la experiencia del comprador final y el flujo de ventas.

---

## Propósito

Implementar el Store público B2C (tienda online) para cada empresa tenant. El Store se accede por slug de empresa (`/{slug}`) y permite:
- Navegar el catálogo de artículos con filtrado por categoría y búsqueda.
- Ver detalle de productos con galería de imágenes.
- Gestionar un carrito de compras (sesión).
- Realizar checkout con creación de pedido transaccional.
- Autenticarse como cliente web (login, registro, verificación de email, logout).
- Consultar historial de pedidos propios ("Mis Pedidos").

---

## Alcance

### Qué hace
- **Catálogo**: listado paginado de artículos activos con filtrado por categoría (slug) y búsqueda libre. Cache via FileCache (1h).
- **Detalle de producto**: vista individual con galería de imágenes del artículo.
- **Carrito de compras**: gestión en sesión (`$_SESSION['cart']`) por empresa. Agregar, actualizar cantidad, eliminar items. Snapshot de precio al agregar.
- **Checkout**: formulario de datos del cliente → deduplicación/creación de cliente web → creación transaccional de pedido → limpieza de carrito. Opción de registro automático durante checkout.
- **Autenticación**: login, registro con verificación de email, logout. Sesión aislada por empresa via `ClienteWebContext`.
- **Mis Pedidos**: historial de pedidos del cliente logueado con detalle por pedido.
- **Resolución de tienda**: `StoreResolver` valida slug, verifica que la empresa esté activa y tenga módulo tiendas habilitado, e inicializa `PublicStoreContext`.

### Qué NO hace
- No gestiona artículos (consume `ArticuloRepository` en modo lectura).
- No gestiona categorías (consume `CategoriaRepository` en modo lectura).
- No envía pedidos a Tango (los deja en estado `pendiente_envio_tango` para el backoffice).
- No tiene panel de administración de la tienda (la configuración se hace desde `EmpresaConfig`).
- No procesa pagos (no hay pasarela de pago integrada).

---

## Piezas principales

### Controladores

#### `Controllers/StoreController.php` — 171 líneas
- `index()`: catálogo público con filtrado por categoría y búsqueda. Usa FileCache.
- `showProduct()`: detalle de producto con galería.
- `requireValidStore()`: middleware que valida slug via `StoreResolver`.
- `hydrateCategorias()`: rehidrata objetos `Categoria` desde cache serializado.

#### `Controllers/CartController.php` — 86 líneas
- `index()`: vista del carrito.
- `add()`: agregar item al carrito (POST).
- `update()`: actualizar cantidad (POST).
- `remove()`: eliminar item (POST).

#### `Controllers/CheckoutController.php` — 117 líneas
- `index()`: formulario de checkout (precarga datos si cliente logueado).
- `confirm()`: procesa checkout → `CheckoutService::processCheckout()`. Opción de registro automático durante checkout.

#### `Controllers/MisPedidosController.php` — 76 líneas
- `index()`: historial de pedidos del cliente logueado (SQL directo, no usa repo).
- `show()`: detalle de pedido con validación de pertenencia al cliente.
- `requireAuthStore()`: middleware que valida tienda + sesión de cliente.

### Servicios

#### `Services/StoreResolver.php` — 50 líneas
- `resolveEmpresaPublica()`: busca empresa por slug en `empresas` (activa + `modulo_tiendas = 1`), carga config de `EmpresaConfigRepository`, inicializa `PublicStoreContext`.

#### `Services/CartService.php` — 132 líneas
- Gestión de carrito en `$_SESSION['cart'][$empresaId]`.
- `addItem()`: valida artículo (existe, activo, con stock), toma snapshot de precio (`precio_lista_1 ?? precio`).
- `updateItem()` / `removeItem()` / `clearCart()`: mutaciones de carrito.
- `getItems()` / `getTotal()`: lectura.

#### `Services/CheckoutService.php` — 104 líneas
- `processCheckout()`: orquesta deduplicación/creación de cliente → creación de pedido → limpieza de carrito.
- Decisión estratégica: **no envía a Tango sincrónicamente**. Los pedidos quedan pendientes.

### Contextos

#### `Context/PublicStoreContext.php` — 60 líneas
- Singleton estático inicializado por `StoreResolver`.
- Expone: `getEmpresaId()`, `getEmpresaSlug()`, `getEmpresaNombre()`, `getFlags()`.
- Flags de config: `mostrar_stock` (true), `permitir_sin_stock` (false).

#### `Context/ClienteWebContext.php` — 70 líneas
- Gestión de sesión del cliente web B2C.
- `login()` / `logout()` / `isLoggedIn()`: maneja `$_SESSION['store_cliente_id']`, `store_empresa_id`, etc.
- **Aislamiento por empresa**: `isLoggedIn()` verifica que `store_empresa_id` coincida con la empresa actual.
- Totalmente aislada de `$_SESSION['admin_id']` del backoffice.

### Vistas
- `views/layout.php` — Layout base del Store.
- `views/index.php` — Catálogo/vitrina de productos.
- `views/show.php` — Detalle de producto.
- `views/cart.php` — Carrito de compras.
- `views/checkout.php` — Formulario de checkout.
- `views/checkout_success.php` — Confirmación post-checkout.
- `views/error_tienda.php` — Error de tienda no encontrada.
- `views/auth/` — Login, registro, etc.
- `views/mis_pedidos/` — Historial y detalle de pedidos del cliente.

---

## Rutas / Pantallas

Todas las rutas son públicas (accesibles sin login admin). Guard: `StoreResolver::resolveEmpresaPublica()`.

### Catálogo y Producto
| Método | URI | Acción |
|--------|-----|--------|
| GET | `/{slug}` | `StoreController::index` (catálogo) |
| GET | `/{slug}/producto/{id}` | `StoreController::showProduct` |

### Carrito
| Método | URI | Acción |
|--------|-----|--------|
| GET | `/{slug}/carrito` | `CartController::index` |
| POST | `/{slug}/carrito/agregar` | `CartController::add` |
| POST | `/{slug}/carrito/actualizar` | `CartController::update` |
| POST | `/{slug}/carrito/eliminar` | `CartController::remove` |

### Checkout
| Método | URI | Acción |
|--------|-----|--------|
| GET | `/{slug}/checkout` | `CheckoutController::index` |
| POST | `/{slug}/checkout/confirmar` | `CheckoutController::confirm` |

### Autenticación
| Método | URI | Acción |
|--------|-----|--------|
| GET | `/{slug}/login` | `ClienteAuthController::showLoginForm` |
| POST | `/{slug}/login` | `ClienteAuthController::processLogin` |
| GET | `/{slug}/registro` | `ClienteAuthController::showRegisterForm` |
| POST | `/{slug}/registro` | `ClienteAuthController::processRegister` |
| GET | `/{slug}/logout` | `ClienteAuthController::logout` |

### Mis Pedidos (requiere sesión de cliente)
| Método | URI | Acción |
|--------|-----|--------|
| GET | `/{slug}/mis-pedidos` | `MisPedidosController::index` |
| GET | `/{slug}/mis-pedidos/{id}` | `MisPedidosController::show` |

---

## Tablas / Persistencia

| Tabla | Rol | Modo |
|-------|-----|------|
| `empresas` | Resolución de tienda por slug | Lectura |
| `empresa_config` | Config de tienda (depósito, listas de precio) | Lectura |
| `articulos` | Catálogo público, detalle, validación de carrito | Lectura |
| `articulo_imagenes` | Galería de imágenes de producto | Lectura |
| `categorias` | Navegación por categoría en el catálogo | Lectura |
| `articulo_categoria_map` | Filtrado de artículos por categoría | Lectura |
| `clientes_web` | Deduplicación/creación de cliente en checkout | Lectura/Escritura |
| `pedidos_web` | Creación de pedido en checkout | Escritura |
| `pedidos_web_renglones` | Renglones de pedido | Escritura |

---

## Dependencias directas

| Dependencia | Tipo | Motivo |
|-------------|------|--------|
| `App\Core\Database` | Core | Conexión PDO |
| `App\Core\View` | Core | Render de vistas |
| `App\Core\FileCache` | Core | Cache de catálogo y categorías |
| `App\Core\Controller` | Core | Clase base |
| `App\Modules\Articulos\ArticuloRepository` | Articulos | Catálogo público, detalle, validación de carrito |
| `App\Modules\Categorias\CategoriaRepository` | Categorias | Navegación por categoría |
| `App\Modules\ClientesWeb\ClienteWebRepository` | ClientesWeb | Deduplicación/creación de cliente |
| `App\Modules\ClientesWeb\Services\ClienteWebAuthService` | ClientesWeb | Registro automático en checkout |
| `App\Modules\Pedidos\PedidoWebRepository` | Pedidos | Creación de pedido, detalle en "Mis Pedidos" |
| `App\Modules\EmpresaConfig\EmpresaConfigRepository` | EmpresaConfig | Config de tienda |

---

## Dependencias indirectas / Impacto lateral

| Módulo | Cómo impacta |
|--------|-------------|
| `Articulos` | Si cambia `findPublicCatalogPaginated()`, `countPublicCatalog()`, `findById()` o `obtenerImagenesArticulo()`, el Store se rompe |
| `Categorias` | Si cambia `findStoreCategoriesWithCounts()`, la navegación por categoría se rompe |
| `ClientesWeb` | Si cambia `findByDocumentoOrEmail()` o `create()`, el checkout se rompe |
| `Pedidos` | Si cambia `createPedido()`, el checkout se rompe |
| `EmpresaConfig` | Si cambia el contrato de `findByEmpresaId()`, la resolución de tienda falla |

---

## Integraciones involucradas

### FileCache
- **Catálogo**: `catalogo_empresa_{id}_p{page}_s{md5}_c{md5}` — TTL 1h.
- **Conteo**: `catalogo_empresa_{id}_count_s{md5}_c{md5}` — TTL 1h.
- **Categorías**: `categorias_store_empresa_{id}` — TTL 1h.
- La invalidación ocurre desde los módulos `Articulos` y `Categorias` cuando mutan datos.

### MailService (indirecto via ClienteWebAuthService)
- Envío de email de verificación al registrarse.
- Registro automático durante checkout usa `ClienteWebAuthService::register()`.

---

## Seguridad

### Aislamiento multiempresa
- **Implementado**: `StoreResolver` resuelve la empresa por slug y la inyecta en `PublicStoreContext`. Todas las queries subsiguientes usan el `empresaId` del contexto resuelto.
- **Aislamiento de sesión de cliente**: `ClienteWebContext::isLoggedIn()` verifica que `store_empresa_id` coincida con la empresa actual, previniendo acceso cruzado entre tiendas.

### Permisos / Guards
- **Guard público**: `StoreResolver::resolveEmpresaPublica()` valida slug, que la empresa esté activa y tenga `modulo_tiendas = 1`.
- **Guard de cliente**: `ClienteWebContext::isLoggedIn()` para rutas protegidas (Mis Pedidos).
- **No requiere login admin**: todo el Store es accesible públicamente (excepto "Mis Pedidos" que requiere sesión de cliente).

### Admin sistema (RXN) vs Admin tenant
- No aplica. El Store no tiene concepto de administración; es puramente consumidor.

### No mutación por GET
- **Cumplido**: las mutaciones del carrito (agregar, actualizar, eliminar) y el checkout requieren POST.
- **Excepción**: `logout` es GET (`/{slug}/logout`). No es una mutación de datos persistentes pero cierra sesión.

### Validación server-side
- `CartService::addItem()`: valida existencia, estado activo y stock positivo del artículo.
- `CheckoutController::confirm()`: valida nombre y email obligatorios.
- `StoreResolver`: valida slug, empresa activa y módulo tiendas habilitado.
- `ClienteWebContext::isLoggedIn()`: valida scope de empresa.
- `MisPedidosController::show()`: valida que el pedido pertenezca al cliente logueado.

### Escape / XSS
- Las vistas deberían aplicar escape (no verificado en detalle desde este contexto documental).
- `CheckoutController::confirm()` usa `die()` con texto plano para errores, sin escape HTML.

### Impacto sobre acceso local
- **Alto**: todo el Store es público por diseño. Las rutas `/{slug}/*` están expuestas al internet sin autenticación admin.

### CSRF
- **No implementado** en formularios del carrito ni del checkout. Deuda de seguridad activa. Un atacante podría agregar items al carrito o iniciar un checkout manipulando formularios externos.

---

## Reglas operativas del módulo

1. **Resolución por slug obligatoria**: todo controlador del Store debe llamar `requireValidStore()` o `requireAuthStore()` como primera acción.
2. **Carrito en sesión**: los items viven en `$_SESSION['cart'][$empresaId]`. No hay persistencia en DB del carrito.
3. **Snapshot de precio**: al agregar un item, se captura `precio_lista_1 ?? precio`. Este valor se actualiza si el mismo item se vuelve a agregar.
4. **Pedidos quedan pendientes**: el checkout NO envía a Tango. Los pedidos nacen `pendiente_envio_tango` y se operan desde el backoffice.
5. **Cache de 1 hora**: catálogo y categorías usan FileCache con TTL de 3600 segundos. La invalidación es responsabilidad de los módulos `Articulos` y `Categorias`.
6. **Sesión de cliente aislada**: `ClienteWebContext` no interfiere con `$_SESSION['admin_id']` del backoffice.
7. **Registro automático en checkout**: si el comprador no está logueado y envía password en el formulario de checkout, se registra automáticamente via `ClienteWebAuthService`.

---

## Tipo de cambios permitidos (bajo riesgo)

- Modificar textos, labels, estilos en vistas del Store.
- Ajustar el límite de items por página en el catálogo (actualmente 24).
- Agregar campos informativos a la vista de detalle de producto.
- Modificar el layout visual del Store.
- Ajustar TTL del FileCache.

---

## Tipo de cambios sensibles (requieren análisis previo)

- **Modificar `StoreResolver`**: rompe la resolución de todas las tiendas públicas.
- **Cambiar `CartService::addItem()`**: impacta el snapshot de precios y la validación de stock.
- **Cambiar `CheckoutService::processCheckout()`**: impacta la creación de pedidos y la deduplicación de clientes.
- **Cambiar `PublicStoreContext`**: impacta todas las vistas y controladores del Store.
- **Cambiar `ClienteWebContext`**: impacta autenticación de cliente y "Mis Pedidos".
- **Modificar las claves de cache**: puede causar cache stale o invalidación incorrecta.

---

## Puntos críticos del código

| Zona | Archivo | Riesgo |
|------|---------|--------|
| `StoreResolver::resolveEmpresaPublica()` | StoreResolver.php | Si falla o retorna datos incorrectos, todo el Store se rompe |
| `CartService::addItem()` con snapshot | CartService.php | El precio se actualiza en cada `addItem` del mismo artículo; si el precio cambió en DB, el snapshot cambia silenciosamente |
| `CheckoutController::confirm()` con `die()` | CheckoutController.php | Error de validación mata el proceso con texto plano, sin layout ni escape |
| `MisPedidosController::index()` SQL directo | MisPedidosController.php | Bypasea el repositorio; si cambia el schema de `pedidos_web`, falla silenciosamente |
| `hydrateCategorias()` en StoreController | StoreController.php | Rehidrata manualmente desde cache; si el modelo Categoria cambia, la rehidratación puede fallar |

---

## No romper

1. **`StoreResolver::resolveEmpresaPublica()`**: gate de entrada para todo el Store.
2. **`CartService::addItem()` / `getItems()` / `getTotal()`**: base del flujo de compra.
3. **`CheckoutService::processCheckout()`**: orquesta la creación del pedido.
4. **`PublicStoreContext::getEmpresaId()`**: usado por todos los controladores y servicios del Store.
5. **`ClienteWebContext::isLoggedIn()` / `getClienteId()`**: gate de "Mis Pedidos" y precarga de datos en checkout.
6. **Cache keys**: `catalogo_empresa_{id}*` y `categorias_store_empresa_{id}` son las claves invalidadas desde otros módulos.

---

## Riesgos conocidos

1. **Sin CSRF en carrito y checkout**: un atacante puede manipular el carrito o iniciar un checkout via formulario externo.
2. **Carrito solo en sesión**: si la sesión se pierde (expiración, limpieza), el carrito se pierde sin aviso.
3. **`die()` en checkout**: errores de validación muestran texto plano sin layout.
4. **SQL directo en MisPedidosController**: no usa repositorio, riesgo de divergencia.
5. **Logout por GET**: `/{slug}/logout` es GET, vulnerable a CSRF por imagen/link.
6. **Precio snapshot mutable**: si el mismo artículo se agrega dos veces y el precio cambió entre medio, el snapshot se actualiza al último valor.
7. **Sin validación de stock en checkout**: el carrito valida stock al agregar, pero no revalida al confirmar checkout. Si el stock se agotó entre tanto, se crea el pedido igualmente.
8. **Login redirect potencialmente inseguro**: `filter_var($next, FILTER_SANITIZE_URL)` puede ser insuficiente para prevenir open redirect.

---

## Checklist post-cambio

- [ ] El catálogo público carga correctamente (`/{slug}`)
- [ ] El filtrado por categoría funciona
- [ ] La búsqueda de productos funciona
- [ ] El detalle de producto muestra datos y galería
- [ ] Agregar al carrito funciona (valida stock y precio)
- [ ] El carrito muestra items y total correctamente
- [ ] El checkout procesa y crea el pedido
- [ ] El registro automático durante checkout funciona
- [ ] Login y registro de cliente funcionan
- [ ] "Mis Pedidos" muestra historial y detalle
- [ ] El Store de una empresa no muestra datos de otra empresa
- [ ] El cache se invalida correctamente al mutar artículos o categorías

---

## Documentación relacionada

- `app/modules/Articulos/MODULE_CONTEXT.md` — Fuente de datos del catálogo
- `app/modules/Categorias/MODULE_CONTEXT.md` — Fuente de datos de categorías
- `app/modules/ClientesWeb/MODULE_CONTEXT.md` — Gestión de clientes y autenticación
- `app/modules/Pedidos/MODULE_CONTEXT.md` — Repositorio de pedidos

---

## Regla de mantenimiento

Este archivo debe actualizarse si cambian:
- Las rutas públicas del Store
- La lógica de resolución de tienda (`StoreResolver`)
- El flujo de carrito o checkout
- La autenticación de clientes web
- La estructura del cache (claves, TTL, invalidación)
- Los módulos consumidos (Articulos, Categorias, ClientesWeb, Pedidos)
