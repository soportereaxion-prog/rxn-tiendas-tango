# Release 1.16.0 — RXN Live: agrupación estilo Tango + paginación "Todos" + fix Tabla Dinámica + hotfix HY093 envío PDS

**Fecha**: 2026-04-19 (sesión nocturna domingo)
**Build**: 20260419.2
**Tipo**: Feature mayor en RXN Live + hotfix CRÍTICO de prod en envío PDS a Tango

---

## TL;DR

Release con dos núcleos:

1. **RXN Live** ganó tres mejoras de UX que lo acercan al "feeling Tango": agrupación drag-to-group con hasta 3 niveles anidados y subtotales, paginación con selector "Mostrar Todos", y fix de un bug donde la Tabla Dinámica perdía columnas declaradas en la view SQL pero no en `pivot_metadata`.

2. **Hotfix HY093 en envío PDS a Tango** (heredado de release 1.15.0): el método `markAsSentToTango` reusaba el mismo placeholder PDO `:nro_pedido` en dos columnas, lo que PDO con prepares nativas (default desde 03-2026) rechaza con `SQLSTATE[HY093]: Invalid parameter number`. Cuando Tango aceptaba el pedido pero rxn_suite no podía registrarlo, el catch caía en `markAsErrorToTango` y el pedido quedaba como "error" aunque en Tango sí existía → riesgo de duplicado al reintentar.

Suma una migración utilitaria (`resync_usuario_nombre`) que normaliza la denormalización de `crm_pedidos_servicio.usuario_nombre` cuando un usuario corrige su nombre después de haber generado PDS.

---

## 1. RXN Live — Fix Tabla Dinámica (`getFieldOptions` defensivo)

### Síntoma
En el dataset "Pedidos de Servicio (Tiempos)", el dropdown de FILAS/COLUMNAS de la Tabla Dinámica mostraba solo 8 opciones cuando la grilla plana (que hace `SELECT *`) mostraba más columnas (ID_TECNICO, COD_TANGO, Estado Tango, Última Sync Tango).

### Causa raíz
`RxnLiveService::pivot_metadata` declaraba 10 columnas, pero `RXN_LIVE_VW_PEDIDOS_SERVICIO` (extendida en release 1.15.0) ya devolvía 19. Las columnas no declaradas eran silenciadas en el pivot porque `getFieldOptions(purpose)` iteraba SOLO `pivotMetadata`.

### Fix
1. Agregadas al `pivot_metadata` de `pedidos_servicio`: `id_tecnico`, `cod_tango`, `tango_estado_label`, `tango_estado_sync_at`.
2. **Fallback defensivo en `getFieldOptions()`**: para `purpose === 'group'`, si una columna del `rawDatasetRows[0]` no está declarada Y no está en la blacklist `PIVOT_INTERNAL_COLS = ['empresa_id', 'cliente_id', 'id_pedidoservicio', 'diagnostico', 'tango_estado']`, se autoincluye como groupable string. Para `purpose === 'val'` NO hay fallback (suma/promedio requieren declaración explícita).

### Beneficio
El bug no se vuelve a repetir cuando una migración futura agregue columnas a `RXN_LIVE_VW_*` sin tocar el PHP. Si la nueva columna es ruido (FK, ID interno), basta agregarla a `PIVOT_INTERNAL_COLS`.

---

## 2. RXN Live — Paginación con selector "Mostrar Todos"

### Antes
`$limit = 50` hardcoded en `RxnLiveController::dataset()`. Botones Ant/Sig sin opción de cambiar tamaño de página.

### Después
- Whitelist `['50', '100', '250', '500', 'all']` aceptada vía `?per_page=`. "Todos" levanta el límite a 1.000.000 (techo defensivo para no tildar el server).
- Footer rediseñado: selector "Mostrar" siempre visible + Ant/Sig solo cuando hay más de una página + contador "X registros".
- `changePerPage(newPerPage)` reload manteniendo filtros y reseteando `page=1` para no quedar en offset huérfano.
- El `per_page` se "recuerda" entre visitas porque el controller persiste `last_url` por dataset (bonus gratis).

---

## 3. RXN Live — Agrupación estilo Tango (drag-to-group, 3 niveles, subtotales, expand/collapse)

