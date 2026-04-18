# MODULE_CONTEXT — RxnSync

## Nivel de criticidad
ALTO

Este módulo impacta directamente en:
- integraciones externas (Tango Connect)
- consistencia de datos CRM
- sincronización bidireccional

Cualquier cambio debe considerarse sensible.

## Propósito
El módulo **RxnSync** es la consola centralizada de sincronización bidireccional entre las entidades locales del entorno CRM y la API de Axioma/Nexo (Tango Connect). Su función es consolidar la vinculación de registros, permitir la auditoría de estado y ejecutar acciones de *Push* (enviar a Tango) y *Pull* (traer de Tango) para mantener la coherencia de los catálogos.

Además expone el **Circuito de Sync** — un panel visual que indica al operador si las precondiciones están cumplidas (artículos vinculados, listas de precios configuradas, depósito configurado) antes de ejecutar sincronizaciones masivas de Precios y Stock.

## Alcance
- **Sí hace**:
  - Sincronización masiva e individual de `Clientes CRM` y `Artículos CRM` (Import + Audit o las variantes separadas). Ver sección "Flujos de sync" más abajo.
  - Sincronización de **Catálogos Comerciales CRM** (condiciones de venta, listas de precio, vendedores, transportes, depósitos, clasificaciones PDS) via `CommercialCatalogSyncService` que habita en `Services/`. Prerequisito para que `Sync Precios` y `Sync Stock` funcionen en CRM. Las clasificaciones PDS (process 326 de Tango, `tipo='clasificacion_pds'`) se agregaron en release 1.13.1 para reemplazar el campo raw `clasificaciones_pds_raw` que vivía en `empresa_config_crm` como salida temporal.
  - Inicia sincronizaciones de Precios y Stock redirigiendo al `TangoSyncController` con `?return=` para volver al módulo.
  - Realiza la vinculación blanda ("Match Suave") basada en SKU o código, y conserva el historial de transacciones mediante un pivot `rxn_sync_status` + log `rxn_sync_log`.
- **No hace**: No sincroniza pedidos transaccionales ni configuraciones maestras. No realiza sincronización desatendida/automática (es *on demand* operada por el usuario). No sincroniza clientes hacia el entorno de *Tiendas B2C/B2B* (no hay endpoint `/mi-empresa/sync/clientes` — solo existe en CRM).

## Bifurcación CRM vs Tiendas (importante)

El circuito visual del módulo es distinto según el área:

### Área Tiendas (`/mi-empresa/rxn-sync`)
Circuito: `1. Artículos → 2. Precios → 3. Stock`.
- **Precios** se basa en los selectores planos `lista_precio_1` y `lista_precio_2` de `empresa_config` (form de `/mi-empresa/configuracion`). Actualiza columnas planas `precio_lista_1` / `precio_lista_2` en `crm_articulos`. Máximo 2 listas.
- **Stock** se basa en el selector `deposito_codigo` de `empresa_config`. Actualiza columna plana `stock_actual` en `crm_articulos`. Un solo depósito de referencia.
- **Catálogos comerciales NO aplican.** El botón "Sync Catálogos" no se renderiza.

### Área CRM (`/mi-empresa/crm/rxn-sync`)
Circuito: `1. Artículos → 2. Catálogos → 3. Precios → 4. Stock`.
- **Catálogos** es un paso obligatorio explícito. Trae listas, depósitos, condiciones, vendedores, transportes desde Tango y los persiste en `crm_catalogo_comercial_items`. Se habilita apenas hay credenciales Tango configuradas.
- **Precios** recorre TODAS las listas del catálogo (no 2) y puebla la tabla normalizada `crm_articulo_precios`.
- **Stock** recorre TODOS los depósitos del catálogo y puebla la tabla normalizada `crm_articulo_stocks`. No requiere `deposito_codigo` en el config (ese campo está oculto en el form CRM).
- Los selectores `lista_precio_1/2` y `deposito_codigo` del `empresa_config_crm` NO se usan (se ocultan en el form del área CRM desde release 1.12.5).

**Precondiciones calculadas en `RxnSyncController::index()` por área**:
- Tiendas: `listasReady = !empty(lista_precio_1) || !empty(lista_precio_2)`; `depositoReady = !empty(deposito_codigo)`.
- CRM: `listasReady = countByType(empresaId, 'lista_precio') > 0`; `depositoReady = countByType(empresaId, 'deposito') > 0`.

