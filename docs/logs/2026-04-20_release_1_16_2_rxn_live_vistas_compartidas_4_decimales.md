# Release 1.16.2 — RXN Live: vistas compartidas por empresa + 4 decimales default

**Fecha**: 2026-04-20
**Build**: 20260420.1
**Scope**: módulo RxnLive

---

## Qué se hizo

### 1) Vistas guardadas → scope lectura por empresa

**Problema reportado por Charly**: "Guardo una vista y los otros usuarios no la ven. ¿Puede ser?" → sí, la tabla `rxn_live_vistas` tenía solo `usuario_id` y el `getUserViews` filtraba por ese campo. Cada user veía únicamente las suyas.

**Modelo nuevo (Opción A confirmada por Charly)**:
- Lectura: todos los usuarios de la misma empresa ven las mismas vistas.
- Ownership: solo el dueño (`usuario_id`) puede sobrescribir ("Guardar") o eliminar.
- UX: el dropdown muestra el nombre del dueño al costado de las vistas ajenas (ej: `Mis PDS — Gaby`). En vistas ajenas los botones "Guardar" y "Eliminar" se ocultan; el user puede duplicar con "Nueva Vista".

**Migración `2026_04_20_02_add_empresa_id_to_rxn_live_vistas.php`**:
- Idempotente (`SHOW COLUMNS LIKE 'empresa_id'` antes de alterar).
- Backfill con `UPDATE ... INNER JOIN usuarios u ON u.id = v.usuario_id SET v.empresa_id = u.empresa_id`.
- Nuevo índice `idx_empresa_dataset (empresa_id, dataset)` para listados scoped.
- El viejo índice `idx_usuario_dataset` se conserva — sigue útil para auditoría por dueño.

**Guards de seguridad**:
- `saveUserView`: el UPDATE mantiene `WHERE id = ? AND usuario_id = ?`. Si un user ajeno enviara view_id ajeno, el UPDATE no afecta filas (0 rows).
- `deleteUserView`: intacto — solo borra si `usuario_id` matchea.

### 2) Formato numérico a 4 decimales default

El gatillo fue el caso de "Tiempo (Hs)" del dataset `pedidos_servicio` que mostraba `2,48` cuando en realidad el valor interno tiene mayor precisión. Charly pidió que todos los numéricos muestren al menos 4 decimales.

**Lugares actualizados** en `app/modules/RxnLive/views/dataset.php`:
1. Totales del `<tfoot>` de la vista plana.
2. Celdas de detalle en `buildDetailRowHtml`.
3. Subtotales de grupo en `buildGroupedRowsHtml`.
4. `formatVal()` de pivot (excepto operación COUNT, que sigue en 0 decimales).
5. Tooltip del chart (excepto COUNT).

Todos pasan de `{minimumFractionDigits:2, maximumFractionDigits:2}` a `{minimumFractionDigits:4, maximumFractionDigits:4}`.

### 3) Export Excel — verificación

Charly pidió "controlar que las columnas configuradas y sus dimensiones sean exportadas al excel". La lógica ya estaba implementada (release 1.9.1):
- Frontend en `updateExportForm()` empuja `hidden_cols`, `ordered_cols`, `col_widths`, `flat_filters`, `discrete_filters`, `global_date_format`.
- `RxnLiveController::exportar` los aplica. Los widths se convierten a Excel width units (`px/7` para Calibri 11) con `OpenSpout\Writer\XLSX\Options::setColumnWidth`.

No hay cambio de código en esta release sobre export. Si Charly detecta un caso concreto donde no se respeta alguna dimensión/orden, se trata como bug puntual en una iteración separada.

---

## Archivos afectados

- `database/migrations/2026_04_20_02_add_empresa_id_to_rxn_live_vistas.php` NUEVO
- `app/modules/RxnLive/RxnLiveService.php` — firmas `getUserViews` y `saveUserView` reciben `empresa_id`.
- `app/modules/RxnLive/RxnLiveController.php` — pasa `$empresaId` y expone `currentUserId` al view.
- `app/modules/RxnLive/views/dataset.php` — dropdown con data-owner-id / data-is-mine, `toggleDeleteViewButton` extendido para Guardar, 5 cambios de 2→4 decimales.
- `app/modules/RxnLive/MODULE_CONTEXT.md` — descripción de scope + decimales default.
- `app/config/version.php` — bump a 1.16.2.

---

## Decisiones tomadas

- **Ownership para editar/borrar queda con el dueño**, no compartido. Razón: evita que un user pise la vista de otro sin querer. Si el equipo necesita editar colaborativamente, el patrón es duplicar con "Nueva Vista" y el dueño nuevo tiene su propia copia. Simple, sin locks ni auditoría.
- **4 decimales fijos, no variables** (no `min:4, max:8`). Razón: consistencia visual. Si en el futuro aparece un caso donde la precisión del dato excede 4 decimales y la pérdida importa, se revisa — pero para el caso actual de horas de PDS, 4 decimales alcanzan de sobra.
- **No se agrega flag `compartida`** (Opción B descartada). Razón: Charly prefirió el modelo más simple — todo compartido. Si mañana aparece la necesidad de vistas privadas, se agrega un flag booleano sin romper el esquema actual.

---

## Validación

- [x] Migración corre local (`php tools/run_migrations.php`) sin errores. Backfill exitoso.
- [ ] Pendiente: probar con 2 usuarios de la misma empresa que uno guarda y el otro ve.
- [ ] Pendiente: probar que un user ajeno no ve los botones Guardar/Eliminar en vistas de otro.
- [ ] Pendiente: verificar con dataset real que los decimales se ven correctamente en plana, pivot, tfoot y chart.

---

## Pendiente para próximas iteraciones

- Si aparece bug puntual de export Excel con widths/orden, abrir iteración separada con caso reproducible.
- Eventualmente considerar flag "privada" si el equipo necesita vistas personales sin exponer.
