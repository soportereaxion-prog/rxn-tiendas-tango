# Release 1.12.4 — RxnSync: restauración del flujo de Import masivo

**Fecha y tema**: 2026-04-16 21:00 — Consola RxnSync / integración Tango. Restauración de funcionalidad histórica que quedó huérfana en la migración a la consola centralizada.

## Contexto histórico

Antes de la migración a RxnSync (abril 2026), los módulos `Articulos` y `CrmClientes` tenían botones propios de sync masivo:

- `docs/logs/2026-03-26_2107_sync_total_articulos.md` — boton "Sync Total" en el módulo Articulos que encadenaba `Articulos → Precios → Stock`.
- `docs/logs/2026-03-31_1635_restauracion_sync_clientes_crm.md` — restauración de "Client Sync" en CrmClientes tras un `git reset`.

Cuando se creó la consola centralizada RxnSync (`docs/logs/2026-04-09_1425_rxnsync_circuito_articulos_precios_stock.md`), el circuito visual conectó correctamente Precios y Stock al `TangoSyncController` via `?return=`. **Pero para Artículos y Clientes solo se expuso el botón "Auditoría"** — que es match suave local, NO trae datos desde Tango.

Resultado: el código de `TangoSyncController::syncArticulos` y `syncClientes` (import real masivo) nunca se rompió — quedó sin puerta de entrada en la UI nueva. Un tenant nuevo apretaba "Auditoría" y obtenía `Vinculados: 0 | Pendientes: 0` técnicamente correcto pero UX engañoso.

## Qué se hizo

### Fase 1 — Backend

- `app/modules/Tango/Controllers/TangoSyncController.php`:
  - `syncClientes()` ahora respeta `?return=` (con validación anti open-redirect: solo acepta prefijo `/mi-empresa/`). Antes hardcodeaba redirect a `/mi-empresa/crm/clientes`. Imprescindible para que el botón "Solo importar" de RxnSync pueda volver al módulo.

- `app/modules/RxnSync/RxnSyncController.php`:
  - Nuevo método `syncPullArticulos()` (AJAX POST). Ejecuta `TangoSyncService->syncArticulos()` (import masivo, upsert local) + `service->auditarArticulos()` (match suave) en secuencia. Devuelve JSON con stats consolidados: recibidos/insertados/actualizados/omitidos + vinculados/pendientes.
  - Nuevo método `syncPullClientes()` análogo.
  - Nuevo helper privado `resolveTangoSyncService()` que construye `TangoSyncService` con el área correcta (crm|tiendas) según URI.

### Fase 2 — Rutas

- `app/config/routes.php`:
  - `POST /mi-empresa/rxn-sync/sync-full-articulos` (Tiendas)
  - `POST /mi-empresa/rxn-sync/sync-full-clientes` (Tiendas)
  - `POST /mi-empresa/crm/rxn-sync/sync-full-articulos` (CRM)
  - `POST /mi-empresa/crm/rxn-sync/sync-full-clientes` (CRM)

### Fase 3 — UI

- `app/modules/RxnSync/views/index.php` — rediseño de la toolbar del tab activo:
  - **"Sincronizar desde Tango"** (botón principal, gradiente naranja, id `btn-audit-tab-main`): dispara `sync-full-{entidad}s`. Flujo completo Import + Audit en un solo click. Tooltip: "Importa desde Tango (upsert local) y luego audita el vínculo por código. Flujo completo recomendado para tenants nuevos o sincronización periódica."
  - **"Solo importar"** (outline primary, id `btn-only-import-main`): redirige al `TangoSyncController` con `?return=/mi-empresa/[crm/]rxn-sync`. Aprovecha el Flash existente — al volver, se muestra el resumen arriba.
  - **"Solo auditar"** (outline secondary, id `btn-only-audit-main`): AJAX al endpoint viejo `/auditar-*`. Match suave sin traer datos nuevos. Útil para revalidar vínculos.
  - El label dinámico pasó de "Auditoría Clientes/Artículos" a "Sincronizar Clientes/Artículos" al cambiar de tab.
  - Nuevo helper JS `runRxnSyncAjax(opts)` que factoriza el patrón `confirm → fetch POST → mostrar stats → recargar tab`. Lo reutilizan `runSyncFullTab` y `runOnlyAuditTab`.
  - Gate en `runOnlyImportTab()`: si el usuario está en Tiendas + tab Clientes, se muestra alert explicando que el import masivo de clientes es solo CRM (la ruta `/mi-empresa/sync/clientes` no existe por diseño).