## Piezas principales

### Controlador
`app/modules/RxnSync/RxnSyncController.php`

Acciones principales:
- `index()` — Renderiza la consola con el `syncCircuit` array (estado de precondiciones)
- `listClientes()` — AJAX, carga parcial del tab de clientes
- `listArticulos()` — AJAX, carga parcial del tab de artículos
- `auditarArticulos()` / `auditarClientes()` — AJAX POST, **solo auditoría** (match suave local vs Tango). No trae datos nuevos.
- `syncPullArticulos()` / `syncPullClientes()` — AJAX POST, flujo completo **Import + Audit** en cadena (release 1.12.4). Es lo que dispara el botón principal "Sincronizar desde Tango".
- `syncCatalogos()` — AJAX POST, sincroniza catálogos comerciales CRM via `CommercialCatalogSyncService` (release 1.12.5). Solo CRM.
- `pushToTango()` / `pullSingle()` — AJAX POST, operaciones individuales
- `pushMasivo()` / `pullMasivo()` — AJAX POST, operaciones masivas seleccionadas
- `getPayload()` — AJAX GET, solo lectura del último snapshot de sync

### Servicios
- `app/modules/RxnSync/RxnSyncService.php` — lógica de negocio de audit/push/pull de clientes y artículos, mapeos a DTOs de Tango y conexión real a `TangoService`.
- `app/modules/RxnSync/Services/CommercialCatalogSyncService.php` — sync de catálogos comerciales CRM (condiciones/listas/vendedores/transportes/depósitos). Se movió acá desde `CrmPresupuestos` en release 1.12.5 porque semánticamente es responsabilidad de la consola de sync, no de un módulo de presentación. Persiste en `crm_catalogo_comercial_items` (tabla cuyo repo sigue en `CrmPresupuestos\CommercialCatalogRepository` por consumo directo del form).

### Sync de Precios y Stock (delegado)
Las sincronizaciones de precios y stock NO pasan por `RxnSyncController` sino por `TangoSyncController`:
- `GET /mi-empresa/sync/precios?return=/mi-empresa/rxn-sync` → `TangoSyncController::syncPrecios()`
- `GET /mi-empresa/sync/stock?return=/mi-empresa/rxn-sync` → `TangoSyncController::syncStock()`
- Luego de ejecutar, `Flash::set()` guarda stats en sesión y redirige de vuelta a RXN-Sync
- `RxnSyncController::index()` consume `Flash::get()` y lo renderiza en la vista

El parámetro `?return=` tiene validación server-side: solo acepta rutas que empiecen con `/mi-empresa/` (anti open-redirect).

### Vistas
- `app/modules/RxnSync/views/index.php` — Consola centralizada: Circuito visual + tabs AJAX + Flash
- `app/modules/RxnSync/views/tabs/articulos.php` — Panel de artículos (Motor BD filter + embudo inline)
- `app/modules/RxnSync/views/tabs/clientes.php` — Panel de clientes (solo embudo inline)

### Persistencia involucrada
- Locales: `crm_articulos`, `crm_clientes`
- Pivot y Logs: `rxn_sync_status` (estado actual de vinculación), `rxn_sync_log` (historial de eventos)

### Endpoints externos
- Process `87` — Artículos
- Process `2117` — Clientes
- Process `20091` — Precios (via `TangoSyncController`)
- Process `17668` — Stock en tiempo real (via `TangoSyncController`)

---

## Arquitectura de la consola (index.php)

### Tabs AJAX
Los tabs de Clientes y Artículos se cargan via AJAX con `loadTabContent(tabKey, url)`.
El HTML parcial inyectado incluye controles de filtro propios de cada tab.
Tras cada carga AJAX, se reinicializan los filtros con `rxnFiltersInit()` (de `rxn-advanced-filters.js`).

### Persistencia de filtros entre tabs
El estado de filtros se mantiene en variables de outer scope (fuera de los handlers de tab):

```javascript
var tabColFilters  = { clientes: {}, articulos: {} };   // Embudo inline por columna
var tabSearchState = { clientes: { search: '', estado: '' }, articulos: { search: '', estado: '' } };
var tabBdParams    = { clientes: null, articulos: null }; // URLSearchParams del Motor BD
```