### Mecánica de uso
1. Arriba de la grilla aparece una zona dashed: "Arrastrá aquí los encabezados de columna para agrupar (hasta 3 niveles)".
2. El usuario arrastra un `<th>` cualquiera a la zona → aparece un chip con número de nivel y nombre de la columna.
3. La grilla se re-renderiza agrupada: cada grupo es una fila con caret expandible (▼/▶), el label del campo, el valor del grupo, un contador de registros, y los subtotales numéricos en las celdas correspondientes.
4. Hasta 3 niveles anidables (`MAX_GROUP_LEVELS = 3`). Más niveles → alerta "Solo se pueden anidar 3 niveles".
5. Click en la X del chip → quita ese nivel.
6. Click en una fila de grupo → toggle expand/collapse.
7. Por defecto todo expandido. El estado collapse se recuerda en sessionStorage.
8. Si la primera agrupación se activa estando en `per_page=50` y faltan registros, se redirige automáticamente a `per_page=all` (Charly: "que actúe sobre todos los registros").

### Archivos tocados (todo en `app/modules/RxnLive/views/dataset.php`)
- HTML: drop zone `#groupByZone` + `#groupByChips` insertada antes de `#planaResultContainer`.
- CSS: bloque nuevo dentro del `<style>` con `.rxn-group-zone`, `.rxn-group-chip`, `tr.rxn-group-row.rxn-group-level-{0,1,2}` con fondos azul progresivos.
- Estado JS: `groupByCols` (array hasta 3), `groupCollapseState` (objeto `{path: true}` solo guarda colapsados — eficiente).
- Helpers: `rxnEscapeHtml`, `rxnEscapeJsArg`, `buildGroupChipHtml`, `renderGroupZone`, `handleHeaderDragStart/End`, `handleGroupZoneDragOver/Leave/Drop`, `removeGroupCol`, `toggleGroupCollapse`, `computeGroupSubtotals`, `formatGroupKey`, `buildDetailRowHtml`, `buildGroupedRowsHtml` (recursiva).
- `extractViewConfig` + `applyViewConfig` + bloque "Vista Base" del DOMContentLoaded: incluyen `groupByCols` y `groupCollapseState` para persistencia en sessionStorage y vistas guardadas.

### Decisiones clave
- **Default expandido + recordar solo colapsados** → sessionStorage liviano.
- **Path delimiter `|||`** entre niveles para evitar colisión con keys que contengan `|`.
- **Subtotales por columna** (no colspan único) → matchea visualmente con la grilla y muestra suma de Tiempo (Hs), Cant. PDS, etc. en cada nivel de anidación.
- **Sin reorder de chips** entre sí (decisión por simplicidad — para reordenar: quitar y volver a arrastrar). Charly OK con esto.
- **Headers ya agrupados NO son draggables** (evita agregarlos dos veces). La columna sigue visible con valores repetidos en filas de detalle (Tango la oculta — feature futura si surge).
- **Total general (TFOOT)** se sigue calculando independiente de la agrupación.
- **Export NO respeta agrupación** — exporta filas planas con filtros y orden. Acordado para iteración futura.

### Compatibilidad con features existentes
- Sort por header sigue funcionando (click → sort, drag → group; el browser distingue por movimiento real).
- Resize de columnas con el handle derecho intacto (`startColResize` ya hacía `e.preventDefault() + e.stopPropagation()` en el mousedown → convive con el draggable del th padre).
- Filtros locales (`flatFilters`/`flatDiscreteFilters`): la agrupación opera sobre `filteredDatasetRows`, así que respeta filtros activos.
- Vistas guardadas: agrupación se persiste en `extractViewConfig` automáticamente.

---

## 4. Hotfix CRÍTICO — HY093 en envío PDS a Tango

### Síntoma en prod
Al apretar "Enviar a Tango" en el form de PDS, cartelito rojo:
```
No se pudo enviar el PDS a Tango: SQLSTATE[HY093]: Invalid parameter number
```

### Causa raíz
`PedidoServicioRepository::markAsSentToTango()` reusaba el mismo placeholder nominal `:nro_pedido` en dos columnas distintas del UPDATE:

```sql
UPDATE crm_pedidos_servicio SET
    nro_pedido = :nro_pedido,                      -- uso 1
    tango_id_gva21 = COALESCE(:tango_id_gva21, tango_id_gva21),
    tango_nro_pedido = :nro_pedido,                -- uso 2 ← MISMO placeholder
    ...
```