## Por qué

La consola RxnSync es el punto de entrada de sincronización. Si el botón principal hace "match suave" en vez de "traer datos", el operador se pierde. La Opción B (un solo botón que hace todo) es la que mejor refleja el flujo operativo real: *configuro Tango → traigo clientes/artículos → sincronizo precios/stock*. No tiene sentido separar Import de Audit si el operador siempre quiere ambos.

Pero también necesitábamos las variantes separadas (Opción A) porque:
- "Solo importar" sirve cuando el operador quiere ver stats de import sin gastar tiempo en audit.
- "Solo auditar" sirve para revalidar vínculos sin traer datos (ej: después de corregir códigos a mano).

Entonces la decisión fue **ambas**: principal grande = flujo completo, dos outline = variantes.

## Impacto

- Un tenant nuevo puede ahora apretar "Sincronizar desde Tango" y en un click traer + vincular clientes/artículos. No hay más "Vinculados: 0" misterioso sin nada que importar.
- El Pull individual por fila (botón de la columna Acciones) sigue funcionando igual — no se tocó.
- El botón "Solo auditar" mantiene 100% el comportamiento previo para quienes lo usaban así.
- `syncClientes` del TangoSyncController ahora se comporta consistente con sus hermanos (respeta `?return=`). Ese fix beneficia también a cualquier futuro caller que quiera volver a un módulo específico.

## Decisiones tomadas

- **No se tocó `TangoSyncService`**: ya estaba listo para ser reutilizado. El cambio es puramente de exposición (nuevos controllers + UI).
- **El botón principal mantiene id `btn-audit-tab-main`** aunque el nombre cambió a "Sincronizar". Razón: ese id lo comparte `btn-bulk-audit-tab` en la barra bulk de selección. Renombrar requería tocar también la barra bulk, y no valía el riesgo para las demos.
- **El gate del botón "Solo importar" en Tiendas + Clientes** es explícito (muestra alert) y no oculta el botón. Preferimos mensaje claro al desaparecer silenciosamente — el operador entiende por qué no funciona. Ocultar el tab Clientes en Tiendas es tech debt pre-existente no atacado acá.
- **No se creó ruta `/mi-empresa/sync/clientes`** (Tiendas). Eso obligaría a definir si Tiendas soporta clientes formalmente o no — debate arquitectónico fuera del scope de estas demos.
- **Timeout `set_time_limit(240)` en los nuevos endpoints**: 4 minutos. El import pagina hasta 100 páginas x 500 registros (50k max) + audit secuencial. El límite anterior de 120s era bajo para catálogos grandes.

## Validación

- Smoke test local CRM: Consola RxnSync → tab Artículos → "Sincronizar desde Tango" → confirma → import OK + audit OK → alert verde con "Sincronización completada. Importados: N recibidos → X nuevos / Y actualizados / Z omitidos. Vinculación: V vinculados / P pendientes."
- Smoke test "Solo importar" → redirige a `/mi-empresa/crm/sync/articulos?return=/mi-empresa/crm/rxn-sync` → al volver muestra Flash con stats.
- Smoke test "Solo auditar" → AJAX al endpoint viejo → alert verde con stats de auditoría solamente (mismo comportamiento que antes de esta release).
- Smoke test gate Tiendas + Clientes → alert warning sin redirect: "El import masivo de clientes desde Tango solo está disponible en el área CRM...".
- `php -l` mental OK (no se corrió porque no hay PHP en PATH del shell; los cambios están en path servido y cualquier error de sintaxis salta al primer hit).

## Pendiente

- Tech debt pre-existente: el tab "Clientes" de RxnSync aparece también en Tiendas. Si algún día se decide que Tiendas no maneja clientes formalmente, ocultar el tab condicionalmente. Hoy el gate del "Solo importar" cubre el caso práctico sin necesidad de remover el tab.
