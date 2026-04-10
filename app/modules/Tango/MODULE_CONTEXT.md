# MODULE_CONTEXT — Tango

## Nivel de criticidad
ALTO (Crítico para Integraciones)

Este módulo impacta directamente en:
- Conectividad con el ERP Tango (Axoft/Nexo) vía API Connect.
- Sincronización masiva de artículos, precios, stock y clientes.
- Creación de pedidos en Tango desde el flujo CRM/Tiendas.
- Consistencia de datos entre el sistema local y el ERP.
- Resolución de cabeceras de pedidos (perfiles, talonarios, depósitos, monedas).

Cualquier cambio debe considerarse de alto riesgo por su impacto en datos fiscales y transaccionales del ERP.

---

## Propósito

Módulo de infraestructura de integración con **Tango Connect** (API REST de Axoft/Nexo). Provee:
1. **Clientes HTTP tipados** (`TangoApiClient`, `TangoOrderClient`) para comunicarse con los endpoints de Connect.
2. **Servicio de orquestación** (`TangoService`) que instancia la conexión por empresa/área y expone métodos de fetch tipados.
3. **Servicio de sincronización masiva** (`TangoSyncService`) para artículos, precios, stock y clientes.
4. **Controlador de sincronización** (`TangoSyncController`) con endpoints para disparar sync desde UI.
5. **Resolución de cabeceras de pedidos** (`TangoOrderHeaderResolver`) con lógica de cascading entre perfil de pedido, config de empresa y defaults legacy.
6. **Snapshot de perfiles** (`TangoProfileSnapshotService`) para cachear metadata de perfiles de pedido.
7. **Mappers** para transformar payloads de Connect a entidades locales y viceversa.
8. **Log de sincronización** (`TangoSyncLogRepository`) para trazabilidad.

---

## Alcance

### Qué hace
- Fetch paginado de entidades desde Connect: artículos (process 87), clientes (process 2117), precios (process 20091), stock (process 17668).
- Sync masivo: upsert de artículos, update de precios por lista, update de stock por depósito, upsert de clientes CRM.
- Sync total (artículos + precios + stock en una sola operación orquestada).
- Catálogos maestros: depósitos (process 2941), listas de precio (process 984), empresas (process 1418), clasificaciones PDS (process 326), perfiles de pedido (process 20020).
- Creación de pedidos en Tango (process 19845) vía `TangoOrderClient::sendOrder()`.
- Resolución de IDs de cabecera para pedidos: talonario, vendedor, condición de venta, depósito, zona, transporte, clasificación, moneda.
- Snapshot y cache de perfiles de pedido para evitar consultas repetidas a Connect.
- Mapeo bidireccional: Connect JSON → entidad local (`ArticuloMapper`, `CrmClienteMapper`) y local → payload Tango (`TangoOrderMapper`).
- Trazabilidad completa de cada sync (inicio, fin, stats, errores) en tabla `tango_sync_logs`.
- Limpieza de FileCache de catálogo tras sync exitoso (solo área Tiendas).

### Qué NO hace
- No ejecuta sync automático/desatendido. Todo es on-demand desde UI.
- No gestiona la configuración de conexión (eso es `EmpresaConfig`).
- No maneja la UI de exploración de artículos/clientes (eso es `Articulos`, `CrmClientes`).
- No implementa sync de pedidos transaccionales desde Tango hacia local.
- No realiza validaciones fiscales sobre los datos recibidos.

---

## Piezas principales

### Clientes HTTP

- **`TangoApiClient.php`** — 497 líneas
  - Cliente REST genérico para Connect. Headers: `ApiAuthorization`, `Company`, `Client-Id` (opcional).
  - Métodos: `getArticulos()`, `getClientes()`, `getPrecios()`, `getStock()`, `getArticuloById()`, `getClienteById()`, `updateEntity()`, `testConnection()`.
  - Catálogos: `getMaestroDepositos()`, `getMaestroListasPrecio()`, `getMaestroEmpresas()`, `getClasificacionesPds()`, `getPerfilesPedidos()`, `getPerfilPedidoById()`.
  - Paginación interna con detección de fin de datos (`seenFirstIds`, `count < pageSize`).
  - Extracción defensiva de `GetById` con 5 variantes de envelope.

- **`TangoOrderClient.php`** — 73 líneas
  - Cliente especializado para creación de pedidos (process 19845).
  - `sendOrder()`: POST a `Create?process=19845`.
  - `getArticleIdByCode()`: resolución de `ID_STA11` por código de artículo via `GetByFilter`.
  - `getOrderById()`: consulta de pedido creado.

### Servicios

