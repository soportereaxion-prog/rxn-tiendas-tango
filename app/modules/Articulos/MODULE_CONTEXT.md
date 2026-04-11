# MODULE_CONTEXT — Articulos (alias: CrmArticulos)

> **Nota de nomenclatura:** El módulo físico es `app/modules/Articulos`. No existe un módulo separado llamado `CrmArticulos`. El mismo módulo sirve **dos áreas operativas** (Tiendas y CRM) usando una estrategia de contexto dinámico por URI.

---

## Nivel de criticidad

**ALTO**

Este módulo es la fuente de datos de artículos para cuatro módulos CRM activos (Presupuestos, Pedidos de Servicio, RxnSync y TangoSyncService) y para el frente público (Store). Un cambio en el repositorio o en la lógica de resolución de área puede romper silenciosamente múltiples flujos sin errores evidentes.

---

## Propósito

Gestionar el catálogo de artículos en dos entornos operativos desacoplados:

- **Tiendas**: catálogo público con categorías, imágenes, precios de lista, stock y flags de oferta. Fuente de datos del Store B2C y del carrito de compras.
- **CRM**: catálogo interno con precios, stock y sync Tango. Fuente de datos de Presupuestos CRM, Pedidos de Servicio y RxnSync CRM.

Cada área opera sobre su propia tabla de persistencia (`articulos` vs `crm_articulos`), con tablas auxiliares propias, pero compartiendo el mismo código de controlador, repositorio y vistas.

---

## Alcance

### Qué hace
- Listado paginado con búsqueda, ordenamiento y filtros avanzados (CRUD filter embudo).
- Edición manual de campos locales: nombre, descripción, precios, stock, activo, categoría (solo Tiendas), imágenes.
- Galería de imágenes por artículo (máximo 5), con selección de portada.
- Soft delete, restauración y borrado definitivo (individual y masivo).
- Purga total de la tabla por empresa.
- Sugerencias de autocompletado JSON (`/sugerencias`) para buscadores en PDS, Presupuestos y listado propio.
- Push individual a Tango via `RxnSyncService::pushToTangoByLocalId()`.
- Pull individual a Tango via `RxnSyncController::pullSingle()`.
- Auditoría de payload Tango via `RxnSyncController::getPayload()`.
- Catálogo público paginado para el Store B2C (`findPublicCatalogPaginated`).

### Qué NO hace
- No crea artículos manualmente (solo los sincroniza desde Tango via TangoSyncService).
- No gestiona stock en tiempo real (solo refleja la última sincronización).
- No realiza checkout ni pedidos.
- En CRM: no muestra categorías, no expone flags de oferta, no tiene acceso al Store público.
- La funcionalidad "Copiar artículo" existe en el código pero **no está implementada** (`copy()` retorna error directamente).

---

## Piezas principales

### Controlador
- `ArticuloController.php` — 532 líneas
  - `resolveArea()`: detecta el área por URI (`/mi-empresa/crm/` → `crm`, sino → `tiendas`)
  - `resolveRepository()`: instancia `ArticuloRepository` normal o via `::forCrm()` según área
  - `buildUiContext()`: configura todos los strings y paths de UI según área (basePaths, labels, flags)
  - `clearEnvironmentCaches()`: limpia FileCache del catálogo solo en área Tiendas
  - `pushToTango()`: endpoint AJAX que delega en `RxnSyncService::pushToTangoByLocalId()`
  - `handleCrudFilters()`: hook de la clase base `Controller` para filtros avanzados

### Repositorio
- `ArticuloRepository.php` — 704 líneas
  - **Patrón Strategy Table**: recibe mapa de tablas en constructor. `::forCrm()` configura `crm_articulos`, `crm_articulo_categoria_map`, `crm_articulo_imagenes`, `empresa_config_crm` y deshabilita store flags.
  - **Bootstrap on-the-fly**: al instanciar para CRM, ejecuta `ensureSchema()` que crea las tablas CRM con `CREATE TABLE IF NOT EXISTS ... LIKE tablaFuente` si no existen. Esto ocurre en cada request.
  - **Soft delete on-the-fly**: `ensureSoftDeleteSchema()` ejecuta `ALTER TABLE ADD COLUMN deleted_at` al instanciar, ignorando la excepción si ya existe (corre en cada request).
  - `upsert()`: insert-or-update por `(empresa_id, codigo_externo)` — usado por TangoSyncService.
  - `updatePrecioListas()` / `updateStock()`: actualizaciones por SKU — usados por TangoSyncService.
  - `findSuggestions()`: retorna hasta N registros con ranking de relevancia por SKU/nombre.
  - `syncCategoriaMapping()`: gestión de la tabla de mapa categoría por código externo (solo Tiendas activo).
  - `syncStoreOfferFlag()`: gestión de flags de oferta (no opera si `store_flags = null`).
  - `findPublicCatalogPaginated()` / `countPublicCatalog()`: consultas para el Store B2C.
  - Collation explícita `utf8mb4_unicode_ci` en JOINs sobre `codigo_externo` para evitar errores de comparación.

