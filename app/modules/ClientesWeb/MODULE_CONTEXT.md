# MODULE_CONTEXT — ClientesWeb

---

## Nivel de criticidad

**ALTO**

Este módulo gestiona los clientes que compran en el Store B2C y los clientes vinculados comercialmente desde el backoffice. Es la fuente de datos de clientes para los módulos Pedidos, Store (checkout) y la integración con Tango Connect. Un cambio en la tabla `clientes_web` o en la lógica de vinculación Tango puede romper el flujo de checkout y el envío de pedidos al ERP.

---

## Propósito

Gestionar clientes web (B2C) y clientes CRM en dos contextos operativos:

- **Tiendas**: clientes que se registran o se crean automáticamente en el checkout del Store público. ABM desde backoffice con vinculación comercial a Tango.
- **CRM**: clientes CRM con su propia tabla (`crm_clientes`), bootstrapeada automáticamente. ABM desde backoffice CRM con misma vinculación Tango.

Cada área opera sobre su propia tabla (`clientes_web` vs `crm_clientes`) usando el patrón Strategy Table, compartiendo el mismo controlador, repositorio y vistas.

---

## Alcance

### Qué hace
- Listado paginado con búsqueda, ordenamiento y filtros avanzados (CRUD filter embudo).
- Edición manual de campos: nombre, apellido, email, teléfono, documento, razón social, dirección, código postal, provincia, localidad, código Tango.
- Sugerencias de autocompletado JSON (`/sugerencias`) para buscadores.
- Soft delete (via flag `activo=0`), restauración y borrado definitivo (individual y masivo).
- Vinculación comercial con Tango Connect: búsqueda de clientes Tango, validación de código, sincronización de IDs internos (GVA14, GVA01, GVA10, GVA23, GVA24).
- Override de relaciones Tango (condición de venta, lista de precios, vendedor, transporte).
- Limpieza de datos Tango cuando el código cambia o se vacía.
- Obtención de metadatos/catálogos Tango (condiciones de venta, listas de precio, vendedores, transportes).
- Registro y autenticación de clientes web desde el Store público (login, registro, verificación de email, reset de password).
- Envío de pedidos pendientes de un cliente al reprocesador de Tango.
- Deduplicación de clientes en checkout por documento o email.

### Qué NO hace
- No crea pedidos (eso lo hace `CheckoutService` / `PedidoWebRepository`).
- No gestiona el carrito de compras (eso lo hace `CartService`).
- No muestra el Store público (eso lo hace `StoreController`).
- No sincroniza artículos con Tango (eso lo hacen `RxnSync` / `TangoSync`).

---

## Piezas principales

### Controladores

#### `Controllers/ClienteWebController.php` — 542 líneas (backoffice)
- `resolveArea()`: detecta el área por URI (`/mi-empresa/crm/` → `crm`, sino → `tiendas`).
- `resolveRepository()`: instancia `ClienteWebRepository` normal o via `::forCrm()`.
- `buildUiContext()`: configura strings y paths de UI según área.
- `index()`: listado paginado con filtros avanzados.
- `suggestions()`: endpoint JSON para autocompletado.
- `edit()` / `update()`: edición con lógica de vinculación Tango.
- `eliminar()` / `restore()` / `forceDelete()`: individual.
- `eliminarMasivo()` / `restoreMasivo()` / `forceDeleteMasivo()`: masivo.
- `buscarTango()`: búsqueda de clientes en Tango Connect (AJAX/JSON).
- `obtenerMetadataTango()`: catálogos de relaciones Tango (AJAX/JSON).
- `validarTango()`: valida un código Tango y persiste los IDs internos.
- `enviarPendientes()`: redirige al reprocesador de pedidos del primer pedido pendiente del cliente.

#### `Controllers/ClienteAuthController.php` — 130 líneas (Store público)
- `showLoginForm()` / `processLogin()`: login de cliente web.
- `showRegisterForm()` / `processRegister()`: registro con verificación de email.
- `logout()`: cierre de sesión de cliente web.
- Usa `StoreResolver` para validar que la tienda exista y esté activa.

### Servicios

#### `Services/ClienteWebAuthService.php` — 137 líneas
- `login()`: verifica password hash, estado activo y verificación de email. Inicia sesión via `ClienteWebContext`.
- `register()`: crea cuenta nueva o promueve guest (sin password) a registrado. Genera token de verificación, envía email.
- `requestPasswordReset()`: genera PIN temporal (6 dígitos), persiste token con expiración de 30 min, envía email. Falla silenciosa si el email no existe.