- **`TangoService.php`** — 159 líneas
  - Orquestador de conexión por empresa/área. Instancia `TangoApiClient` con credenciales resueltas de `EmpresaConfigService`.
  - Factory: `TangoService::forCrm()`.
  - Fetch tipados: `fetchArticulos()`, `fetchClientes()`, `fetchPrecios()`, `fetchStock()` — retornan `TangoResponseDTO`.
  - Resolución de URL API: por `tango_api_url` directo o derivando de `tango_connect_key`.

- **`Services/TangoSyncService.php`** — 397 líneas
  - Motor de sincronización masiva. Un servicio por área (tiendas/crm).
  - `syncArticulos()`: fetch + mapeo + upsert en `articulos` o `crm_articulos`.
  - `syncPrecios()`: fetch + match por SKU + update de `precio_lista_1` / `precio_lista_2` según config de listas.
  - `syncStock()`: fetch + match por SKU + update de `stock_actual` filtrado por depósito configurado.
  - `syncClientes()`: fetch paginado + mapeo + upsert en `crm_clientes`.
  - `syncTodo()`: orquestación de artículos + precios + stock en secuencia.
  - Trazabilidad: cada sync registra inicio/fin/stats/errores en `tango_sync_logs`.
  - Limpieza de FileCache tras sync de Tiendas.

- **`Services/TangoOrderHeaderResolver.php`** — 277 líneas
  - Resuelve IDs de cabecera para pedidos Tango con cascading: perfil (snapshot usuario > snapshot empresa > API live) → config empresa → defaults legacy.
  - Soporte de moneda con override por company_id.
  - Cache de snapshots en config de empresa y en perfil de usuario.

- **`Services/TangoProfileSnapshotService.php`** — 118 líneas
  - Fetch y normalización del detalle de perfil de pedido de Connect.
  - Lookup case-insensitive de keys con múltiples variantes.
  - Retorna snapshot estructurado para cachear en `tango_perfil_snapshot_json`.

### Controlador

- **`Controllers/TangoSyncController.php`** — 139 líneas
  - Endpoints para disparar sync desde UI con redirect + flash message.
  - `syncArticulos()`, `syncPrecios()`, `syncStock()`, `syncClientes()`, `syncTodo()`.
  - Resolución de área por URI (`/mi-empresa/crm/` → crm, sino → tiendas).
  - Todos requieren `AuthService::requireLogin()`.

### DTOs
- **`DTOs/TangoResponseDTO.php`** — DTO simple: `isSuccess`, `payload`, `errorMessage`.

### Mappers
- **`Mappers/ArticuloMapper.php`** — Transforma JSON de Connect (process 87) a `Articulo` local.
- **`Mappers/CrmClienteMapper.php`** — Transforma JSON de Connect (process 2117) a datos de cliente CRM.
- **`Mappers/TangoOrderMapper.php`** — Transforma pedido local a payload JSON para `Create?process=19845`.

### Repositorio
- **`Repositories/TangoSyncLogRepository.php`** — 57 líneas
  - `startLog()`: INSERT con estado `IN_PROGRESS`.
  - `endLog()`: UPDATE con stats, estado final y mensaje de error.

---

## Rutas / Pantallas

### Sync Tiendas
| Método | URI | Acción |
|--------|-----|--------|
| POST | `/mi-empresa/sync/articulos` | `syncArticulos` |
| POST | `/mi-empresa/sync/precios` | `syncPrecios` |
| POST | `/mi-empresa/sync/stock` | `syncStock` |
| POST | `/mi-empresa/sync/todo` | `syncTodo` |

### Sync CRM
| Método | URI | Acción |
|--------|-----|--------|
| POST | `/mi-empresa/crm/sync/articulos` | `syncArticulos` |
| POST | `/mi-empresa/crm/sync/precios` | `syncPrecios` |
| POST | `/mi-empresa/crm/sync/stock` | `syncStock` |
| POST | `/mi-empresa/crm/sync/clientes` | `syncClientes` |
| POST | `/mi-empresa/crm/sync/todo` | `syncTodo` |

> El módulo no tiene vistas propias. Los botones de sync viven en los módulos Articulos, CrmClientes, EmpresaConfig, etc.

---

## Tablas / Persistencia

| Tabla | Rol |
|-------|-----|
| `tango_sync_logs` | Historial de sincronizaciones (empresa, tipo, fechas, stats, estado, error) |

> El módulo **lee y escribe** en tablas de otros módulos: `articulos`, `crm_articulos` (via `ArticuloRepository`), `crm_clientes` (via `CrmClienteRepository`).
> Las tablas de configuración (`empresa_config`, `empresa_config_crm`) son leídas via `EmpresaConfigService`.

---

## Dependencias directas