### Modelo
- `Articulo.php` — 24 líneas — POPO (Plain Old PHP Object)
  - Campos: `id`, `empresa_id`, `codigo_externo`, `nombre`, `descripcion`, `precio`, `precio_lista_1`, `precio_lista_2`, `stock_actual`, `activo`, `mostrar_oferta_store`, `categoria_id`, `categoria_nombre`, `categoria_slug`, `fecha_ultima_sync`, `imagen_principal`, `deleted_at`

### Vistas
- `views/index.php` — 32 KB — Listado CRUD con tabla, buscador, filtros, paginación, papelera, acciones masivas, botones sync en header
- `views/form.php` — 20 KB — Formulario edición con galería de imágenes, campos comerciales, botones Push/Pull/Info Tango (solo si `showSyncActions = true`)

---

## Rutas / Pantallas

### Tiendas (guard: `requireTiendasAccess`)
| Método | URI | Acción |
|--------|-----|--------|
| GET | `/mi-empresa/articulos` | `index` |
| GET | `/mi-empresa/articulos/sugerencias` | `suggestions` (JSON) |
| POST | `/mi-empresa/articulos/purgar` | `purgar` |
| POST | `/mi-empresa/articulos/eliminar-masivo` | `eliminarMasivo` |
| POST | `/mi-empresa/articulos/restore-masivo` | `restoreMasivo` |
| POST | `/mi-empresa/articulos/force-delete-masivo` | `forceDeleteMasivo` |
| POST | `/mi-empresa/articulos/{id}/eliminar` | `eliminar` |
| POST | `/mi-empresa/articulos/{id}/restore` | `restore` |
| POST | `/mi-empresa/articulos/{id}/force-delete` | `forceDelete` |
| GET | `/mi-empresa/articulos/editar` | `editar` (?id=N) |
| POST | `/mi-empresa/articulos/editar` | `actualizar` |
| POST | `/mi-empresa/articulos/{id}/push-tango` | `pushToTango` (AJAX/JSON) |

### CRM (guard: `requireCrmAccess`)
| Método | URI | Acción |
|--------|-----|--------|
| GET | `/mi-empresa/crm/articulos` | `index` |
| GET | `/mi-empresa/crm/articulos/sugerencias` | `suggestions` (JSON) |
| POST | `/mi-empresa/crm/articulos/purgar` | `purgar` |
| POST | `/mi-empresa/crm/articulos/eliminar-masivo` | `eliminarMasivo` |
| POST | `/mi-empresa/crm/articulos/restore-masivo` | `restoreMasivo` |
| POST | `/mi-empresa/crm/articulos/force-delete-masivo` | `forceDeleteMasivo` |
| POST | `/mi-empresa/crm/articulos/{id}/eliminar` | `eliminar` |
| POST | `/mi-empresa/crm/articulos/{id}/restore` | `restore` |
| POST | `/mi-empresa/crm/articulos/{id}/force-delete` | `forceDelete` |
| GET | `/mi-empresa/crm/articulos/editar` | `editar` (?id=N) |
| POST | `/mi-empresa/crm/articulos/editar` | `actualizar` |
| POST | `/mi-empresa/crm/articulos/{id}/push-tango` | `pushToTango` (AJAX/JSON) |

> Pull individual: resuelto por `RxnSyncController::pullSingle` en `/mi-empresa/(crm/)rxn-sync/pull` (POST, body `id` + `entidad=articulo`).
> Auditoría payload: `RxnSyncController::getPayload` en `/mi-empresa/(crm/)rxn-sync/payload` (GET).

---

## Tablas / Persistencia

### Tiendas
| Tabla | Rol |
|-------|-----|
| `articulos` | Catálogo principal |
| `articulo_categoria_map` | Relación artículo-categoría por `articulo_codigo_externo` |
| `articulo_imagenes` | Galería de imágenes (max 5 por artículo) |
| `articulo_store_flags` | Flags de oferta para el Store B2C |
| `empresa_config` | Imagen default de producto fallback |
| `categorias` | JOIN para resolver nombre y slug |

