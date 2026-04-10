# MODULE_CONTEXT — Pedidos

---

## Nivel de criticidad

**ALTO**

Este módulo es el punto de convergencia entre el checkout del Store, los clientes web y el ERP Tango. Gestiona el ciclo de vida completo de pedidos web: desde su creación transaccional hasta su envío (o reintento de envío) a Tango Connect. Un error aquí puede causar pérdida de ventas, pedidos duplicados en Tango o pedidos huérfanos sin posibilidad de reenvío.

---

## Propósito

Gestionar pedidos web generados desde el Store B2C. Incluye:
- ABM y visualización de pedidos desde el backoffice.
- Envío individual, selectivo y masivo de pedidos a Tango Connect.
- Reproceso de pedidos fallidos o pendientes.
- Detalle de pedido con renglones, datos de cliente e información de respuesta Tango.

> **Nota**: La creación de pedidos ocurre en `CheckoutService` (módulo Store). Este módulo se encarga del repositorio transaccional y de la gestión backoffice.

---

## Alcance

### Qué hace
- Listado paginado de pedidos con búsqueda, filtros avanzados (CRUD filter embudo) y filtro por estado Tango.
- Sugerencias de autocompletado JSON (`/sugerencias`) para buscadores.
- Vista detalle de pedido con renglones, datos del cliente y respuesta/payload Tango.
- Envío individual a Tango (`reprocesar`): construye payload, consulta artículos, valida ID_STA11, envía orden.
- Envío selectivo (`reprocesarSeleccionados`): reenvía un lote de pedidos seleccionados.
- Envío masivo de pendientes (`reprocesarPendientes`): reenvía todos los pedidos en estado `pendiente_envio_tango`.
- Soft delete (via flag `activo=0`), restauración y borrado definitivo (individual y masivo).
- Creación transaccional de pedidos (`createPedido`): cabecera + renglones en una transacción.
- Registro de resultado Tango: `markAsSentToTango` / `markAsErrorToTango` con payload y respuesta.
- Ofuscación de respuesta Tango para usuarios no-admin (extrae solo mensajes legibles).

### Qué NO hace
- No gestiona el carrito de compras (eso es `CartService`).
- No crea clientes (eso es `ClienteWebRepository` / `CheckoutService`).
- No sincroniza artículos (eso es `RxnSync` / `TangoSync`).
- No expone rutas públicas al Store; la vista pública de pedidos está en `MisPedidosController` (módulo Store).

---

## Piezas principales

### Controladores

#### Legacy (vacíos)
- `PedidosController.php` — 0 líneas — Stub vacío.
- `PedidosModel.php` — 0 líneas — Stub vacío.

#### Activo
- `Controllers/PedidoWebController.php` — 402 líneas
  - `index()`: listado paginado con filtros avanzados y filtro por estado.
  - `suggestions()`: endpoint JSON para autocompletado.
  - `show()`: detalle de pedido con renglones, cliente y respuesta Tango (ofuscada para no-admin).
  - `reprocesar()`: envío individual a Tango.
  - `reprocesarSeleccionados()`: envío selectivo (POST con lista de IDs).
  - `reprocesarPendientes()`: envío masivo de todos los pendientes.
  - `eliminar()` / `restore()` / `forceDelete()`: individual.
  - `eliminarMasivo()` / `restoreMasivo()` / `forceDeleteMasivo()`: masivo.
  - `sendPedidoToTango()`: pipeline interno: valida cliente Tango, construye payload con mapper, envía via `TangoOrderClient`, registra resultado.

### Repositorio