| Dependencia | Tipo | Motivo |
|-------------|------|--------|
| `App\Core\Context::getEmpresaId()` | Core | Aislamiento multiempresa |
| `App\Core\Database` | Core | Conexión PDO para logs |
| `App\Core\Controller` | Core | Clase base del controlador |
| `App\Core\Flash` | Core | Mensajes flash post-sync |
| `App\Core\FileCache` | Core | Limpieza de cache post-sync (Tiendas) |
| `App\Infrastructure\Http\ApiClient` | Infrastructure | Cliente HTTP base para requests a Connect |
| `App\Infrastructure\Exceptions\*` | Infrastructure | Excepciones tipadas |
| `App\Modules\Auth\AuthService` | Auth | `requireLogin()` + `getCurrentUser()` para resolución de perfil |
| `App\Modules\EmpresaConfig\EmpresaConfigService` | EmpresaConfig | Credenciales y config de conexión Tango por empresa/área |
| `App\Modules\Articulos\ArticuloRepository` | Articulos | Upsert y update de artículos/precios/stock |
| `App\Modules\CrmClientes\CrmClienteRepository` | CrmClientes | Upsert de clientes CRM |

---

## Dependencias indirectas / Impacto lateral

| Módulo | Cómo depende de Tango |
|--------|----------------------|
| `Articulos` | Llama `pushToTango()` que delega en `RxnSync`, que usa `TangoService` internamente |
| `CrmClientes` | Botón sync dispara `TangoSyncController::syncClientes()` |
| `RxnSync` | Usa `TangoService::forCrm()` para GetById, Update y listado paginado |
| `CrmPedidosServicio` | Usa `TangoOrderClient` para crear pedidos en Tango (process 19845) |
| `EmpresaConfig` | Expone UI de configuración de credenciales Tango y perfiles de pedido; usa `TangoProfileSnapshotService` y `TangoApiClient` para test de conexión y fetch de catálogos |

---

## Integraciones involucradas

### Tango Connect (Axoft/Nexo)
| Process | Endpoint | Entidad |
|---------|----------|---------|
| 87 | `Get`, `GetById`, `GetByFilter` | Artículos |
| 2117 | `Get`, `GetById`, `Update` | Clientes |
| 20091 | `Get` | Precios |
| 17668 | `GetApiLiveQueryData` | Stock |
| 19845 | `Create`, `GetById` | Pedidos |
| 20020 | `Get`, `GetById` | Perfiles de Pedido |
| 984 | `Get` | Listas de Precio (maestro) |
| 2941 | `Get` | Depósitos (maestro) |
| 1418 | `Get` | Empresas (maestro) |
| 326 | `Get` | Clasificaciones PDS (maestro) |

### Protocolo de comunicación
- REST sobre HTTPS.
- Headers obligatorios: `ApiAuthorization` (token), `Company` (ID empresa Tango), `Accept: application/json`.
- Header opcional: `Client-Id` (key de Connect).
- URL derivada de `tango_connect_key` si no hay `tango_api_url` explícita: `https://{key}.connect.axoft.com/Api`.

---

## Seguridad

### Aislamiento multiempresa
- `TangoService` requiere `Context::getEmpresaId()` para instanciarse. Sin empresa en sesión, lanza excepción.
- Las credenciales de Tango son por empresa (via `EmpresaConfigService`). No hay posibilidad de acceso cruzado a nivel de API.
- Las operaciones de sync (upsert, update) siempre pasan `empresa_id` como filtro.

### Permisos / Guards
- `TangoSyncController` requiere `AuthService::requireLogin()` en todos los endpoints.
- **No hay guard de admin**: cualquier usuario autenticado del tenant puede disparar una sincronización masiva. Esto es una decisión de diseño pero podría ser un riesgo en tenants con muchos usuarios.

### Admin Sistema vs Tenant
- `TangoService` no diferencia entre tipos de admin. La configuración de credenciales vive en `EmpresaConfig` y es editable por Admin Tenant.
- Las credenciales de Tango (`tango_connect_token`, `tango_connect_company_id`) son datos sensibles almacenados en la tabla `empresa_config` / `empresa_config_crm`.

### Mutación por método
- Todos los endpoints de sync operan por **POST**.
- Los catálogos maestros se consultan por **GET** (desde EmpresaConfig, no desde este módulo directamente).
- No existen endpoints GET en `TangoSyncController` que muten estado.

### Validación server-side
- Los payloads recibidos de Connect se validan defensivamente (nullable checks, `is_array`, parsing de envelopes con múltiples variantes).
- Los mappers descartan registros con datos insuficientes (`$stats['omitidos']++`).

### Escape / XSS
- No aplica directamente: el módulo no renderiza HTML. Los datos sincronizados se almacenan en BD y se renderizan por los módulos consumidores.

### CSRF
- Los endpoints de sync no validan token CSRF. Deuda de seguridad activa.

