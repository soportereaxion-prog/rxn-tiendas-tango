# 2026-04-08 — CORS Fix Definitivo + CRUD Masivo Completo

## Qué se hizo

### 1. CORS Fix — Root Cause y Solución Definitiva

**Causa raíz identificada**: El error "CORS/Timeout" en Configuración → Tango Connect no era un error de CORS real. Era Apache matando el proceso PHP porque el endpoint monolítico `/tango-metadata` encadenaba **4 llamadas cURL a Tango** en un solo request PHP (~10-15s total). Apache TimeOut (típicamente 60s, pero en producción puede ser menos) enviaba un `RST TCP` antes de que PHP terminara de escribir el JSON, resultando en respuesta truncada → JS catch → mensaje de "CORS".

**Por qué `set_time_limit(120)` no funcionó**: Controla el timeout de PHP, no el de Apache. Son independientes.

**Solución**: Se desacoplaron los 4 catálogos en **endpoints atómicos independientes**:
- `POST /mi-empresa/configuracion/tango-empresas` → solo `getMaestroEmpresas()` (~2-3s)
- `POST /mi-empresa/configuracion/tango-listas` → solo `getMaestroListasPrecio()` (~2-3s)
- `POST /mi-empresa/configuracion/tango-depositos` → solo `getMaestroDepositos()` (~2-3s)
- `POST /mi-empresa/configuracion/tango-perfiles` → solo `getPerfilesPedidos()` (~2-3s)

Ambos contextos (CRM y Tiendas) tienen sus 8 rutas registradas.

El JS usa `Promise.allSettled()` para ejecutarlos en paralelo con feedback visual "Cargando catálogos... 1/4, 2/4...".

### 2. Nuevo endpoint `pullSingle`

- `POST /mi-empresa/rxn-sync/pull` y `POST /mi-empresa/crm/rxn-sync/pull`
- Recibe `id` (local ID) y `entidad` (`articulo` | `cliente`)
- Delega a `RxnSyncService::pullFromTangoByLocalId()`

### 3. RxnSync Tabs — Búsqueda/Filtros/Orden client-side

Los tabs de Artículos y Clientes en la consola RxnSync ahora tienen:
- **Búsqueda de texto** en tiempo real (responde a F3 y `/` via `[data-search-input]`)
- **Filtro por estado** (Todos / Vinculado / Pendiente / Conflicto)
- **Ordenamiento de columnas** clickeable con íconos ▲/▼
- **Botones Push/Pull por fila** con `disabled` según estado

### 4. Botones Push/Pull en CRUDs

**Artículos (Tiendas y CRM)**:
- `index.php`: botones Pull (↓) junto a Push (↑) en cada fila con SKU
- `form.php`: botones Push/Pull en el header, condicionales a `$showSyncActions && codigo_externo`

**CrmClientes**:
- `index.php`: botones Push (↑) y Pull (↓) en cada fila, junto a Editar
- `form.php`: botones Push/Pull en el header

Todos usan `rxnConfirm()` para confirmación vía modal.

### 5. Fix: `pushToTango` controller

Se cambió de `service->pushToTango($pivotId)` a `service->pushToTangoByLocalId($localId)` ya que los botones de los CRUDs envían el ID local del registro, no el pivot ID de sincronización.

## Impacto

- ✅ El error "CORS/Timeout" en Configuración debe resolverse completamente
- ✅ RxnSync CRUDs ahora tienen búsqueda, filtros y ordenamiento
- ✅ Push/Pull disponibles en Artículos y CrmClientes (listado + edición)
- ⚠️ La consola RxnSync del tab aún usa el endpoint `/push` de la consola central para acciones masivas — no afectado

## Decisiones

- Se mantuvo el endpoint monolítico `getConnectTangoMetadata` como `@deprecated` para compatibilidad
- `pullSingle` reutiliza `pullFromTangoByLocalId` que ya existía en el Service
- El botón Pull en el listado de Artículos queda habilitado siempre (no condicionado a estado pivot) para máxima usabilidad