#### `Services/ClienteTangoLookupService.php` — 269 líneas
- `findByCodigo()`: busca cliente en Tango por código (process 2117).
- `findById()`: busca cliente en Tango por ID_GVA14.
- `search()`: búsqueda libre de clientes Tango por código o razón social.
- `getRelacionCatalogs()`: obtiene catálogos de condiciones de venta, listas de precios, vendedores y transportes.
- API: usa curl directo contra `{key}.connect.axoft.com/Api` con headers `ApiAuthorization`, `Company`.

### Repositorio

#### `ClienteWebRepository.php` — 493 líneas
- **Patrón Strategy Table**: recibe nombre de tabla en constructor. `::forCrm()` configura `crm_clientes` con bootstrap.
- **Bootstrap on-the-fly**: `ensureSchema()` ejecuta `CREATE TABLE IF NOT EXISTS` con esquema completo si `bootstrap = true`.
- `findByDocumentoOrEmail()`: deduplicación de clientes en checkout.
- `create()` / `update()` / `updateIfChanged()`: CRUD de datos locales.
- `updateTangoData()`: persiste IDs internos de Tango.
- `clearTangoData()`: limpia todos los campos Tango de un cliente.
- `updateRelacionOverrides()`: actualiza overrides de relaciones Tango.
- `findAllPaginated()` / `countAll()`: listado paginado con filtros.
- `findSuggestions()`: búsqueda para autocompletado.
- `softDelete()` / `restore()` / `forceDelete()`: individual.
- `softDeleteBulk()` / `restoreBulk()` / `forceDeleteBulk()`: masivo.

### Vistas
- `views/index.php` — Listado CRUD con tabla, buscador, filtros, paginación, papelera, acciones masivas.
- `views/edit.php` — Formulario de edición con vinculación Tango, overrides de relaciones.

---

## Rutas / Pantallas

### Backoffice Tiendas (guard: `AuthService::requireLogin()`)
| Método | URI | Acción |
|--------|-----|--------|
| GET | `/mi-empresa/clientes` | `index` |
| GET | `/mi-empresa/clientes/sugerencias` | `suggestions` (JSON) |
| GET | `/mi-empresa/clientes/{id}/editar` | `edit` |
| POST | `/mi-empresa/clientes/{id}/editar` | `update` |
| POST | `/mi-empresa/clientes/{id}/eliminar` | `eliminar` |
| POST | `/mi-empresa/clientes/{id}/restore` | `restore` |
| POST | `/mi-empresa/clientes/{id}/force-delete` | `forceDelete` |
| POST | `/mi-empresa/clientes/eliminar-masivo` | `eliminarMasivo` |
| POST | `/mi-empresa/clientes/restore-masivo` | `restoreMasivo` |
| POST | `/mi-empresa/clientes/force-delete-masivo` | `forceDeleteMasivo` |
| GET | `/mi-empresa/clientes/buscar-tango` | `buscarTango` (JSON) |
| GET | `/mi-empresa/clientes/metadata-tango` | `obtenerMetadataTango` (JSON) |
| POST | `/mi-empresa/clientes/{id}/validar-tango` | `validarTango` |
| POST | `/mi-empresa/clientes/{id}/enviar-pendientes` | `enviarPendientes` |

### Backoffice CRM (guard: `AuthService::requireLogin()`)
| Método | URI | Acción |
|--------|-----|--------|
| GET | `/mi-empresa/crm/clientes` | `index` |
| GET | `/mi-empresa/crm/clientes/sugerencias` | `suggestions` (JSON) |
| GET | `/mi-empresa/crm/clientes/{id}/editar` | `edit` |
| POST | `/mi-empresa/crm/clientes/{id}/editar` | `update` |
| POST | `/mi-empresa/crm/clientes/{id}/eliminar` | `eliminar` |
| ... | (análogo a Tiendas con prefijo `/crm/`) | ... |

### Store público (sin guard admin; requiere tienda activa)
| Método | URI | Acción |
|--------|-----|--------|
| GET | `/{slug}/login` | `showLoginForm` |
| POST | `/{slug}/login` | `processLogin` |
| GET | `/{slug}/registro` | `showRegisterForm` |
| POST | `/{slug}/registro` | `processRegister` |
| GET | `/{slug}/logout` | `logout` |

---

## Tablas / Persistencia

### Tiendas
| Tabla | Rol |
|-------|-----|
| `clientes_web` | Clientes B2C principales |
| `empresa_config` | Credenciales Tango Connect (lectura) |

### CRM
| Tabla | Rol |
|-------|-----|
| `crm_clientes` | Clientes CRM (creada automáticamente `CREATE TABLE IF NOT EXISTS` con esquema completo) |
| `empresa_config` | Credenciales Tango Connect (lectura) |

---

## Dependencias directas