Al cambiar de tab:
1. Se guarda `window.location.search` en `tabBdParams[leavingTabKey]`
2. Al cargar el nuevo tab se restauran los BD params en la URL de fetch
3. Tras la carga AJAX, `initTabControls()` restaura los valores en los inputs y re-aplica los filtros de embudo

### Motor BD (solo Artículos)
El tab de artículos usa `rxn-filter-col` + `data-ajax-url` en la tabla para filtros server-side.
El estado persiste via URLSearchParams en `tabBdParams['articulos']`.
Se activa con `rxnFiltersInit()` después de cada carga de tab.

### Embudo inline (Clientes y Artículos)
Filtra client-side por columna. Estado en `tabColFilters[tabKey]`.
Se restaura tras cambio de tab via `initTabControls()` que re-aplica `applyColFilter()`.

### Circuito de Sync (syncCircuit)
Array generado en `RxnSyncController::index()` con estado de precondiciones:
```php
'syncCircuit' => [
    'articulos_total'     => int,
    'articulos_vinculados'=> int,
    'articulos_ready'     => bool,
    'listas_ready'        => bool,
    'deposito_ready'      => bool,
    'precios_ready'       => bool,  // articulos_ready && listas_ready
    'stock_ready'         => bool,  // articulos_ready && deposito_ready
    'config_path'         => string,
    'sync_articulos_path' => string,
    'sync_precios_path'   => string, // incluye ?return= para volver al módulo
    'sync_stock_path'     => string, // incluye ?return= para volver al módulo
]
```

### Flash de stats post-sync
Después de un Sync Precios o Sync Stock, `TangoSyncController` guarda stats con `Flash::set()`.
`RxnSyncController::index()` consume `Flash::get()` y pasa los datos a la vista.
La vista renderiza el alert con las métricas (recibidos, actualizados, omitidos, sin_match).

---

## Flujo de syncPrecios / syncStock (TangoSyncService)

La sincronización itera en paginación completa (do-while con páginas de 500 registros):

1. **Fetch paginado**: `fetchPrecios($page, 500)` / `fetchStock($page, 500)` — pageSize fijo en 500, independiente del `syncAmount` (que es para artículos y puede ser pequeño).
2. **Envelope parsing**: soporta `resultData.list`, `Data`, `data` — en ese orden.
3. **Filtrado de lista/depósito**: Precios → macheo por `NRO_DE_LIS` (normalizado con trim). Stock → macheo por `ID_STA22` (normalizado con trim) contra `deposito_codigo` configurado.
4. **Match local por SKU**: `articuloRepo->updatePrecioListas()` / `updateStock()` — SQL Update silencioso, afecta solo artículos pre-existentes.
5. **Límite de seguridad**: máximo 100 páginas (50.000 registros) por ejecución.

**Importante**: `cantidad_articulos_sync` en EmpresaConfig NO aplica a precios/stock — ese campo controla el batch de artículos únicamente. Usar syncAmount para precios generaba el bug de recibir solo N registros.

---

## Dependencias directas
- **TangoService / TangoApiClient / TangoSyncService**: conexión remota, fetch y sync masivo.
- **EmpresaConfigRepository**: fuente de precondiciones operativas del circuito visual.
- **Context / Database / View / Flash**: Core del framework.
- **`rxn-advanced-filters.js`**: provee `rxnFiltersInit()` y el Motor BD.
- **`docs/whitelist_definition.md`**: define largos y campos permitidos para Push.

## Dependencias indirectas / impacto lateral
- **Artículos CRM** y **Clientes CRM** dependen de este módulo para sus flujos de sync individual en sus ABM.
- La metadata en `rxn_sync_status` impacta la visual en listados del CRM si consumen dicha tabla para mostrar estado de vinculación.
- Un Pull mal ejecutado puede pisar trabajo hecho en el CRM en los ABM locales.

---

