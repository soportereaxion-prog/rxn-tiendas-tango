# MODULE_CONTEXT — CrmClientes

## Nivel de criticidad
ALTO. Es el catálogo comercial de clientes del entorno CRM. De su integridad dependen directamente flujos transaccionales clave como "Pedidos de Servicio" (PDS) y "Presupuestos".

## Propósito
Proveer una caché local (Local-First) de los clientes provenientes de Tango Connect para el entorno CRM, permitiendo operar, buscar clientes y asignar operaciones sin latencia ni dependencia continua de consultas remotas ("lookup remoto").

## Alcance
**QUÉ HACE:**
- Gestiona un ABM unificado para clientes del CRM (aislados del entorno "Tiendas").
- Provee un endpoint interno de autocompletado y sugerencias (`suggestions()`) muy rápido (usado en modales Spotlight de PDS y Presupuestos).
- Sincroniza masivamente desde Tango a través de `TangoService` (usualmente `process=2117`) y pagina el resultado.
- Permite borrado suave (Papelera), eliminación masiva, copiado rápido y purgado total del caché local.
- Persiste identificadores y metadatos comerciales de Tango (Vendedor, Lista de precios, Transporte, Condición Venta) para tener pre-completamiento listo en transacciones.

**QUÉ NO HACE:**
- No comparte base ni tablas con `clientes_web`, manteniendo la separación explícita B2B vs Operativo.
- No envía automáticamente ediciones locales hacia Tango Connect (Push de cliente); la sincronización actual es predominantemente PULL (espejo unidireccional de resguardo).

## Piezas principales
- **Controladores:** `CrmClienteController` (Maneja las vistas, filtros visuales genéricos, vistas transaccionales, y el Json de sugerencias).
- **Servicios:** `CrmClienteSyncService` (Encapsula el ciclo de paginación contra Connect, la bitácora vía `TangoSyncLogRepository` y el mapeo de keys del payload difuso de Tango).
- **Vistas:** `views/index.php` (Listado y Data table), `views/form.php` (Formulario simple de edición rápida).
- **Rutas/Pantallas:** `/mi-empresa/crm/clientes/...`
- **Tablas/Persistencia:** `crm_clientes`.
- **Endpoints externos:** No expone hacia afuera; las consultas son in-house exclusivas en el footprint de session de la empresa.

## Dependencias directas
- `App\Modules\Tango\TangoService` enfocado por entorno (Llamada específica: `TangoService::forCrm()`).
- `App\Modules\Tango\Repositories\TangoSyncLogRepository` (Utilizado para asentar bitácoras de éxito o errores al procesar páginas).
- Módulos núcleo (`Context`, `AuthService`, `OperationalAreaService` para setear la interfaz).

## Dependencias indirectas / impacto lateral
- **CrmPresupuestos y CrmPedidosServicio (PDS):** Ambos módulos leen activamente sobre la tabla persistida localmente. Si cambian o se eliminan los identificadores de cabecera como `id_gva14_tango` o `codigo_tango`, fallaría el armado comercial del envío a Connect, generando bloqueos gravísimos.

## Integraciones involucradas (si aplica)
- **Tango Connect:** Fetch asíncrono puro. Maneja payloads variables utilizando un buscador heurístico de *Keys* dentro del DataSet proveniente de Axoft (Ej: puede venir `CUIL`, `CUIT` o `NRO_DOC` según subida previa).

## Seguridad Base (Política de Implementación)
- **Aislamiento Multiempresa**: OBLIGATORIO. Todas las consultas y mutaciones en base de datos deben estar atadas al `Context::getEmpresaId()`. Nunca se debe permitir el acceso a clientes de otro tenant.
- **Permisos / Guards**: El controlador usa `AuthService::requireLogin()` en sus acciones. No se observa un guard granular adicional por rol dentro del módulo; el alcance queda contenido por el tenant activo.
- **Mutación**: Prohibida la mutación de estado o borrados a través de peticiones HTTP GET. Todo cambio (Soft-delete, purgado, importación) se realiza mediante POST/PUT/DELETE.
- **Validación Server-Side**: Todas las entradas deben ser validadas y sanitizadas en el controlador antes de llegar a la capa de servicios o repositorios.
- **Escape Seguro (XSS)**: Las vistas (`views/index.php`, `views/form.php`) deben escapar la salida de datos de cliente, especialmente en campos textuales libres provenientes de sincronizaciones remotas.
- **Token CSRF**: No se observó validación CSRF explícita en formularios ni acciones AJAX del módulo. Debe tratarse como deuda de seguridad a revisar, no como control ya implementado.
- **Acceso Local**: Los datos cacheados son accesibles únicamente bajo el contexto seguro del session token activo de la empresa local.