| Dependencia | Tipo | Motivo |
|-------------|------|--------|
| `App\Core\Context::getEmpresaId()` | Core | Aislamiento multiempresa |
| `App\Core\Controller` | Core | Clase base (hereda `handleCrudFilters`) |
| `App\Core\Database` | Core | Conexión PDO |
| `App\Core\View` | Core | Render de vistas |
| `App\Core\AdvancedQueryFilter` | Core | Filtros avanzados CRUD |
| `App\Core\Services\MailService` | Core | Envío de emails (verificación, reset password) |
| `App\Modules\Auth\AuthService` | Auth | `requireLogin()` en métodos backoffice |
| `App\Modules\Store\Services\StoreResolver` | Store | Resolución de tienda pública para auth |
| `App\Modules\Store\Context\PublicStoreContext` | Store | Contexto de empresa para rutas públicas |
| `App\Modules\Store\Context\ClienteWebContext` | Store | Sesión de cliente web |

---

## Dependencias indirectas / Impacto lateral

| Módulo | Cómo consume |
|--------|-------------|
| `Store\Services\CheckoutService` | Instancia `ClienteWebRepository` para deduplicar/crear clientes en checkout |
| `Store\Controllers\CheckoutController` | Instancia `ClienteWebRepository` para precargar datos de cliente logueado |
| `Store\Controllers\MisPedidosController` | Consulta `pedidos_web` filtrada por `cliente_web_id` |
| `Pedidos\Controllers\PedidoWebController` | Lee `id_gva14_tango` y datos Tango del cliente para enviar pedidos al ERP |

---

## Integraciones involucradas

### Tango Connect
- **Búsqueda de clientes**: `ClienteTangoLookupService::search()` y `findByCodigo()` consultan process 2117 de Tango API.
- **Validación de código**: `validarTango()` resuelve código → ID_GVA14 y persiste metadatos.
- **Catálogos de relaciones**: `getRelacionCatalogs()` consulta processes 2497 (condiciones venta), 984 (listas precios), 952 (vendedores), 960 (transportes).
- **Overrides locales**: el operario puede sobreescribir condición de venta, lista de precios, vendedor y transporte por cliente.

### MailService
- Envío de email de verificación al registrarse.
- Envío de email de reset de password.

---

## Seguridad

### Aislamiento multiempresa
- **Implementado**: toda query usa `empresa_id` como filtro. El repositorio recibe `empresa_id` explícito en todos los métodos de consulta y mutación.

### Permisos / Guards
- **Backoffice**: `AuthService::requireLogin()` en todos los métodos de `ClienteWebController`.
- **Store público**: `StoreResolver::resolveEmpresaPublica()` valida que la tienda exista y esté activa. `ClienteWebContext::isLoggedIn()` verifica sesión de cliente con scope de empresa.
- **No hay guard granular por rol** en el backoffice: cualquier usuario logueado puede editar/eliminar clientes.

### Admin sistema (RXN) vs Admin tenant
- No hay diferenciación explícita. El módulo opera en contexto de tenant.

### No mutación por GET
- **Cumplido**: todas las mutaciones (update, eliminar, restore, force-delete, validar-tango, enviar-pendientes) requieren POST.
- **Excepción menor**: `buscarTango()` y `obtenerMetadataTango()` son GET pero son de solo lectura (consultan API externa).

### Validación server-side
- Inputs del formulario de edición son `trim()`-eados.
- Login verifica `password_verify()` contra hash.
- Registro valida campos obligatorios (nombre, email, password).
- Token de verificación generado con `bin2hex(random_bytes(16))`.
- Reset token es un `random_int(100000, 999999)` con expiración de 30 min.

### Escape / XSS
- Las vistas deberían aplicar escape (no verificado en detalle desde este contexto documental).
- Los mensajes flash se setean con `$_SESSION` directamente.

### Impacto sobre acceso local
- Las rutas de autenticación del Store (`/{slug}/login`, etc.) son públicas por diseño.

### CSRF
- **No implementado** en formularios del backoffice ni del Store público. Deuda de seguridad activa.

---

## Reglas operativas del módulo

1. **Aislamiento multiempresa obligatorio**: toda query usa `empresa_id`.
2. **Soft delete via flag `activo`**: no usa columna `deleted_at` sino `activo = 0/1`.
3. **Bootstrap automático de tabla CRM**: `::forCrm()` ejecuta `CREATE TABLE IF NOT EXISTS crm_clientes` con esquema completo si no existe.
4. **Deduplicación en checkout**: el checkout busca primero por documento o email antes de crear un cliente nuevo.
5. **Vinculación Tango no es obligatoria**: un cliente puede existir sin código Tango. Sin vinculación, los pedidos no pueden enviarse al ERP.
6. **Sugerencias requieren mínimo 2 caracteres**: retorna vacío sin consultar DB si no se cumple.
7. **Registro desde Store genera verificación**: el cliente debe verificar su email antes de poder loguearse.
8. **Guest → Registrado**: si un cliente fue creado en checkout (sin password) y luego se registra con el mismo email, se promueve actualizando sus datos y seteando password/token.