PDO con `ATTR_EMULATE_PREPARES = false` (configurado en `app/core/Database.php:39` desde 03-2026) usa prepares NATIVAS de MySQL/MariaDB, y esas RECHAZAN reutilización de placeholders nominales. **Mismo bug ya pisado en marzo 2026** según los logs `2026-03-25_1608_ux_refinements_y_buscadores.md` (buscador de clientes con `:s` reutilizado) y `2026-03-31_2155_pds_fix_buscadores_y_layout.md` (`PedidoServicioRepository` con `:term` reutilizado).

### Por qué no se notó al deployar release 1.15.0
Probablemente nadie envió un PDS exitoso entre el 18-04 (release 1.15.0) y el 19-04 (descubrimiento). Los envíos que fallan en `sendOrder()` caen directo a `markAsErrorToTango`, que NO tiene placeholders duplicados. Solo los exitosos pasaban por la query rota.

### Daño colateral en prod (ventana 2026-04-18 23:59 → 2026-04-19 ~22:00)
Cuando Tango aceptaba el pedido, `markAsSentToTango` tiraba HY093 → catch del `PedidoServicioTangoService::send()` (línea 180-188) → `markAsErrorToTango` con SOLO 4 args (sin pasar el `$response` exitoso, perdido en el aire). Resultado:
- Tango con el pedido creado y NRO_PEDIDO asignado.
- rxn_suite con `tango_sync_status = 'error'` y `tango_sync_response = NULL`.
- Si el usuario reintentaba → DUPLICADO en Tango.

### Fix aplicado
Local al método `markAsSentToTango` — agregar placeholder distinto `:tango_nro_pedido` y bindearlo al mismo valor en `execute()`. NO se cambia firma del método ni flow de llamadas.

### Auditoría sugerida en prod
```sql
SELECT id, empresa_id, numero, fecha_inicio, cliente_nombre,
       tango_sync_status, tango_sync_error,
       tango_sync_payload IS NOT NULL AS tiene_payload, updated_at
FROM crm_pedidos_servicio
WHERE tango_sync_status = 'error'
  AND tango_sync_error LIKE '%HY093%'
  AND updated_at >= '2026-04-18 23:59:00'
ORDER BY updated_at DESC;
```
Para cada match: verificar manualmente en Tango si el pedido existe (cliente + fecha). Si sí → fix manual con `UPDATE crm_pedidos_servicio SET tango_sync_status='success', ...` para que el sync de estados los pille.

### Pendiente para futura iteración
El catch del `PedidoServicioTangoService::send()` debería distinguir entre fallo del `sendOrder()` (real) y fallo del `markAsSentToTango()` posterior (post-éxito). En este último caso la lógica correcta es reintentar el UPDATE o al menos NO marcar como error. Sin esto, cualquier futuro bug en el repository post-envío puede repetir la historia.

---

## 5. Migración utilitaria — Resync de `usuario_nombre` denormalizado

### Por qué
Cuando se crea un PDS, se graba `usuario_nombre` por valor (snapshot). Si un usuario corrige su nombre después (ej: "Charly" → "Charly Yaciofani"), los PDS viejos quedan con el nombre anterior y los filtros de RXN Live muestran al mismo usuario como dos opciones distintas.

### Cómo
Migración nueva `2026_04_19_resync_usuario_nombre_in_crm_pedidos_servicio.php`:

```sql
UPDATE crm_pedidos_servicio ps
INNER JOIN usuarios u
    ON ps.usuario_id = u.id
   AND ps.empresa_id = u.empresa_id
SET ps.usuario_nombre = u.nombre
WHERE ps.usuario_id IS NOT NULL
  AND ps.usuario_nombre <> u.nombre
  AND u.deleted_at IS NULL
```

- Multi-tenant: respeta `empresa_id`.
- Idempotente: el `<>` evita actualizar si no hay drift.
- Defensiva: guards `SHOW TABLES` y `SHOW COLUMNS` antes del UPDATE — si una DB cliente no tiene CRM, la migración salta sin tocar nada.
- Excluye usuarios soft-deleted (su nombre puede haber sido modificado intencionalmente al darlos de baja).

### Aplicación
Corre automáticamente al subir el OTA. En local ya se ejecutó (1 fila afectada en la DB de Charly).

