# Release 1.15.1 — Hotfix MariaDB CAST AS JSON + filtro tango_sync_status

**Fecha**: 2026-04-19 11:03
**Build**: 20260419.1
**Tipo**: Hotfix de release 1.15.0

---

## Qué pasó en prod

Al subir el OTA de la release 1.15.0 (ZIP generado el 2026-04-18), el módulo de Mantenimiento reportó "Sistema actualizado exitosamente. Se aplicaron 1260 archivos… Además, se detectaron y ejecutaron exitosamente **0 migraciones de BD**", pero el listado mostraba **3 pendientes**:

- `2026_04_18_add_tango_estado_to_crm_pedidos_servicio.php`
- `2026_04_18_repair_tango_id_gva21_from_savedid.php`
- `2026_04_18_update_rxn_live_vw_pds_tango_estado.php`

Al intentar ejecutarlas manualmente con "Ejecutar Pendientes":

```
Fallo en migracion: SQLSTATE[42000]: Syntax error or access violation: 1064
You have an error in your SQL syntax; check the manual that corresponds to your
MariaDB server version for the right syntax to use near 'JSON)),
NULLIF(JSON_EXTRACT(tango_sync_response, '$.value.ID_GVA2...' at line 5
```

---

## Diagnóstico

### Causa raíz primaria: `CAST('null' AS JSON)` no existe en MariaDB

La migración inicial usaba el patrón siguiente para detectar valores JSON null dentro del response guardado en `tango_sync_response`:

```sql
NULLIF(JSON_EXTRACT(tango_sync_response, '$.data.value.ID_GVA21'), CAST('null' AS JSON))
```

`CAST(x AS JSON)` es sintaxis válida en MySQL 8+, pero **MariaDB no la soporta**. En MariaDB, el tipo JSON es un alias de `LONGTEXT` con validación opcional, y no acepta ese cast.

**Por qué en local corrió bien**: el entorno local (Open Server / OSPanel) usa MySQL real, no MariaDB. La incompatibilidad solo apareció en prod.

### Causa raíz secundaria: filtro `tango_sync_status = 'ok'`

La misma migración filtraba el UPDATE con:

```sql
WHERE tango_sync_status = 'ok'
```

Pero el valor que realmente escribe `PedidoServicioRepository::markAsSentToTango()` en [PedidoServicioRepository.php:724](app/modules/CrmPedidosServicio/PedidoServicioRepository.php:724) es `'success'`. Ese UPDATE no matcheaba ninguna fila, ni en local ni en prod. En la práctica el daño fue limitado porque la migración de repair (savedId) usa el filtro correcto y cubre el path correcto para el ID_GVA21 real.

### Por qué las 3 quedaron pendientes

El flujo del deploy es correcto:

1. `SystemUpdater::installPackage()` extrae los archivos.
2. Si `$autoMigrate` está en true, llama a `MigrationRunner::runPending()`.
3. `runPending` corre las migraciones en orden alfabético dentro de un `try/catch`. Al primer error:
   - Loguea `ERROR` en `RXN_MIGRACIONES`.
   - Hace `break` para no corromper dependencias.
4. `getExecutedMigrations()` filtra por `resultado = 'SUCCESS'`, por lo que las migraciones con status `ERROR` **siguen apareciendo como pendientes**.

En este deploy:
- `add_tango_estado_to_crm_pedidos_servicio` corrió parcialmente: los `ALTER TABLE` (DDL) se auto-commitean en MariaDB, y eran idempotentes (`SHOW COLUMNS` guard). Esos sí quedaron aplicados.
- El `UPDATE` final tiró error → migración logueada como ERROR → `break`.
- Las otras 2 nunca se intentaron.

El mensaje "0 migraciones exitosas" es técnicamente correcto, pero no avisa explícitamente de errores. Mejora UX futura: que el flash del updater reporte `X exitosas / Y con error` con link a los detalles.

---

## Fix aplicado

### 1. `database/migrations/2026_04_18_add_tango_estado_to_crm_pedidos_servicio.php`