#### `PedidoWebRepository.php` — 368 líneas
- `createPedido()`: creación transaccional (cabecera `pedidos_web` + renglones `pedidos_web_renglones`).
- `markAsSentToTango()`: actualiza estado a `enviado_tango` con payload y respuesta.
- `markAsErrorToTango()`: actualiza estado a `error_envio_tango` con error y respuesta.
- `findAllPaginated()` / `countAll()`: listado paginado con JOINs a `clientes_web`.
- `findSuggestions()`: búsqueda para autocompletado.
- `findByIdWithDetails()`: detalle de pedido con renglones.
- `findPendingIds()`: IDs de pedidos en estado `pendiente_envio_tango`.
- `findIdsByEmpresaAndList()`: valida IDs contra empresa antes de operar masivamente.
- `softDelete()` / `restore()` / `forceDelete()`: individual.
- `softDeleteBulk()` / `restoreBulk()` / `forceDeleteBulk()`: masivo.

### Vistas
- `views/index.php` — Listado de pedidos con tabla, filtros, paginación, acciones masivas, botones de reenvío.
- `views/show.php` — Detalle de pedido con renglones, datos del cliente, estado Tango, payload/respuesta (admin) o mensaje limpio (tenant).

---

## Rutas / Pantallas

Guard: `AuthService::requireLogin()` + `Context::getEmpresaId()`.

| Método | URI | Acción |
|--------|-----|--------|
| GET | `/mi-empresa/pedidos` | `index` |
| GET | `/mi-empresa/pedidos/sugerencias` | `suggestions` (JSON) |
| GET | `/mi-empresa/pedidos/{id}` | `show` |
| POST | `/mi-empresa/pedidos/{id}/reprocesar` | `reprocesar` |
| POST | `/mi-empresa/pedidos/reprocesar-seleccionados` | `reprocesarSeleccionados` |
| POST | `/mi-empresa/pedidos/reprocesar-pendientes` | `reprocesarPendientes` |
| POST | `/mi-empresa/pedidos/{id}/eliminar` | `eliminar` |
| POST | `/mi-empresa/pedidos/{id}/restore` | `restore` |
| POST | `/mi-empresa/pedidos/{id}/force-delete` | `forceDelete` |
| POST | `/mi-empresa/pedidos/eliminar-masivo` | `eliminarMasivo` |
| POST | `/mi-empresa/pedidos/restore-masivo` | `restoreMasivo` |
| POST | `/mi-empresa/pedidos/force-delete-masivo` | `forceDeleteMasivo` |

---

## Tablas / Persistencia

| Tabla | Rol |
|-------|-----|
| `pedidos_web` | Cabecera de pedidos (empresa_id, cliente_web_id, total, estado_tango, payload, respuesta, intentos) |
| `pedidos_web_renglones` | Renglones de pedido (articulo_id, cantidad, precio_unitario, nombre_articulo) |
| `clientes_web` | JOIN para datos de cliente en listado y detalle |
| `articulos` | Consulta de `codigo_externo` para mapeo de renglones a Tango |
| `empresa_config` | Credenciales Tango Connect |
| `usuarios` | `tango_perfil_pedido_id` del usuario activo para el mapper |

---

## Dependencias directas

| Dependencia | Tipo | Motivo |
|-------------|------|--------|
| `App\Core\Context::getEmpresaId()` | Core | Aislamiento multiempresa |
| `App\Core\Controller` | Core | Clase base |
| `App\Core\View` | Core | Render de vistas |
| `App\Core\Flash` | Core | Mensajes flash |
| `App\Core\Database` | Core | Conexión PDO para queries auxiliares |
| `App\Core\AdvancedQueryFilter` | Core | Filtros avanzados CRUD |
| `App\Modules\Auth\AuthService` | Auth | `requireLogin()` |
| `App\Modules\Tango\TangoOrderClient` | Tango | Envío de orden a Tango Connect |
| `App\Modules\Tango\Mappers\TangoOrderMapper` | Tango | Mapeo de pedido local → payload Tango |

---

## Dependencias indirectas / Impacto lateral

