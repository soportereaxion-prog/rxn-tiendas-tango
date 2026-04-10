# MODULE_CONTEXT — Categorias

---

## Nivel de criticidad

**MEDIO-ALTO**

Las categorías son la estructura taxonómica del catálogo de artículos para el Store B2C. Un cambio en la tabla `categorias` o en los métodos de consulta pública impacta directamente la navegación del Store y la asignación de artículos a categorías.

---

## Propósito

Gestionar el catálogo de categorías de productos para el área Tiendas. Las categorías organizan artículos en el Store público, permiten filtrado por categoría en la vitrina B2C y se vinculan a artículos mediante la tabla de mapeo `articulo_categoria_map` por `codigo_externo`.

---

## Alcance

### Qué hace
- CRUD completo de categorías (crear, editar, listar, buscar).
- Listado paginado con búsqueda, ordenamiento y filtros avanzados (CRUD filter embudo).
- Sugerencias de autocompletado JSON (`/sugerencias`) para buscadores.
- Upload de imagen de portada por categoría (jpg, jpeg, png, webp).
- Generación automática de slug único por empresa.
- Soft delete, restauración y borrado definitivo (individual y masivo).
- Flags de visibilidad: `activa` y `visible_store`.
- Orden visual configurable (`orden_visual`).
- Conteo de artículos asociados por categoría (JOIN con `articulo_categoria_map` + `articulos`).
- Provisión de categorías al Store público via `findStoreCategoriesWithCounts()`.
- Invalidación de FileCache del catálogo y categorías al mutar datos.

### Qué NO hace
- No gestiona la relación artículo-categoría directamente (eso lo hace `ArticuloRepository`).
- No opera en contexto CRM (es exclusivo del área Tiendas).
- La funcionalidad "Copiar categoría" existe como stub pero **no está implementada** (retorna flash de error).
- No tiene sincronización con Tango ni con ningún sistema externo.

---

## Piezas principales

### Controlador
- `CategoriaController.php` — 277 líneas
  - CRUD completo: `index`, `create`, `store`, `edit`, `update`, `eliminar`, `eliminarMasivo`, `restore`, `restoreMasivo`, `forceDelete`, `forceDeleteMasivo`.
  - `suggestions()`: endpoint JSON para autocompletado.
  - `copy()`: stub no implementado.
  - `clearStoreCache()`: invalida FileCache de catálogo y categorías Store.
  - `buildUiContext()`: configura paths base para UI.

### Servicio
- `CategoriaService.php` — 295 líneas
  - Capa de lógica de negocio entre controlador y repositorio.
  - `findAllForContext()`: listado paginado con filtros.
  - `findSuggestionsForContext()`: sugerencias (mínimo 2 caracteres).
  - `create()` / `update()`: validación, generación de slug único, upload de imagen.
  - `delete()` / `restore()` / `forceDelete()`: operaciones de ciclo de vida.
  - `generateUniqueSlug()`: genera slug y resuelve colisiones con sufijo numérico.
  - `storeImageIfPresent()`: upload con validación de extensión, reemplazo de imagen anterior.

### Repositorio
- `CategoriaRepository.php` — 400 líneas
  - `ensureSoftDeleteSchema()`: ejecuta ALTER TABLE para agregar `deleted_at` en cada request (ignora si ya existe).
  - `findFilteredPaginatedByEmpresaId()`: listado con JOINs a `articulo_categoria_map` y `articulos` para conteo.
  - `findSuggestionsByEmpresaId()`: búsqueda para autocompletado.
  - `findStoreCategoriesWithCounts()`: categorías activas y visibles con conteo de artículos activos (usado por Store).
  - `findSelectableByEmpresaId()`: lista simple para selectores (usado por módulo Articulos).
  - `save()`: INSERT o UPDATE según `$categoria->id`.
  - Collation explícita `utf8mb4_unicode_ci` en JOINs sobre `codigo_externo`.

### Modelo
- `Categoria.php` — 21 líneas — POPO
  - Campos: `id`, `empresa_id`, `nombre`, `slug`, `descripcion_corta`, `imagen_portada`, `orden_visual`, `activa`, `visible_store`, `articulos_count`, `created_at`, `updated_at`, `deleted_at`.

### Vistas
- `views/index.php` — Listado CRUD con tabla, buscador, filtros, paginación, papelera, acciones masivas.
- `views/crear.php` — Formulario de creación.
- `views/editar.php` — Formulario de edición.
- `views/form_fields.php` — Campos compartidos entre crear y editar.

---

## Rutas / Pantallas

Guard: `AuthService::requireLogin()` (usuario autenticado del backoffice). Área fija: Tiendas.

