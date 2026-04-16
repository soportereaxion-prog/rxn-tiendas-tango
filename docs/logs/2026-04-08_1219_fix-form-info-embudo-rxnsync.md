# 2026-04-08 1219 — Fix: Botón "i" en form edición, filtro embudo RxnSync, fix push artículos

## Cambios realizados

### 1. Botón "i" de payload en form de edición de Artículos
- **Archivo**: `app/modules/Articulos/views/form.php`
- Agregado `btn-form-info` junto a Push/Pull en el header del formulario
- **Fix crítico**: el JS del form usaba `syncBase + '/push'` (endpoint de RxnSync, incorrecto) — ahora usa `basePath + '/' + id + '/push-tango'` (endpoint de `ArticuloController::pushToTango`)
- Eliminado spin; Push usa `disabled=true/false`; Pull llama a rxn-sync/pull correctamente
- `payloadHtml()` helper integrado en el form (igual al index)
- Eliminado `@keyframes rxn-spin` residual

### 2. Botón "i" de payload en form de edición de Clientes
- **Archivo**: `app/modules/CrmClientes/views/form.php`
- Agregado `btn-form-info` (bi-info-circle) en el grupo de botones del header
- Eliminado spin; `doSync` refactorizado a pasar `btn` en lugar de `iconEl`
- `payloadHtml()` helper integrado

### 3. Botón "i" en tabla de RxnSync (Artículos tab)
- **Archivo**: `app/modules/RxnSync/views/tabs/articulos.php`
- Agregado `btn-payload-info` en el btn-group de cada fila

### 4. Filtro de columna (embudo) en RxnSync — client-side
- **Archivo**: `app/modules/RxnSync/views/index.php`
- Implementado `initColumnFilters()` inline en `initTabControls`
- Funciones: `applyColFilter()` que combina búsqueda global + estado + filtros por columna
- Cada `<th class="rxnsync-sortable">` recibe un `bi-funnel` + popover con operadores:
  contiene / no contiene / empieza con / termina con / igual / distinto
- CSS inyectado dinámicamente (dark-mode compatible vía `var(--bs-body-bg)`)
- Icono se torna `bi-funnel-fill` activo (azul) cuando hay filtro aplicado

### 5. Selector empresa en Config (análisis)
- El código es correcto: la función `populateTangoSelects` genera el fallback estático
- El listado vacío es comportamiento normal de la API: Tango Connect solo expone la empresa del certificado actual
- El ID `351` no está en el catálogo retornado por `getMaestroEmpresas()`
- No es un bug del sistema; es una limitación del endpoint de Tango

## Diagnóstico del error `Unexpected token '<'` en artículos
- **Causa**: `form.php` de artículos enviaba el push a `rxn-sync/push` en vez de `articulos/{id}/push-tango`
- El endpoint `/rxn-sync/push` espera `entidad` como FormData — lo procesaba como sync masivo
- El string de búsqueda de rutas resultaba en un redirect o respuesta HTML → JSON.parse fallaba con `<`
- **Fix**: usar directamente `basePath + '/' + id + '/push-tango'` con `method: POST, X-Requested-With: XMLHttpRequest`

## Impacto
- ✅ Push de artículos desde form de edición: ahora usa endpoint correcto
- ✅ Botón "i" en edición de Artículos y Clientes
- ✅ Filtro de columna (embudo) en RxnSync: Código / Nombre / Estado / Fecha
- ✅ Sin spin en ningún form de edición