| Módulo | Cómo consume |
|--------|-------------|
| `Store\Services\CheckoutService` | Instancia `PedidoWebRepository` para crear pedidos en checkout |
| `Store\Controllers\MisPedidosController` | Instancia `PedidoWebRepository` para detalle de pedido del cliente web |
| `ClientesWeb\Controllers\ClienteWebController` | Redirige a `reprocesar` via `enviarPendientes()` |

---

## Integraciones involucradas

### Tango Connect (via TangoOrderClient + TangoOrderMapper)
- **Flujo**: `sendPedidoToTango()` → obtiene credenciales de `empresa_config` → instancia `TangoOrderClient` → resuelve `codigo_externo` de cada artículo → valida `getArticleIdByCode()` (ID_STA11) → mapea con `TangoOrderMapper::map()` → envía con `sendOrder()`.
- **Estados**: `pendiente_envio_tango` → `enviado_tango` | `error_envio_tango`.
- **Reintentos**: no hay límite de reintentos; el campo `intentos_envio_tango` se incrementa en cada intento.
- **Perfil de pedido**: se consulta `tango_perfil_pedido_id` del usuario activo para incluir en el mapper.

---

## Seguridad

### Aislamiento multiempresa
- **Implementado**: toda query usa `empresa_id` como filtro. `findIdsByEmpresaAndList()` valida que los IDs pertenezcan a la empresa antes de operar masivamente.

### Permisos / Guards
- **Guard activo**: `AuthService::requireLogin()` + `Context::getEmpresaId()`.
- **Diferenciación admin/tenant**: `show()` consulta `$_SESSION['es_rxn_admin']` para decidir si mostrar payload/respuesta raw o mensaje ofuscado.
- **No hay guard granular por rol**: cualquier usuario logueado puede ver, reprocesar y eliminar pedidos.

### Admin sistema (RXN) vs Admin tenant
- **Implementado parcialmente**: en `show()`, si `es_rxn_admin == 1` se muestra `respuesta_tango` cruda; si no, se extrae un mensaje limpio.

### No mutación por GET
- **Cumplido**: todas las mutaciones requieren POST.
- **GET es solo lectura**: `index`, `suggestions`, `show`.

### Validación server-side
- `reprocesar` valida existencia del pedido y presencia de `id_gva14_tango` en el cliente.
- `reprocesarSeleccionados` valida IDs contra empresa con `findIdsByEmpresaAndList()`.
- Creación transaccional con rollback automático en caso de error.

### Escape / XSS
- La ofuscación de respuesta Tango en `show()` previene exposición de datos internos del ERP a operarios.

### Impacto sobre acceso local
- Sin impacto. El módulo no expone rutas públicas.

### CSRF
- **No implementado** en formularios ni acciones POST. Deuda de seguridad activa.

---

## Reglas operativas del módulo

1. **Aislamiento multiempresa obligatorio**: toda query usa `empresa_id`.
2. **Soft delete via flag `activo`**: no usa `deleted_at` sino `activo = 0/1`.
3. **Creación transaccional**: `createPedido()` usa `beginTransaction()` / `commit()` / `rollBack()`.
4. **Pedidos quedan pendientes por diseño**: la sincronización con Tango fue desacoplada del checkout. Los pedidos nacen en estado `pendiente_envio_tango` y se envían desde el backoffice.
5. **Precio snapshot**: los renglones guardan `precio_unitario` y `nombre_articulo` al momento de la creación (snapshot inmutable).
6. **Payload y respuesta persistidos**: todo envío a Tango registra el payload enviado y la respuesta recibida para auditoría.
7. **Sin límite de reintentos**: `intentos_envio_tango` se incrementa pero no bloquea reenvíos.

---

## Tipo de cambios permitidos (bajo riesgo)

- Modificar labels y mensajes en vistas.
- Ajustar límites de paginación.
- Agregar columnas de visualización al listado que no cambien la firma de `findAllPaginated`.
- Agregar campos informativos a la vista de detalle.

---

## Tipo de cambios sensibles (requieren análisis previo)