### CRM
| Tabla | Rol |
|-------|-----|
| `crm_articulos` | Catálogo CRM (creada automáticamente `LIKE articulos` si no existe) |
| `crm_articulo_categoria_map` | Mapa categoría CRM (creada automáticamente, no usada en UI CRM) |
| `crm_articulo_imagenes` | Galería CRM (creada automáticamente) |
| `empresa_config_crm` | Config CRM (imagen default fallback) |

> `store_flags` es `null` para CRM → los métodos `syncStoreOfferFlag`, `selectStoreFlagsColumns` y `joinStoreFlags` son no-ops.

---

## Dependencias directas

| Dependencia | Tipo | Motivo |
|-------------|------|--------|
| `App\Core\Context::getEmpresaId()` | Core | Aislamiento multiempresa |
| `App\Core\Controller` | Core | Clase base (hereda `handleCrudFilters`) |
| `App\Core\View` | Core | Render de vistas |
| `App\Core\FileCache` | Core | Limpieza de cache de catálogo (solo Tiendas) |
| `App\Core\Flash` | Core | Mensajes flash |
| `App\Core\AdvancedQueryFilter` | Core | Filtros avanzados CRUD |
| `App\Modules\Auth\AuthService` | Auth | `requireLogin()` en todos los métodos |
| `App\Modules\Categorias\CategoriaRepository` | Categorias | Listado y validación de categorías (solo Tiendas) |
| `App\Modules\RxnSync\RxnSyncService` | RxnSync | Push to Tango individual |
| `App\Modules\EmpresaConfig\EmpresaConfigRepository` | EmpresaConfig | Config de lote sync para `pushToTango` |
| `App\Shared\Services\OperationalAreaService` | Shared | Resolución de `helpPath` |

---

## Dependencias indirectas / Impacto lateral

Estos módulos **consumen datos de `crm_articulos` o `articulos`** directamente:

| Módulo | Cómo consume |
|--------|-------------|
| `Store\Controllers\StoreController` | Instancia `new ArticuloRepository()` para catálogo público y detalle de producto |
| `Store\Services\CartService` | Instancia `new ArticuloRepository()` para validar precios y disponibilidad en carrito |
| `CrmPresupuestos\PresupuestoController` | Llama `ArticuloRepository::forCrm()->findSuggestions()` para autocompletado de renglones |
| `CrmPresupuestos\PresupuestoRepository` | SQL directo sobre `crm_articulos` para contexto de artículo en presupuesto |
| `CrmPedidosServicio\PedidoServicioRepository` | SQL directo sobre `crm_articulos` para sugerencias de artículos en PDS |
| `Tango\Services\TangoSyncService` | Instancia `ArticuloRepository` o `::forCrm()` para `upsert`, `updatePrecioListas`, `updateStock` |
| `RxnSync\RxnSyncService` | SQL directo sobre `crm_articulos` en listado, pull unitario y push unitario |

> **Si cambian el esquema de `crm_articulos` (columnas, índices, nombre)**, todos los módulos anteriores pueden fallar silenciosamente.

---

## Integraciones involucradas

### Tango Connect (via RxnSyncService / TangoSyncService)
- **Dirección Push** (artículo → Tango): `ArticuloController::pushToTango()` delega en `RxnSyncService::pushToTangoByLocalId($empresaId, $localId, 'articulo', $syncBatch)`.
- **Dirección Pull** (Tango → local): resuelto por `RxnSyncController::pullSingle()` con `entidad=articulo`.
- **Sync masivo** (artículos, precios, stock): resuelto por `TangoSyncController` + `TangoSyncService`, que llama `upsert()`, `updatePrecioListas()`, `updateStock()` del repositorio.
- El sync masivo opera en el área detectada por la URI (`/mi-empresa/crm/sync/*` → CRM, `/mi-empresa/sync/*` → Tiendas).

### FileCache (solo Tiendas)
- Al mutarse datos (actualizar, eliminar, restaurar, purgar), `clearEnvironmentCaches()` invalida los prefijos `catalogo_empresa_{id}` y `categorias_store_empresa_{id}`.
- En CRM este método es no-op. Si se agrega cache en CRM en el futuro, debe actualizarse este método.

---

## Reglas operativas del módulo

