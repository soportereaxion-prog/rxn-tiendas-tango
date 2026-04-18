# Release 1.15.0 — Sync manual de estados de pedidos Tango + skip confirm en flujo completo

**Fecha**: 2026-04-18
**Build**: 20260418.4
**Tema**: Visibilidad del ciclo de vida de los PDS en Tango + fricción cero al cerrar forms ya completados.

## Qué se hizo

### Fase 1 — Probe del endpoint de pedidos Tango Connect

Charly pidió sumar sync de pedidos a RxnSync para ver cuáles fueron anulados (y de paso: aprobados / cumplidos / cerrados). Antes de diseñar, probé el endpoint con un ID concreto que él anuló (26576 → X0065200000984) usando un script nuevo:

- `tools/probe_tango_pedidos.php` — llama `GetById?process=19845&id=26576` y también `Get?process=19845` (listado paginado).

**Hallazgos clave**:
- El `id` que acepta `GetById?process=19845` es **`ID_GVA21`** (NO `ID_NOTA`). El ID_NOTA identifica notas adjuntas al pedido, un pedido puede tener varias. Charly había dicho que yo tenía que usar `ID_NOTA`, pero el probe demostró que ID_GVA21 es el correcto — y su ID 26576 era justo un ID_GVA21.
- Campo **`ESTADO`** es numérico. Muestra de 50 pedidos dio distribución 4x30 / 3x17 / 5x2 / 2x1.
- Los 4 estados (confirmados por Charly): **2=Aprobado**, **3=Cumplido (facturado)**, **4=Cerrado**, **5=Anulado**.
- El schema del listado `Get?process=19845` es distinto al del `GetById` — trae las mismas claves pero en otro shape (más orientado a reporting). Para sync masivo conviene paginar el listado (500 por request) en lugar de hacer N `GetById`.

### Fase 2 — Modelo de datos

Migración `2026_04_18_add_tango_estado_to_crm_pedidos_servicio.php` agrega a `crm_pedidos_servicio`:

| Columna | Tipo | Uso |
|---|---|---|
| `tango_id_gva21` | INT | Clave de match con Tango (con índice `empresa_id + tango_id_gva21`) |
| `tango_nro_pedido` | VARCHAR(30) | El "X006..." legible |
| `tango_estado` | TINYINT | 2/3/4/5 |
| `tango_estado_sync_at` | DATETIME | Última vez que consultamos el estado |

Backfill desde el JSON `tango_sync_response` ya guardado, con `JSON_EXTRACT` sobre tres convenciones (`$.data.value.ID_GVA21`, `$.value.ID_GVA21`, `$.ID_GVA21`) — contempla variantes históricas del wrapper.

### Fase 3 — Helper de estados

`App\Modules\CrmPedidosServicio\TangoPedidoEstado` con constants y un `meta(?int)` que devuelve `{code, label, color, icon}`. Sin este helper el mapping se esparciría por 3 vistas distintas — centralizamos en un único punto.

### Fase 4 — Sync en `RxnSyncService`

- `syncPedidosEstados(int $empresaId)`: pagina `Get?process=19845` con `pageSize=500` y hasta 50 páginas, con circuit breaker por `firstId` repetido (patrón ya usado en `resolveTangoIdBySku`). Arma un map por `ID_GVA21` y hace update masivo.
- `syncPedidoEstadoByLocalId(int, int)`: pull individual con `GetById?process=19845&id=ID_GVA21`. Útil como botón "refrescar" por fila.
- `getPedidosSyncList(int, array)`: query para el tab (solo PDS con `tango_id_gva21 IS NOT NULL`).

### Fase 5 — Controller + rutas

`RxnSyncController` expone `listPedidos()`, `syncPedidosEstados()` (bulk AJAX) y `syncPedidoEstado()` (individual AJAX). Rutas nuevas solo en CRM (PDS es exclusivo de esa área):

- `GET  /mi-empresa/crm/rxn-sync/pedidos/list`
- `POST /mi-empresa/crm/rxn-sync/sync-pedidos-estados`
- `POST /mi-empresa/crm/rxn-sync/sync-pedido-estado`

### Fase 6 — Vista del tab