| Método | URI | Acción |
|--------|-----|--------|
| GET | `/mi-empresa/categorias` | `index` |
| GET | `/mi-empresa/categorias/sugerencias` | `suggestions` (JSON) |
| GET | `/mi-empresa/categorias/crear` | `create` |
| POST | `/mi-empresa/categorias/crear` | `store` |
| GET | `/mi-empresa/categorias/{id}/editar` | `edit` |
| POST | `/mi-empresa/categorias/{id}/editar` | `update` |
| POST | `/mi-empresa/categorias/{id}/copiar` | `copy` (stub) |
| POST | `/mi-empresa/categorias/{id}/eliminar` | `eliminar` |
| POST | `/mi-empresa/categorias/eliminar-masivo` | `eliminarMasivo` |
| POST | `/mi-empresa/categorias/{id}/restore` | `restore` |
| POST | `/mi-empresa/categorias/restore-masivo` | `restoreMasivo` |
| POST | `/mi-empresa/categorias/{id}/force-delete` | `forceDelete` |
| POST | `/mi-empresa/categorias/force-delete-masivo` | `forceDeleteMasivo` |

---

## Tablas / Persistencia

| Tabla | Rol |
|-------|-----|
| `categorias` | Catálogo principal de categorías por empresa |
| `articulo_categoria_map` | Relación artículo-categoría por `articulo_codigo_externo` (JOIN de lectura, no escrito por este módulo) |
| `articulos` | JOIN de lectura para conteo de artículos por categoría |

---

## Dependencias directas

| Dependencia | Tipo | Motivo |
|-------------|------|--------|
| `App\Core\Context::getEmpresaId()` | Core | Aislamiento multiempresa |
| `App\Core\Controller` | Core | Clase base (hereda `handleCrudFilters`) |
| `App\Core\View` | Core | Render de vistas |
| `App\Core\FileCache` | Core | Limpieza de cache de catálogo y categorías |
| `App\Core\Flash` | Core | Mensajes flash |
| `App\Core\AdvancedQueryFilter` | Core | Filtros avanzados CRUD |
| `App\Modules\Auth\AuthService` | Auth | `requireLogin()` en todos los métodos |
| `App\Shared\Services\OperationalAreaService` | Shared | Resolución de `dashboardPath` y `helpPath` |

---

## Dependencias indirectas / Impacto lateral

| Módulo | Cómo consume |
|--------|-------------|
| `Articulos\ArticuloRepository` | Importa `CategoriaRepository` para listado y validación de categorías en formulario Tiendas |
| `Store\Controllers\StoreController` | Instancia `CategoriaRepository` para `findStoreCategoriesWithCounts()` (navegación por categoría en Store público) |

---

## Integraciones involucradas

Ninguna integración externa. Este módulo es local-first sin sincronización con Tango ni sistemas externos.

### FileCache
- Al mutar datos (crear, actualizar, eliminar, restaurar, force-delete), `clearStoreCache()` invalida los prefijos `catalogo_empresa_{id}` y `categorias_store_empresa_{id}`.

---

## Seguridad

### Aislamiento multiempresa
- **Implementado**: toda query usa `empresa_id` como filtro via `Context::getEmpresaId()`. El servicio valida que `empresaId` no sea null antes de operar.

### Permisos / Guards
- **Guard activo**: `AuthService::requireLogin()` en todos los métodos del controlador. Cualquier usuario autenticado del backoffice con acceso al área Tiendas puede operar.
- **No hay guard granular por rol**: no existe verificación de permiso específico (ej. `canManageCategories`). Todo usuario logueado puede crear, editar y eliminar categorías.

### Admin sistema (RXN) vs Admin tenant
- No aplica diferenciación explícita. El módulo opera exclusivamente en contexto de tenant (empresa logueada).

### No mutación por GET
- **Cumplido parcialmente**: todas las mutaciones (crear, eliminar, restore, force-delete) requieren `$_SERVER['REQUEST_METHOD'] === 'POST'` o llegan por POST directo.
- **Excepción**: `copy()` está registrada como POST pero es un stub que no muta.

### Validación server-side
- Nombre obligatorio (validado en `CategoriaService::fillCategoria`).
- Slug generado y deduplicado automáticamente.
- Extensión de imagen validada (jpg, jpeg, png, webp).
- `orden_visual` forzado a `max(0, int)`.
- Checkbox normalizado a 0/1.

### Escape / XSS
- `renderDenied()` usa `htmlspecialchars($message, ENT_QUOTES, 'UTF-8')` para el mensaje de error.
- Las vistas deberían aplicar escape (no verificado en detalle desde este contexto documental).

### Impacto sobre acceso local
- Sin impacto. El módulo no expone rutas públicas. Todas las rutas están detrás de autenticación.