1. **Aislamiento multiempresa obligatorio**: toda query usa `empresa_id` como filtro. No existe lectura cruzada entre empresas.
2. **`codigo_externo` es inmutable desde UI**: es el ancla de identidad con Tango. No puede editarse manualmente en el formulario. Cualquier cambio debe venir de un pull/sync.
3. **Bootstrap automático de tablas CRM**: `ArticuloRepository::forCrm()` crea `crm_articulos`, `crm_articulo_categoria_map` y `crm_articulo_imagenes` si no existen, en **cada request**. Esto es intencional pero tiene costo de DDL por request.
4. **Soft delete por defecto**: eliminar = `SET deleted_at = NOW()`. El borrado definitivo requiere pasar por papelera primero o usar force-delete.
5. **Categorías solo en Tiendas**: en contexto CRM, `showCategories = false`. El campo `categoria_id` no se procesa en update para CRM.
6. **Store flags solo en Tiendas**: `store_flags = null` en CRM. Acceder a `syncStoreOfferFlag` en contexto CRM es no-op seguro.
7. **Imágenes separadas por área**: las imágenes de Tiendas van a `/uploads/empresas/{id}/productos/{artId}/` y las de CRM a `/uploads/empresas/{id}/crm-articulos/{artId}/`.
8. **Sugerencias requieren mínimo 2 caracteres** en `q`; retorna vacío sin consultar DB si se incumple.
9. **Persistencia de filtros de listado**: el input de búsqueda F3 (`search`), el campo de búsqueda (`field`), la cantidad por página (`limit`), el filtro de estado de negocio (`estado`), el filtro de categoría (`categoria_id`, donde aplique) y los filtros Motor BD (`f[campo][op|val]`) se persisten automáticamente en `localStorage` scopeados por `pathname + empresa_id` via `public/js/rxn-filter-persistence.js` (cargado inline desde `admin_layout.php`). Al volver al listado, los filtros se restauran y se reinicia en la primera página. `status` (activos/papelera), `sort`, `dir` y `area` quedan fuera por ser navegación u orden. Para limpiarlos: `?reset_filters=1` (lo dispara `rxn-advanced-filters.js` al borrar BD) o `window.rxnFilterPersistence.clear()`. Los filtros "locales" (selección por columna) siguen viviendo en `sessionStorage` via `rxn-advanced-filters.js` con key `rxn_lf::`.

---

## Tipo de cambios permitidos (bajo riesgo)

- Agregar o modificar campos de texto/número en el formulario (ui labels, placeholders).
- Ajustar límites de paginación (25/50/100).
- Agregar columnas al listado que no cambien la firma de `findAllPaginated`.
- Ampliar `buildUiContext()` con nuevas keys de UI sin alterar keys existentes.
- Agregar nuevas rutas al controlador que no toquen `resolveArea()`, `resolveRepository()` ni el repositorio compartido.

---

## Tipo de cambios sensibles (requieren análisis previo)

- **Modificar el esquema de `articulos` o `crm_articulos`**: impacta Store, CartService, Presupuestos, PDS, RxnSync, TangoSync.
- **Cambiar `resolveArea()`**: cualquier modificación en la detección de área por URI puede causar que CRM opere sobre la tabla de Tiendas o viceversa.
- **Cambiar `forCrm()`**: altera el mapeo de todas las tablas CRM y el proceso de bootstrap on-the-fly.
- **Cambiar la lógica de `ensureSchema()` o `ensureSoftDeleteSchema()`**: estas funciones corren en cada request; un error puede bloquear todas las páginas del módulo.
- **Agregar columnas con `NOT NULL` sin DEFAULT a `articulos`**: rompe el bootstrap on-the-fly de `crm_articulos` porque `LIKE` copia estructura pero no puede insertar valores.
- **Modificar `findSuggestions()`**: rompe autocompletado en Presupuestos CRM y PDS.
- **Cambiar el contrato del endpoint `/sugerencias`** (estructura del JSON): rompe el JS de buscadores en PDS y Presupuestos.

---

## Puntos críticos del código

| Zona | Archivo | Línea | Riesgo |
|------|---------|-------|--------|
| `resolveArea()` | ArticuloController.php | 393–398 | Si la URI cambia de patrón, la detección de área falla silenciosamente (retorna `tiendas` por defecto) |
| `forCrm()` con `bootstrap` | ArticuloRepository.php | 56–70 | `ensureSchema()` ejecuta DDL en cada request; falla fatal si MySQL no permite DDL sin transacción activa |
| `ensureSoftDeleteSchema()` | ArticuloRepository.php | 47–54 | Ejecuta `ALTER TABLE` en cada request; atrapa `PDOException` silenciosamente, pero si hay otro error diferente (ej: tabla bloqueada), se pierde |
| Build collation JOINs | ArticuloRepository.php | 686–702 | JOIN con collation explícita `utf8mb4_unicode_ci`; si `codigo_externo` usa otra collation en alguna tabla, el JOIN puede retornar 0 resultados |
| `pushToTango()` | ArticuloController.php | 498–530 | Llama `EmpresaConfigRepository::forArea($area)` + `RxnSyncService`; error en Connect no detiene el flujo, retorna JSON con `success:false` |
| `syncCategoriaMapping()` | ArticuloRepository.php | 494–513 | Hace DELETE + INSERT (no upsert); si hay FK constraint futura rompe el flujo |