`app/modules/RxnSync/views/tabs/pedidos.php` es **auto-contenido**: trae su propio `<script>` con filtro por texto + filtro por estado. No se engancha al framework de sort/paginación/bulk-selection del `index.php` porque pedidos es **read-only** — no hay push, no hay bulk push/pull, no hay filtros Motor BD.

### Fase 7 — Integración al `index.php` de RxnSync

Modificaciones mínimas, quirúrgicas:

- Tab button + pane condicionales `if ($isCrm)`.
- `pageState`, `tabColFilters`, `tabSearchState`, `tabBdParams` incluyen clave `pedidos`.
- `getActiveTabKey()` mapea `entidad=pedido → 'pedidos'`.
- `initTabControls()` early-return cuando `entidad === 'pedido'` (el tab ya se auto-inicializa).
- `runSyncFullTab()` despacha al endpoint correcto según entidad.
- `auditLabel` dice "Sincronizar Estados de Pedidos" para el tab Pedidos.
- Botones "Solo importar" y "Solo auditar" se ocultan cuando el tab es Pedidos.
- Event delegation para `.btn-sync-pedido-row` en `#syncTabsContent`.

### Fase 8 — Badge en el listado de PDS

`app/modules/CrmPedidosServicio/views/index.php`: el badge de `nro_pedido` que antes era siempre verde (`bg-success`) ahora usa `TangoPedidoEstado::meta()` → color + ícono + label dinámicos. El tooltip informa nro pedido, estado legible y fecha de última sync. Al cargar el listado sin haber corrido sync todavía, el badge muestra "Sin sync" con color light.

### Fase 9 — Persistencia al crear el PDS

`PedidoServicioRepository::markAsSentToTango()` extendido con `?int $tangoIdGva21`. Al crear un PDS y recibir el response de Tango, además del JSON completo se persisten las 4 columnas denormalizadas — así el badge aparece coherente desde el primer envío sin esperar al primer pull.

### Fase 10 — Skip del confirm "¿Salir sin guardar?"

Tema independiente que quedó pendiente de la release 1.14.1: el confirm de salida debe saltearse cuando el flujo está completo (Tango + mail). Implementado en ambos forms (PDS y Presupuestos) con el mismo patrón:

1. El `<form>` expone dos `data-attrs` desde PHP: `data-tango-sent` y `data-mail-sent`.
2. En el JS del form hay un helper `isFlowCompleted()` que los lee.
3. En los listeners de Escape y click del botón "Volver", si `isFlowCompleted()` es true → salir sin preguntar.

Los estados parciales (solo Tango, solo mail, nada) siguen mostrando el confirm como protección contra pérdida accidental de cambios.

### Fase 11 — Ritual Engram obligatorio en CLAUDE.md

Charly reportó que no estaba usando Engram lo suficiente. La sección `### CRÍTICO — Uso de Engram` se reescribió como `### CRÍTICO — Ritual Engram obligatorio` con 3 momentos explícitos:

- 🟢 **Apertura**: `mem_context` siempre, `mem_search` antes de responder si el prompt referencia módulos/features.
- 🟡 **Durante**: `mem_save` proactivo después de cada decisión / bugfix / descubrimiento / convención.
- 🔴 **Cierre**: `mem_session_summary` como **paso 0** obligatorio del cierre, al mismo nivel que commit, versionado y OTA. Si se cierra sin llamar esto, la próxima sesión arranca ciega.

Template del summary explícito en la sección.

## Por qué

- **Sync de pedidos**: Charly necesita visibilidad del estado real de los PDS en Tango, especialmente los anulados. Ver un PDS "activo" en el listado cuando en Tango ya fue anulado es una fuente de errores operativos.
- **Pull manual, no cron**: decisión explícita de Charly — no quiere automatización prematura. La automatización eventual será con n8n.
- **Paginación en lugar de N GetById**: 1 request por cada 500 pedidos vs 1 request por pedido. Para una empresa con 300 PDS activos: 1 call vs 300 calls.
- **Skip del confirm**: reduce fricción en el caso feliz (PDS/Presupuesto cerrado correctamente) sin perder la protección cuando hay cambios reales.
- **Ritual Engram**: reconocimiento honesto de que no estaba usando la memoria persistente — algo que Charly ya había formalizado. Se promueve a ritual de cierre obligatorio.