## Reglas operativas del módulo
- **Shadow Copy Estricta**: Nunca enviar un JSON construido desde cero al endpoint de Update (PUT). Siempre: Leer → Sobrescribir DTO local → Enviar.
- **Match Suave**: Si un registro local no tiene `tango_id` en el pivot, busca por `codigo_externo` (artículo) o `codigo_tango` (cliente).
- **Time Limits largos**: Procesos masivos usan `set_time_limit(180)` y `120` por latencia de Connect.
- **Pagesize independiente**: precios y stock usan `pageSize = 500`, no `syncAmount`.
- **Persistencia de filtros (caso especial)**: RxnSync **está explícitamente excluido** de la persistencia global de filtros (`public/js/rxn-filter-persistence.js`) porque gestiona su propio estado en variables JS de outer scope (`tabColFilters`, `tabSearchState`, `tabBdParams`) que viven mientras dura la página y se sincronizan con los tabs AJAX. El script global verifica `EXCLUDED_PATH_PREFIXES` con `/mi-empresa/rxn-sync` y `/mi-empresa/crm/rxn-sync` al inicio y sale sin tocar nada — así evita pisar los filtros que el módulo maneja por su cuenta. Si se mueven las rutas del módulo, actualizar también la lista de exclusión en el JS.

## Seguridad

### Aislamiento multiempresa
Todas las queries y operaciones de sync filtran por `empresa_id` del contexto de sesión. No hay lectura cruzada.

### Permisos / Guards
`RxnSyncController` usa `AuthService::requireLogin()` en todos los endpoints.

### Anti open-redirect
El parámetro `?return=` en syncPrecios/syncStock solo acepta rutas con prefijo `/mi-empresa/`.

### Mutación por método
- Push, pull, auditorías masivas y toda mutación de datos → **POST** (AJAX).
- `getPayload()` → **GET**, solo lectura.

### Validación server-side
- Los payloads de Tango se validan defensivamente.
- La whitelist de campos para Push protege datos sistémicos del ERP.
- Los largos se truncan con `mb_substr` antes de enviar a Connect.

### CSRF
Sin validación de token CSRF en endpoints AJAX. Deuda de seguridad activa.

---

## No romper
- **Mecanismo de Shadow Copy**: Protege campos read-only de Tango Connect (datos fiscales del cliente en ERP).
- **Mapeos parciales por mb_substr**: Campos hacia Tango tienen largos estrictos en `RxnSyncService::pushToTangoByLocalId`.
- **Aislamiento por `empresa_id`**: Nunca hacer queries sin filtrar por contexto multitenant.
- **Persistencia de filtros**: `tabColFilters`, `tabSearchState`, `tabBdParams` son variables de outer scope — no mover a inner scope de handlers.

## Riesgos conocidos
- *Límite de Paginación en Match Suave*: `resolveTangoIdBySku` recorre hasta `pageIndex = 10` con `pageSize = 500` (techo duro de 11 páginas). Catálogos muy grandes pueden quedar sin resolver.
- *Auditorías Masivas Limitadas a Primera Página*: `auditarArticulos()` y `auditarClientes()` consumen una sola página remota (`pageSize = 500` sin iterar). En tenants con más de 500 artículos/clientes, puede haber falsos pendientes que el Pull individual sí resuelve.
- *Timeouts en Masivos*: Un request con muchos registros en backend de hosting normal puede dropearse antes de agotar todos los registros, a pesar del `set_time_limit()`.

## Checklist post-cambio
- [ ] Ejecutar "Push individual" a entidad existente para confirmar que el JSON enviado contiene metadata completa y no explota.
- [ ] Validar que AJAX devuelva JSON correcto y no Warnings PHP escondidos.
- [ ] Probar "Auditoría de Artículos/Clientes" para re-validar el Pivot suave.
- [ ] Probar Sync Precios y verificar que `recibidos` > 3 y `actualizados` corresponde al total de vinculados.
- [ ] Probar Sync Stock y verificar cobertura total.

## Documentación relacionada
- `docs/whitelist_definition.md`
- `docs/architecture.md` (Patrón Local-First)
- `app/modules/Tango/MODULE_CONTEXT.md`

## Tipo de cambios permitidos
- Ajustes de UI (bajo riesgo)
- Ajustes de logging o auditoría
- Correcciones puntuales de mapeo (validadas)

## Tipo de cambios sensibles
- Modificación de payload hacia Tango
- Cambios en lógica de Shadow Copy
- Cambios en Match Suave
- Cambios en paginación o lookup remoto
- Cambios en el mecanismo de persistencia de filtros entre tabs