---

## No romper

1. **Firma JSON de `/sugerencias`**: `{success, data: [{id, label, value, caption}]}` — consumida por PDS, Presupuestos y el buscador del listado.
2. **Clave `codigo_externo` como ancla de identidad**: es el campo de match con Tango en upsert, updatePrecioListas y updateStock. Chanegarlo o validarlo diferente rompe la sincronización.
3. **Separación física de tablas por área**: `articulos` ≠ `crm_articulos`. No mezclar repositorios entre áreas.
4. **Bootstrap on-the-fly de CRM**: cualquier eliminación de `bootstrap` en `forCrm()` requiere migración previa que garantice la existencia de las tablas.
5. **Guard de acceso por área**: rutas Tiendas usan `requireTiendasAccess`, rutas CRM usan `requireCrmAccess`. No intercambiar.

---

## Riesgos conocidos

1. **DDL por request**: `ensureSchema()` y `ensureSoftDeleteSchema()` ejecutan DDL en cada request que instancie `ArticuloRepository::forCrm()`. Esto tiene costo de performance y puede generar deadlocks en alta concurrencia. No hay caché de estado.
2. **SQL directo en módulos consumidores**: `PedidoServicioRepository` y `PresupuestoRepository` consultan `crm_articulos` con SQL propio (no via repositorio). Un cambio de columna no detectado por el controlador puede romperlos.
3. **`copy()` no implementado**: el método existe, tiene botón en UI y ruta registrada, pero siempre retorna error. No es un crash, pero la UX es confusa.
4. **Categorías en CRM sin uso real**: `crm_articulo_categoria_map` se crea en bootstrap pero no se utiliza operativamente (el flag `showCategories = false` en CRM hace que el selector no aparezca ni se procese en update).
5. **Sin CSRF en formularios de edición**: el formulario de `form.php` no incluye token CSRF. El módulo no tiene endpoint que mute estado via GET, pero la ausencia de CSRF es una deuda de seguridad activa.
6. **Imágenes sin validación de tamaño**: el upload solo valida extensión (jpg, jpeg, png, webp). No hay límite de tamaño de archivo ni sanitización del nombre original antes de construir el `filename`.
7. **FileCache no limpiado en CRM**: mutaciones en `crm_articulos` no limpian ningún cache. Si en el futuro se agrega cache para el catálogo CRM, debe actualizarse `clearEnvironmentCaches()`.

---

## Checklist post-cambio

- [ ] El listado de artículos carga correctamente en Tiendas (`/mi-empresa/articulos`)
- [ ] El listado de artículos carga correctamente en CRM (`/mi-empresa/crm/articulos`)
- [ ] El buscador de artículos retorna sugerencias en Presupuestos CRM
- [ ] El buscador de artículos retorna sugerencias en Pedidos de Servicio CRM
- [ ] El Store público muestra el catálogo correctamente (`/{slug}`)
- [ ] El carrito puede agregar artículos sin error
- [ ] El formulario de edición guarda y redirige correctamente en ambas áreas
- [ ] El Push a Tango desde el formulario retorna JSON `success: true`
- [ ] Si se tocó el esquema: verificar que `crm_articulos` se sigue creando correctamente en ambiente limpio
- [ ] Si se tocaron columnas en `articulos`: verificar que `PresupuestoRepository` y `PedidoServicioRepository` no rompan

---

## Documentación relacionada

- `docs/logs/` — buscar logs sobre: `articulos`, `sync`, `tango`, `crm`
- `docs/estado/current.md` — secciones: "CRM Inicial con Datos Separados", "Categorias Local-First", "Ofertas Comerciales por SKU"
- `app/modules/RxnSync/MODULE_CONTEXT.md` — patrón de sync, shadow copy y push/pull unitario
- `app/modules/CrmClientes/MODULE_CONTEXT.md` — patrón análogo para clientes (referencia de arquitectura compartida)

---

## Regla de mantenimiento

Este archivo debe actualizarse si cambian:
- El esquema de `articulos` o `crm_articulos` (columnas, índices, FKs)
- La estrategia de detección de área (`resolveArea`)
- Las rutas del módulo (nuevas, renombradas o eliminadas)
- Los consumidores que leen `crm_articulos` directamente con SQL propio
- El contrato JSON del endpoint `/sugerencias`
- La lógica de bootstrap on-the-fly (`forCrm`, `ensureSchema`)
- Las integraciones con Tango (push/pull)