## Impacto

- Ningún pedido pre-existente queda roto: el backfill extrae ID_GVA21 del JSON ya guardado, así que apenas se corre el primer sync masivo todos los PDS con `tango_id_gva21` tendrán su estado poblado.
- Los pedidos que nunca se enviaron a Tango (solo guardados localmente) no aparecen en el tab — el filtro es `WHERE tango_id_gva21 IS NOT NULL`.
- El confirm de salida sigue apareciendo en todos los casos "normales" (creación nueva, edición a medias, sin mail, etc.). Solo se saltea en el caso específico del flujo completo.

## Decisiones tomadas

- **Entidad separada `'pedido'`** en el tab, NO extensión de `rxn_sync_status`. Razón: los pedidos son read-only desde nuestro lado, no aplican las columnas de push bidireccional (`direccion_ultima_sync`, `resultado_ultima_sync`, etc.).
- **Columnas denormalizadas en `crm_pedidos_servicio`** en lugar de una tabla aparte. Razón: el dato es 1-a-1 con el PDS, no hay cardinalidad múltiple. Y el listado de PDS las necesita inline para el badge.
- **Estados numéricos TINYINT** en DB, no strings. Razón: el enum viene de Tango y es numérico. El string solo vive en la capa de presentación via `TangoPedidoEstado`.
- **`Get` paginado** para bulk, `GetById` para individual. Razón: costo de red.

## Validación

- Migración corrida en local con éxito. Backfill ejecutado (COALESCE no pisa valores existentes).
- Probe del endpoint con ID 26576 devolvió exactamente lo esperado: ESTADO=5 (Anulado), NRO_PEDIDO="X0065200000984", NOTA_PEDIDO_DTO con 2 notas ("EN PROCESO" y "ANULADO").
- Listado Get devolvió 50 pedidos con estados variados (30 cerrados, 17 cumplidos, 2 anulados, 1 aprobado).

## Pendiente / próximas sesiones

- **Presupuestos en RxnSync**: mismo patrón que pedidos (process=19845 acepta tanto pedidos como presupuestos via talonario diferente — hay que chequear). Charly pidió ir de PDS primero y seguir con presupuestos.
- **Automatización con n8n**: pull programado, idealmente cada 15-30 min para los anulados (que son los más time-sensitive). Decisión posterior de cadencia.
- **Exponer `snapshot_tango` completo en la UI**: hoy el resultado del pull individual muestra solo el message "Estado actualizado: ANULADO", pero el payload queda en memoria. Podría abrirse un details/collapse para ver el JSON como hace Clientes/Artículos.

---

## Segundo tramo (post-testing, mismo 2026-04-18)

Charly testeó la release y reportó tres gaps. Se resolvieron en la misma release 1.15.0 (sin bumpear a 1.15.1) porque son parte del mismo ciclo de desarrollo.

### Gap 1 — Confirm de salida seguía preguntando en PDS enviado-a-Tango + con mail

**Causa raíz**: `PedidoServicioController::hydrateFormState()` es un whitelist explícito. Incluía `tango_sync_payload` y `tango_sync_response` pero **no** incluía `tango_sync_status`. Entonces el form PHP recibía `$pedido['tango_sync_status'] = null` → `data-tango-sent="0"` → `isFlowCompleted()` siempre false → confirm siempre aparecía.

Mismo antipatrón que ya tenía documentado en Engram desde 1.14.1 (el bug de `correos_enviados_count`). Caí de nuevo en la misma trampa.

**Fix**: agregado `'tango_sync_status' => $pedido['tango_sync_status'] ?? null,` al hydrate.

Presupuestos ya lo tenía incluido desde 1.14.1, por eso ahí funcionaba sin tocarse.

### Gap 2 — Botón "Sincronizar Estados de Pedidos" no hacía nada

**Causa raíz**: `RxnSyncController` llamaba `AuthService::requireLogin()` en varios methods (`syncPullArticulos`, `syncPullClientes`, `syncCatalogos`, el nuevo `syncPedidosEstados`) pero **no tenía** `use App\Modules\Auth\AuthService;` en los imports. PHP resolvía la clase en el namespace local (`App\Modules\RxnSync\AuthService`) → fatal "Class not found" → respuesta HTML de error con status 200 → el `fetch` del JS parseaba como JSON fallando silencioso.