---

## Pendientes capturados para próximas iteraciones

Guardados en Engram con detalle:

1. **Filtros "Selección Local" en RXN Live** muestran solo valores de la página cargada, no del dataset completo. Solución sugerida: endpoint `GET /rxn_live/distinct?dataset=X&col=Y` con cache JS + LIMIT 1000. Memoria: `rxn_live/distinct-filter-paginated-bug`.

2. **Catch del `PedidoServicioTangoService::send()`** debería distinguir errores del envío vs errores post-envío del repository. Mientras no se arregle, cualquier bug en `markAsSentToTango` puede repetir el escenario "Tango aceptó pero rxn_suite marcó como error". Memoria: `crm_pedidos_servicio/markAsSentToTango-hy093` (sección Learned).

3. **Export de RXN Live no respeta agrupación**. Hoy exporta filas planas con filtros y orden — útil pero no lo más cómodo para reportes agrupados. Iteración futura.

4. **Sticky de la zona de agrupación al scrollear**. Hoy se va con el scroll del table-responsive. Tango la mantiene visible.

---

## Items concretos del release

- `app/modules/RxnLive/RxnLiveService.php`: `pivot_metadata` de `pedidos_servicio` extendido con `id_tecnico`, `cod_tango`, `tango_estado_label`, `tango_estado_sync_at`.
- `app/modules/RxnLive/RxnLiveController.php::dataset`: whitelist `per_page`, mapeo a `$limit` (incluye `'all'` → 1.000.000), pasa `perPage` al view, excluye `per_page` de `$filters`.
- `app/modules/RxnLive/views/dataset.php`: 
  - `getFieldOptions()` reescrito con fallback defensivo + `PIVOT_INTERNAL_COLS`.
  - Footer del paginador rediseñado (selector "Mostrar" siempre visible + Ant/Sig condicional + contador).
  - `changePerPage()` JS para reload con per_page seleccionado.
  - HTML drop zone para agrupación + estilos CSS.
  - Estado `groupByCols` + `groupCollapseState` + helpers + funciones recursivas de render agrupado.
  - Hidratación en `applyViewConfig` y bloque "Vista Base" del DOMContentLoaded.
  - Headers `<th>` ahora son draggables (excepto los ya agrupados).
- `app/modules/CrmPedidosServicio/PedidoServicioRepository.php::markAsSentToTango`: placeholder `:nro_pedido` separado de `:tango_nro_pedido` en el UPDATE + bind explícito en el `execute()`. Comentario en código explicando el por qué con referencia al bug de marzo.
- `database/migrations/2026_04_19_resync_usuario_nombre_in_crm_pedidos_servicio.php` NUEVO: resync del `usuario_nombre` denormalizado con guards de tabla/columna y filtro `<>` para idempotencia.
- `app/config/version.php`: bump a 1.16.0 / build 20260419.2.
- `docs/logs/2026-04-19_2300_release_1_16_0_rxn_live_agrupacion_y_hotfix_hy093.md` NUEVO (este archivo).

---

## Validación pre-release

- [x] RXN Live: dropdowns de FILAS/COLUMNAS muestran ID_TECNICO, COD_TANGO, Estado Tango (Charly OK).
- [x] RXN Live: selector "Mostrar Todos" funciona y mantiene filtros (Charly OK).
- [x] RXN Live: agrupación drag-to-group con 3 niveles, subtotales, expand/collapse, persist en reload (Charly OK).
- [x] CRM PDS: envío a Tango exitoso devuelve "PDS enviado a Tango correctamente. Pedido externo: #X00XXX-XXXXXXX." (Charly OK con captura).
- [x] Migración resync corrida en local (1 PDS afectado, dropdown limpio confirmado por Charly).
- [ ] Auditoría en prod del HY093 (Charly va a correr la query post-deploy).

---

## Pendiente post-release (acción de Charly)

1. Subir el ZIP a Plesk y dejar que el OTA aplique las migraciones (en este release: solo `2026_04_19_resync_usuario_nombre`).
2. Correr la query de auditoría HY093 contra prod (ver sección 4) para identificar PDS que pudieran estar marcados como error pero existir en Tango. Reparar a mano los que correspondan.
3. Validar en prod que un envío nuevo de PDS a Tango ya no tira HY093.