---

## Tipo de cambios permitidos (bajo riesgo)

- Agregar campos de texto/visualización en las vistas.
- Modificar labels y mensajes flash.
- Ajustar límites de paginación.
- Ampliar `buildUiContext()` con nuevas keys.

---

## Tipo de cambios sensibles (requieren análisis previo)

- **Modificar el esquema de `clientes_web`**: impacta Store (checkout), Pedidos (envío a Tango), `ensureSchema()` de CRM.
- **Cambiar `findByDocumentoOrEmail()`**: rompe la deduplicación de clientes en checkout.
- **Cambiar la lógica de `resolveArea()`**: puede causar que CRM opere sobre `clientes_web` o viceversa.
- **Cambiar `updateTangoData()` o `clearTangoData()`**: rompe la vinculación comercial y el flujo de envío de pedidos a Tango.
- **Cambiar la firma JSON de `/sugerencias`**: rompe autocompletado en módulos que lo consuman.
- **Modificar `ClienteWebAuthService`**: impacta login, registro y verificación del Store público.

---

## Puntos críticos del código

| Zona | Archivo | Riesgo |
|------|---------|--------|
| `ensureSchema()` con CREATE TABLE IF NOT EXISTS | ClienteWebRepository.php | DDL completo en bootstrap; si la tabla existe parcialmente puede no actualizarse |
| `clearTangoData()` usa tabla hardcodeada `clientes_web` | ClienteWebRepository.php | No respeta Strategy Table; en CRM limpia la tabla equivocada |
| `updateRelacionOverrides()` usa tabla hardcodeada `clientes_web` | ClienteWebRepository.php | Mismo problema: no respeta Strategy Table para CRM |
| `requestJson()` con `CURLOPT_SSL_VERIFYPEER = false` | ClienteTangoLookupService.php | Deshabilita verificación SSL contra API Tango |
| `findByCodigo()` concatena código en SQL | ClienteTangoLookupService.php | SQL-injection mitigado parcialmente con `str_replace("'", "''", ...)` |

---

## No romper

1. **Firma JSON de `/sugerencias`**: `{success, data: [{id, label, value, caption}]}`.
2. **`findByDocumentoOrEmail()`**: consumida por `CheckoutService` para deduplicación.
3. **`updateTangoData()` / campos `id_gva14_tango`**: consumidos por `PedidoWebController` para enviar pedidos a Tango.
4. **Sesión de `ClienteWebContext`**: usada por todo el Store público para saber si el cliente está logueado.
5. **Separación física de tablas por área**: `clientes_web` ≠ `crm_clientes`.

---

## Riesgos conocidos

1. **Tablas hardcodeadas en `clearTangoData()` y `updateRelacionOverrides()`**: usan `clientes_web` literal en lugar de `$this->clientesTable`. En contexto CRM, operan sobre la tabla equivocada.
2. **Sin CSRF en formularios**: deuda de seguridad activa tanto en backoffice como en Store.
3. **SSL deshabilitado en Tango lookup**: `CURLOPT_SSL_VERIFYPEER = false`.
4. **DDL en bootstrap CRM**: `CREATE TABLE IF NOT EXISTS` ejecuta schema completo; si la tabla existe pero con schema diferente, no se actualiza.
5. **Sin guard granular por rol**: cualquier usuario logueado puede editar/eliminar clientes.
6. **Login redirect con `filter_var($next, FILTER_SANITIZE_URL)`**: podría ser insuficiente para prevenir open redirect.

---

## Checklist post-cambio

- [ ] El listado de clientes carga en Tiendas (`/mi-empresa/clientes`)
- [ ] El listado de clientes carga en CRM (`/mi-empresa/crm/clientes`)
- [ ] El formulario de edición guarda datos locales correctamente
- [ ] La vinculación Tango (buscar, validar, metadata) funciona
- [ ] El checkout del Store crea/reutiliza clientes correctamente
- [ ] El login y registro del Store funcionan
- [ ] Los pedidos pendientes se pueden reenviar desde el cliente
- [ ] Soft delete, restore y force-delete funcionan (individual y masivo)

---

## Documentación relacionada

- `app/modules/Store/MODULE_CONTEXT.md` — Store público que consume clientes
- `app/modules/Pedidos/MODULE_CONTEXT.md` — Pedidos que dependen de vinculación Tango del cliente

---

## Regla de mantenimiento

Este archivo debe actualizarse si cambian:
- El esquema de `clientes_web` o `crm_clientes`
- La estrategia de detección de área (`resolveArea`)
- Las rutas del módulo
- La integración con Tango Connect
- El flujo de autenticación del Store público
- El contrato JSON de `/sugerencias`