Bug latente desde antes del 1.15.0 en los otros 3 endpoints. Apareció visible cuando Charly probó el botón nuevo.

**Fix**: agregado `use App\Modules\Auth\AuthService;` al controller. De una arregla los 4 endpoints.

### Gap 3 — Tab Pedidos aparecía vacío pese a haber 26 PDS históricos

Dos causas encadenadas:

**(a) Backfill del migration inicial estaba mal apuntado**. Asumí que el response de `Create?process=19845` devolvía `{data: {value: {ID_GVA21: ...}}}`. La realidad es `{data: {savedId: 26575}}` — int escalar, no objeto. El path `JSON_EXTRACT($.data.value.ID_GVA21)` no matcheó nunca. 26/26 PDS históricos quedaron con `tango_id_gva21 = NULL`.

**Fix**: migración de reparación `2026_04_18_repair_tango_id_gva21_from_savedid.php` que reintenta con `$.data.savedId`. 26/26 resueltos en dev.

**(b) Query del tab filtraba por `tango_id_gva21 IS NOT NULL`**, que después del gap (a) era todo NULL → tab vacío.

**Fix**: filtro cambió a `tango_sync_status = 'success'`. Ahora lista todos los PDS enviados con éxito, los que no tengan ID resuelto muestran "–" con tooltip y se auto-resuelven al sincronizar.

### Gap 4 (bonus, descubierto al primer sync) — Paginación no alcanzaba a los IDs altos

Al correr el sync masivo la primera vez, 26/26 volvían "no encontrado en Tango". El loop `Get?process=19845&pageSize=500` con tope de 50 páginas cubría hasta 25.000 pedidos. Los IDs locales estaban en 26.550+ → fuera del rango.

**Fix**: reemplazado el paginado completo por `GetByFilter?process=19845&filtroSql=WHERE ID_GVA21 IN (...)` en batches de 100. Una call por cada 100 pedidos, trae solo los que nos interesan.

**Bonus discovery**: `GetByFilter` devuelve la lista en `data.list` (sin `resultData` intermedio), a diferencia del `Get` paginado que la devuelve en `data.resultData.list`. El parsing ahora contempla ambos shapes.

### Feature extra — View de RXN Live con estado Tango

Charly pidió extender la vista SQL `RXN_LIVE_VW_PEDIDOS_SERVICIO` para exponer las columnas nuevas de sync Tango. Migración nueva que:

- Suma `tango_estado` (TINYINT) y `tango_estado_label` (resuelto con `CASE` SQL → "Aprobado/Cumplido/Cerrado/Anulado/Sin sync"), listo para Pivot/Data Live.
- Suma `tango_estado_sync_at` (DATETIME).
- Cambia `nro_pedido_tango` para priorizar `tango_nro_pedido` (formato legible) con fallback al `nro_pedido` legacy.

### Ajuste al workflow — Commits SOLO al cierre

Durante esta sesión Charly pidió formalizar que los commits van **SOLO al cierre**, no en medio del trabajo. Ya lo seguí el resto de la sesión (este segundo tramo quedó sin commitear hasta el ritual final). Agregado a CLAUDE.md.

## Validación end-to-end del segundo tramo

- Probe del endpoint Tango confirmó: `Create` devuelve `data.savedId`, `GetByFilter` devuelve `data.list`, los 4 estados (2/3/4/5) coinciden con lo que dijo Charly.
- Corrida del `syncPedidosEstados` en local: `total: 26, actualizados: 26, sin_match: 0, errores: 0, resueltos_id: 0`.
- Distribución de estados en dev: todos en 2 (Aprobado) — Charly no tiene anulados en la tabla local; el pedido 26576 que anuló no corresponde a un PDS.
- Post-sync los `tango_nro_pedido` quedaron con el formato legible (`X00652-00000964`).
- View `RXN_LIVE_VW_PEDIDOS_SERVICIO` expone las 3 columnas nuevas; verificado con SELECT directo.