**Antes**:
```sql
NULLIF(JSON_EXTRACT(tango_sync_response, '$.data.value.ID_GVA21'), CAST('null' AS JSON)),
NULLIF(JSON_EXTRACT(tango_sync_response, '$.value.ID_GVA21'), CAST('null' AS JSON)),
NULLIF(JSON_EXTRACT(tango_sync_response, '$.ID_GVA21'), CAST('null' AS JSON))
...
WHERE tango_sync_status = 'ok'
```

**Después**:
```sql
NULLIF(JSON_UNQUOTE(JSON_EXTRACT(tango_sync_response, '$.data.value.ID_GVA21')), 'null'),
NULLIF(JSON_UNQUOTE(JSON_EXTRACT(tango_sync_response, '$.value.ID_GVA21')), 'null'),
NULLIF(JSON_UNQUOTE(JSON_EXTRACT(tango_sync_response, '$.ID_GVA21')), 'null')
...
WHERE tango_sync_status = 'success'
```

### 2. `database/migrations/2026_04_18_repair_tango_id_gva21_from_savedid.php`

**Antes**:
```sql
NULLIF(JSON_EXTRACT(tango_sync_response, '$.data.savedId'), CAST('null' AS JSON))
```

**Después**:
```sql
NULLIF(JSON_UNQUOTE(JSON_EXTRACT(tango_sync_response, '$.data.savedId')), 'null')
```

El filtro `WHERE tango_sync_status = 'success'` ya estaba correcto en esta migración.

### 3. `database/migrations/2026_04_18_update_rxn_live_vw_pds_tango_estado.php`

Sin cambios. El `CREATE OR REPLACE VIEW` usa SQL estándar (COALESCE, CASE) y es compatible con ambos motores.

---

## Comportamiento esperado al re-deployar

1. Al subir el ZIP 1.15.1 a Plesk, el updater va a:
   - Pisar los archivos PHP de migración con la versión corregida.
   - Llamar a `runPending()`.
2. Las 3 migraciones siguen apareciendo como pendientes (ninguna tiene fila SUCCESS en `RXN_MIGRACIONES`).
3. Corren en orden:
   - `add_tango_estado_...`: los ALTER se skipean (columnas ya existen), el UPDATE corre limpio. Como los paths `$.data.value.ID_GVA21`, `$.value.ID_GVA21` y `$.ID_GVA21` no existen en las respuestas reales de Tango Create (el ID viene en `$.data.savedId`), el UPDATE no va a poblar nada — pero tampoco falla. Se loguea SUCCESS.
   - `repair_tango_id_gva21_from_savedid`: el UPDATE con path `$.data.savedId` pobla los PDS históricos con tango_id_gva21 real.
   - `update_rxn_live_vw_pds_tango_estado`: corre la vista SQL.
4. Mensaje final: "se detectaron y ejecutaron exitosamente 3 migraciones de BD".

---

## Pattern documentado para el futuro

**Regla**: en migraciones que corren en prod con MariaDB, nunca usar `CAST(x AS JSON)`. El patrón portátil es:

```sql
-- MAL (solo MySQL):
NULLIF(JSON_EXTRACT(col, '$.path'), CAST('null' AS JSON))

-- BIEN (MySQL + MariaDB):
NULLIF(JSON_UNQUOTE(JSON_EXTRACT(col, '$.path')), 'null')
```

Guardado en Engram como pattern `db/mariadb-vs-mysql-json`.

---

## Validación

- [x] Fix aplicado en ambas migraciones.
- [x] Tercera migración (VIEW) verificada limpia.
- [ ] Probar re-ejecución en prod (Charly sube el ZIP y corre el botón).

---

## Pendientes / mejora futura

- **Updater**: que el flash post-deploy reporte explícitamente cuando hay migraciones con error (hoy dice "0 exitosas" a secas). Opcional, no crítico.
- **Entorno de test MariaDB**: tener un local MariaDB para pillar incompatibilidades antes del deploy. Hoy confiamos en que MySQL local es representativo, y no siempre lo es.