- **Modificar el esquema de `pedidos_web` o `pedidos_web_renglones`**: impacta checkout, Store (mis pedidos), y todo el flujo de envío a Tango.
- **Cambiar `createPedido()`**: rompe la creación de pedidos desde el checkout.
- **Cambiar `sendPedidoToTango()`**: rompe el envío al ERP.
- **Cambiar `findByIdWithDetails()`**: rompe la vista de detalle en backoffice y en Store (mis pedidos).
- **Cambiar la firma JSON de `/sugerencias`**: rompe autocompletado.
- **Modificar `TangoOrderMapper`**: impacta el formato del payload enviado a Tango.

---

## Puntos críticos del código

| Zona | Archivo | Riesgo |
|------|---------|--------|
| `sendPedidoToTango()` | PedidoWebController.php | Pipeline complejo: cualquier fallo en credentials, artículo o mapper produce `error_envio_tango` |
| `createPedido()` con transacción | PedidoWebRepository.php | Rollback si falla; pero si la app muere entre commit y respuesta al cliente, pedido se crea sin confirmación visual |
| `getArticleIdByCode()` | PedidoWebController.php | Si el artículo local no tiene `codigo_externo` o no existe en Tango, falla con excepción |
| `findAllPaginated()` con LIMIT/OFFSET inline | PedidoWebRepository.php | Concatena `LIMIT` y `OFFSET` casteados a int; seguro pero no usa bindValue |

---

## No romper

1. **`createPedido()`**: consumida por `CheckoutService` para crear pedidos transaccionalmente.
2. **`findByIdWithDetails()`**: consumida por `MisPedidosController` y `PedidoWebController::show()`.
3. **`findPendingIds()`**: usada por `reprocesarPendientes()` y potencialmente por procesos batch futuros.
4. **Estados Tango**: `pendiente_envio_tango`, `enviado_tango`, `error_envio_tango` son consultados en filtros, badges y lógica de reenvío.
5. **Snapshot de precios**: los renglones deben mantener `precio_unitario` inmutable post-creación.
6. **Firma JSON de `/sugerencias`**: `{success, data: [{id, label, value, caption}]}`.

---

## Riesgos conocidos

1. **Sin CSRF en formularios**: deuda de seguridad activa.
2. **Sin límite de reintentos**: un pedido puede reenviarse infinitamente sin throttle.
3. **Payload Tango visible para RXN admin**: contiene credenciales implícitas (token en headers, no en payload body, pero la respuesta puede contener datos sensibles del ERP).
4. **`MisPedidosController` usa SQL directo**: consulta `pedidos_web` sin usar el repositorio, bypasseando cualquier lógica de negocio del repo.
5. **Stubs legacy**: `PedidosController.php` y `PedidosModel.php` son archivos vacíos que conviven con el controlador activo en `Controllers/`.

---

## Checklist post-cambio

- [ ] El listado de pedidos carga correctamente (`/mi-empresa/pedidos`)
- [ ] El detalle de pedido muestra renglones y datos del cliente
- [ ] Un pedido pendiente se puede reprocesar exitosamente
- [ ] El reproceso masivo de pendientes funciona
- [ ] El checkout del Store crea pedidos correctamente
- [ ] "Mis Pedidos" del Store muestra los pedidos del cliente
- [ ] Soft delete, restore y force-delete funcionan
- [ ] El payload y la respuesta Tango se persisten correctamente

---

## Documentación relacionada

- `app/modules/Store/MODULE_CONTEXT.md` — Checkout que crea pedidos
- `app/modules/ClientesWeb/MODULE_CONTEXT.md` — Clientes vinculados a pedidos

---

## Regla de mantenimiento

Este archivo debe actualizarse si cambian:
- El esquema de `pedidos_web` o `pedidos_web_renglones`
- Las rutas del módulo
- El flujo de envío a Tango (mapper, client, pipeline)
- Los estados de pedido
- Los consumidores que leen `pedidos_web` directamente