### CSRF
- **No implementado**. Los formularios de crear/editar/eliminar no incluyen token CSRF. Deuda de seguridad activa.

---

## Reglas operativas del módulo

1. **Aislamiento multiempresa obligatorio**: toda query usa `empresa_id`. No existe lectura cruzada.
2. **Slug único por empresa**: generado automáticamente con deduplicación por sufijo numérico.
3. **Soft delete por defecto**: eliminar = `SET deleted_at = NOW()`. Borrado definitivo requiere papelera previa o force-delete.
4. **Imágenes en carpeta dedicada**: `/uploads/empresas/{id}/categorias/` con nombre aleatorio.
5. **Cache invalidado en cada mutación**: `clearStoreCache()` limpia FileCache de catálogo y categorías.
6. **Sugerencias requieren mínimo 2 caracteres**: retorna vacío sin consultar DB si no se cumple.
7. **Conteo de artículos via JOIN**: el conteo en listado y en Store usa JOIN con `articulo_categoria_map` + `articulos` con collation explícita.

---

## Tipo de cambios permitidos (bajo riesgo)

- Agregar o modificar labels, placeholders, campos de texto en las vistas.
- Ajustar límites de paginación.
- Ampliar `buildUiContext()` con nuevas keys de UI.
- Agregar campos opcionales al modelo que no rompan el INSERT/UPDATE existente.

---

## Tipo de cambios sensibles (requieren análisis previo)

- **Modificar el esquema de `categorias`**: impacta Store (navegación por categoría) y Articulos (selector de categorías).
- **Cambiar `findStoreCategoriesWithCounts()`**: rompe la navegación por categoría en el Store público.
- **Cambiar `findSelectableByEmpresaId()`**: rompe el selector de categorías en el formulario de artículos (Tiendas).
- **Cambiar la firma JSON de `/sugerencias`**: rompe autocompletado donde se consuma.
- **Modificar la lógica de slug**: puede causar colisiones o romper URLs públicas del Store que usen slugs de categoría.
- **Cambiar collation en JOINs**: puede causar 0 resultados en el conteo de artículos.

---

## Puntos críticos del código

| Zona | Archivo | Riesgo |
|------|---------|--------|
| `ensureSoftDeleteSchema()` | CategoriaRepository.php | Ejecuta ALTER TABLE en cada request; atrapa `PDOException` silenciosamente |
| `buildSkuJoinCondition()` | CategoriaRepository.php | JOIN con collation explícita; si `codigo_externo` usa otra collation, conteo retorna 0 |
| `storeImageIfPresent()` | CategoriaService.php | Sin validación de tamaño de archivo; solo valida extensión |

---

## No romper

1. **`findStoreCategoriesWithCounts()`**: consumida por `StoreController` para la navegación del Store público.
2. **`findSelectableByEmpresaId()`**: consumida por `ArticuloRepository` / controlador Articulos para el selector de categorías.
3. **Firma JSON de `/sugerencias`**: `{success, data: [{id, label, value, caption}]}`.
4. **Slug como identificador público**: usado en URLs del Store para filtrar por categoría.
5. **Invalidación de FileCache**: si se agrega una nueva mutación, debe llamar `clearStoreCache()`.

---

## Riesgos conocidos

1. **DDL por request**: `ensureSoftDeleteSchema()` ejecuta ALTER TABLE en cada instanciación del repositorio.
2. **Sin CSRF en formularios**: deuda de seguridad activa.
3. **Sin validación de tamaño de imagen**: solo se valida extensión; archivos grandes podrían agotar disco o memoria.
4. **`copy()` no implementado**: stub que muestra flash de error; UX confusa si el botón es visible.
5. **Sin guard granular por rol**: cualquier usuario logueado puede operar sobre categorías.

---

## Checklist post-cambio

- [ ] El listado de categorías carga correctamente (`/mi-empresa/categorias`)
- [ ] Crear una categoría genera slug único y muestra en listado
- [ ] Editar una categoría actualiza nombre, slug e imagen
- [ ] El Store público muestra categorías con conteo de artículos (`/{slug}?categoria=xxx`)
- [ ] El selector de categorías en el formulario de artículos funciona
- [ ] El buscador de sugerencias retorna datos correctos
- [ ] Soft delete, restore y force-delete funcionan individual y masivamente
- [ ] La imagen de portada se sube y reemplaza correctamente

---

## Regla de mantenimiento

Este archivo debe actualizarse si cambian:
- El esquema de `categorias` (columnas, índices)
- Las rutas del módulo
- Los consumidores que leen categorías directamente
- El contrato JSON del endpoint `/sugerencias`
- La lógica de generación de slugs
- La integración con FileCache