### Acceso local
- Las credenciales de Tango se almacenan en BD. No hay archivos sensibles en disco.
- `TangoApiClient` tiene propiedades debug públicas (`debugLastRawDepositos`, `debugLastHttpRequest`, etc.) que podrían exponer payloads si se serializan inadvertidamente.

---

## No romper

1. **Formato de headers de Connect**: `ApiAuthorization`, `Company`, `Client-Id`. Cambiar el nombre o formato de estos headers rompe toda la comunicación.
2. **Procesos numéricos**: cada entidad tiene un process ID fijo en Tango. No inventar ni cambiar.
3. **Lógica de cascading en `TangoOrderHeaderResolver`**: el orden de precedencia (usuario → config empresa → API → legacy) es deliberado y afecta directamente los pedidos generados en Tango.
4. **Mappers de entidades**: `ArticuloMapper`, `CrmClienteMapper`, `TangoOrderMapper` definen el contrato de transformación. Cambiar un campo de mapeo puede corromper datos en el ERP.
5. **Trazabilidad en `tango_sync_logs`**: no eliminar el registro de sync. Es la única evidencia auditable de operaciones con el ERP.
6. **Limpieza de FileCache post-sync**: si se elimina, el catálogo público (Store) mostrará datos desactualizados.

---

## Riesgos conocidos

1. **Credenciales en propiedades debug**: `TangoApiClient` tiene `$debugLastRawDepositos`, `$debugLastRawEmpresas`, etc. como propiedades públicas. Si un módulo serializa o loguea el objeto, podría exponer payloads sensibles.
2. **Sin throttle en sync masivo**: un usuario puede disparar múltiples syncs simultáneos. No hay mutex ni cola.
3. **Paginación limitada**: `fetchArticulos()` y `fetchPrecios()` solo consultan la primera página. Solo `syncClientes()` implementa paginación completa (hasta 100 páginas).
4. **Timeouts en masivos**: `set_time_limit` no se aplica en `TangoSyncService` directamente (se aplica en `RxnSyncService`). Syncs muy grandes podrían exceder el timeout del servidor.
5. **Override de moneda hardcodeado**: `TangoOrderHeaderResolver::MONEDA_LOCAL_OVERRIDE_BY_COMPANY` tiene IDs de empresa Tango hardcodeados para sandbox y producción. Nuevas empresas pueden necesitar agregarse manualmente.
6. **Sin CSRF en endpoints de sync**.
7. **Sin guard de admin para sync**: cualquier usuario del tenant puede disparar una sincronización masiva que afecte datos compartidos.

---

## Checklist post-cambio

- [ ] El test de conexión desde EmpresaConfig retorna exitoso.
- [ ] Sync de artículos (masivo) completa sin errores y registra log en `tango_sync_logs`.
- [ ] Sync de precios actualiza correctamente `precio_lista_1` / `precio_lista_2` según configuración.
- [ ] Sync de stock actualiza `stock_actual` filtrado por el depósito configurado.
- [ ] Sync de clientes CRM inserta/actualiza en `crm_clientes`.
- [ ] La creación de pedido en Tango (desde PDS/Presupuesto) envía el payload correcto.
- [ ] Verificar que los headers HTTP hacia Connect sean correctos (ApiAuthorization, Company).
- [ ] Si se tocaron mappers: verificar que los campos mapeados coinciden con el esquema de la tabla destino.

---

## Tipo de cambios permitidos

- Agregar nuevos catálogos maestros (nuevos process de consulta).
- Ampliar `TangoResponseDTO` con campos auxiliares.
- Agregar logging o métricas adicionales.
- Ajustes defensivos en parsing de envelopes.

## Tipo de cambios sensibles

- Modificar headers de autenticación de Connect.
- Cambiar process IDs.
- Alterar mappers de entidades (campos, transformaciones).
- Modificar lógica de cascading en `TangoOrderHeaderResolver`.
- Cambiar la lógica de paginación en sync masivo.
- Modificar el payload de creación de pedidos (`TangoOrderMapper`).

---

## Documentación relacionada

- `docs/whitelist_definition.md` — Largos y campos permitidos para Push/Update a Tango.
- `app/modules/RxnSync/MODULE_CONTEXT.md` — Módulo hermano de sync bidireccional CRM.
- `app/modules/Articulos/MODULE_CONTEXT.md` — Módulo consumidor principal de datos sincronizados.

---

## Regla de mantenimiento

Este archivo debe actualizarse si cambian:
- Los process IDs de Tango Connect.
- Los mappers de entidades (campos, transformaciones).
- La lógica de resolución de cabeceras de pedidos.
- Las credenciales o headers requeridos por Connect.
- Los módulos que consumen `TangoService`, `TangoApiClient` o `TangoOrderClient`.
- La estructura de `tango_sync_logs`.