## Reglas operativas del módulo
- TODO CRUD y lectura en base TIENE que usar filtros estrictos de `empresa_id` de forma preventiva. La mezcla de tenants es catastrófica.
- Los campos pre-cacheables de Tango (`id_gva23_tango`, etc.) se guardan usando validaciones defensivas de casteo (ej: `nullableInt`) para evitar roturas locales frente a payloads extraños de JSON nativo.
- **Persistencia de filtros de listado**: el input de búsqueda F3 (`search`), el campo de búsqueda (`field`), la cantidad por página (`limit`), el filtro de estado de negocio (`estado`), el filtro de categoría (`categoria_id`, donde aplique) y los filtros Motor BD (`f[campo][op|val]`) se persisten automáticamente en `localStorage` scopeados por `pathname + empresa_id` via `public/js/rxn-filter-persistence.js` (cargado inline desde `admin_layout.php`). Al volver al listado, los filtros se restauran y se reinicia en la primera página. `status` (activos/papelera), `sort`, `dir` y `area` quedan fuera por ser navegación u orden. Para limpiarlos: `?reset_filters=1` (lo dispara `rxn-advanced-filters.js` al borrar BD) o `window.rxnFilterPersistence.clear()`. Los filtros "locales" (selección por columna) siguen viviendo en `sessionStorage` via `rxn-advanced-filters.js` con key `rxn_lf::`.

## Tipo de cambios permitidos
- Agregar visuales y mejoras UX al Datatable.
- Añadir nuevos *aliases* válidos en la lista de macheo del `firstNonEmpty()` de `CrmClienteSyncService` para soportar APIs "rebeldes" de perfiles empresariales desactualizados en Tango Connect.
- Expandir variables y lógica en los buscadores visuales o filters `advancedQueryFilter`.

## Tipo de cambios sensibles
- Cualquier alteración que rompa la idempotencia en `upsertFromTango()`. Ese motor asegura que correr Sincronizar muchas veces actualice sin duplicar; equivocarse en validación de `id_gva14_tango` u omitir campos colapsaría la DB.
- Cambios sobre la forma en que el Soft-Delete marca registros en el repositorio. Un montón de SQL queries operativas y sugerencias omiten si flaggean "deleted_at".

## Puntos críticos del código
- `CrmClienteSyncService::extractItems(...)`: Al tener Tango Connect comportamientos mutables (algunos perfiles envían `.list`, otros `.data`), esta capa actúa como traductor universal. Romper este normalizador mata la importación.
- `CrmClienteRepository->ensureSchema()`: Existe una capa *On-The-Fly* dentro del constructor de Repositorio que inyecta columnas de forma silenciosa para pre-compatibilidad. Alerta: Genera un parche dinámico de estructura que puede llegar a entrar en choque silencioso con una migración hardcodeada futura.
- `CrmClienteController->suggestions()`: Método que debe retornar en ms (< 50ms) dado a que se bindea al teclado en la App Frontend (Spotlight).

## No romper
- **Contrato JSON interno**: El return array `['id' => ... , 'label' => ... , 'value' => ... , 'caption' => ...]` en el Controller DEBE mantenerse estrictamente igual; de esto depende el UI Spotlight de PDS y Presupuestos.

## Riesgos conocidos
- **Deuda Técnica Autogeneracional:** La dependencia sobre `ensureSchema()` en el Repository es pragmática en la base actual pero anti-patrón de arquitecturas duras. A futuro lo ideal es migrar por completo hacia el engine de "MigrationRunner".
- **Dependencia Semántica Lassa:** La lectura del "Activo/Desactivo" desde Tango tiene tantas derivaciones lógicas ('HABILITADO', 'S', 1, 'TRUE') que una versión nueva de API no contemplada en el normalizador de booleanos puede dar de baja masivamente clientes transaccionales.

## Checklist post-cambio
- [ ] La pestaña base `/mi-empresa/crm/clientes` arranca y lista la misma empresa (no tira loop ni cross-tenant match).
- [ ] La búsqueda desde Spotlight Presupuestos / PDS sigue devolviendo sugerencias en ms y llenando los inputs sin romper handlers de JS.
- [ ] Re-Tirar Sincronización Masiva actualiza `fecha_ultima_sync` sin incrementar el "COUNT(*)" si no hay clientes nuevos (control Upsert).

## Documentación relacionada
- `docs/estado/current.md` para visualizar el Footprint histórico entre CRM - Tiendas y perfiles unificados de cabecera.
- Módulos del Core/Shared (`docs/logs` en caso de intervenir con el AdvancedQueryFilter o variables globales de UI).

## Regla de mantenimiento
Este archivo debe actualizarse si cambian:
- El alcance (`QUÉ HACE` / `QUÉ NO HACE`).
- El modelo de persistencia `crm_clientes`.
- Las dependencias y APIs transversales que dependan de este caché (por ejemplo si se crea `OrdenDeReparacion` y su Spotlight consume éste cliente).
- Integraciones: Si TangoService pasa a sincronizar por un endpoint diferencial dedicado.
