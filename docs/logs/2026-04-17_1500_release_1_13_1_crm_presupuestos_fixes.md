# Release 1.13.1 — CrmPresupuestos: bugs varios + clasificaciones PDS sincronizadas

**Fecha**: 2026-04-17
**Build**: 20260417.3
**Tema**: Fix de 3 bugs reportados por Charly en el form de Presupuestos + integración de clasificaciones PDS al Sync Catálogos de RXN Sync (para cerrar la deuda técnica del campo raw `clasificaciones_pds_raw`).

---

## Qué se hizo

### Bug 1 — Lista de precios por defecto caía a "Guardado localmente (31)"

**Síntoma**: al entrar a un presupuesto nuevo, el select "Lista de precios" mostraba "Guardado localmente (31)" en lugar de una lista válida.

**Causa**: `PresupuestoController::loadUserTangoProfileDefaults` leía la lista por defecto desde el snapshot del perfil Tango del usuario (`ID_GVA10_ENCABEZADO`). Cuando ese código (ej: `31`) no existía en el catálogo CRM actual — porque había quedado stale de un sync viejo, o porque se eliminó del catálogo en Tango — el fallback del `renderOptions` en `form.php:242-243` emitía `Guardado localmente (N)` como option selected. El fallback ya no tenía sentido para este caso.

**Fix**: helper interno `resolveDefault(empresaId, type, code)` que valida el código contra el catálogo CRM con `CommercialCatalogRepository::findOption`; si no existe, cae a `findFirstByType`. Reemplaza los 5 `if ($x === "")` previos (depósito, condición, transporte, lista, vendedor). Ahora si el perfil trae un código stale, se ignora y se usa el primero disponible del catálogo.

### Bug 2 — Descripción de clasificación no se persistía ni se mostraba

**Síntoma**: al seleccionar una clasificación en el picker, el input mostraba solo el código (ej: `2`). La descripción no aparecía en pantalla ni se guardaba en BD.

**Causa**: el picker de clasificaciones (`/mi-empresa/crm/pedidos-servicio/clasificaciones/sugerencias`) devuelve `value: code` (solo código) y `label: "code - description"`. El form solo persistía `clasificacion_codigo` y `clasificacion_id_tango`.

**Fix** — opción "A" elegida por Charly (persistencia real, no solo cliente-side):
- Nueva columna `crm_presupuestos.clasificacion_descripcion VARCHAR(255) NULL`. Migración idempotente `2026_04_17_add_clasificacion_descripcion_to_crm_presupuestos.php`.
- Repository: INSERT + UPDATE + `buildHeaderPayload` incluyen el campo.
- Controller: `hydrateFormState`, `defaultFormState`, `buildFormStateFromPost`, payload final — todos exponen/aceptan `clasificacion_descripcion`.
- Form: nuevo `<input type="hidden" name="clasificacion_descripcion">` + `<div data-clasificacion-desc-display>` debajo del input para mostrar la descripción.
- JS: el picker de clasificación recibe un `onSelect` custom que parsea `item.label` (formato `COD - DESCRIPCIÓN`) extrayendo la descripción post-código, y actualiza hidden + display. Al tipear/borrar manualmente, se limpia la descripción.

**Nota de Charly**: a mediano plazo las clasificaciones deberían salir del campo raw de configuración (`clasificaciones_pds_raw` en `empresa_config_crm`) y pasar a ser un catálogo propio en BD poblado por RXN Sync — como los otros catálogos comerciales (depósito, lista, vendedor). **Esto se cerró en la misma release** (ver sección "Bug 4 / RxnSync" más abajo) para no dejar la deuda abierta.

### Bug 4 / RxnSync — Clasificaciones PDS al catálogo comercial sincronizado

**Contexto**: las clasificaciones PDS (process 326 de Tango) vivían en `empresa_config_crm.clasificaciones_pds_raw` como un JSON raw. Charly lo había puesto así "para salir del paso" en su momento, pero nunca pasó a ser un catálogo sincronizado en BD como los otros (depósitos, listas, vendedores, transportes, condiciones). El endpoint `/mi-empresa/crm/pedidos-servicio/clasificaciones/sugerencias` leía del raw primero y caía a fetch en vivo a Tango si no había raw.

**Problema**: si el raw nunca se llenaba o quedaba stale, el picker iba a golpear la API de Tango en cada búsqueda del usuario — latencia alta, posible timeout, no-resiliente si la API está caída.

**Fix**:
- `CommercialCatalogSyncService::sync`: agrega fetch de `TangoApiClient::getClasificacionesPds()` y upsert al catálogo con `tipo = 'clasificacion_pds'`. Nuevo método `mapClasificaciones()` que normaliza `COD_GVA81` / `DESCRIP` / `ID_GVA81` al formato esperado por el repo genérico. Aprovecha la paginación robusta que ya tenía `TangoApiClient::fetchRichCatalog`.
- `RxnSyncController::syncCatalogos`: el mensaje de éxito incluye el contador de clasificaciones.
- `RxnSync/views/index.php`: tooltip y texto de confirm del botón "Sync Catálogos" mencionan clasificaciones PDS en la lista.
- `PedidoServicioController::classificationSuggestions`: prioridad reescrita:
  1. BD catalog (`crm_catalogo_comercial_items` WHERE tipo = `clasificacion_pds`) via `CommercialCatalogRepository::findAllByType` — lectura 100% local, instantánea.
  2. Fallback legacy al campo `clasificaciones_pds_raw` si la BD está vacía (para instalaciones que todavía no corrieron Sync Catálogos después de esta release).
  3. Fallback a fetch live al process 326 de Tango (flujo antiguo).

El formato interno del loop de filtrado (`COD_GVA81`/`DESCRIP`/`ID_GVA81`) quedó igual, así que la respuesta al front es idéntica y ningún consumidor (ni PedidoServicio ni Presupuestos) necesita cambiar.

**Path de migración para los usuarios**: la primera vez que corran Sync Catálogos post-1.13.1, el catálogo se llena. El raw legacy queda como backup por si la gente ya lo tenía completado.

### Bug 3 — Lock del encabezado dejaba al usuario sin poder corregir campos obligatorios

**Síntoma**: cargaba un renglón, guardaba sin cliente, la validación devolvía `"Selecciona un cliente desde la base CRM"`, la página reloadeaba con el ítem intacto, el lock se activaba (porque `hasItems()` = true), y el usuario no podía tocar el campo de cliente para corregirlo. Dead-end.

**Causa**: `crm-presupuestos-form.js` llamaba `lockHeader()` apenas `hasItems()` era true, sin chequear el estado de los campos mandatorios.

**Fix**: nueva función `headerRequiredFieldsFilled()` que chequea fecha + lista + cliente_id. `lockHeader()` empieza con un guard: si alguno falta, no bloquea. Circuit breaker — el lock nunca debe impedir corregir un obligatorio faltante.

El flujo post-fix:
1. Usuario carga renglón sin cliente → intenta guardar → validación devuelve error.
2. Página reloadea con el ítem intacto y el mensaje de error.
3. Lock chequea obligatorios → cliente_id vacío → NO bloquea.
4. Usuario puede seleccionar cliente, guardar, todo ok.
5. Al recargar con todo completo → lock se aplica normalmente.

---

## Por qué

Los 3 bugs venían de asumir "estado feliz":
- Bug 1 asumía que el perfil Tango siempre traía códigos válidos en el catálogo actual.
- Bug 2 asumía que el código de clasificación era auto-explicativo sin su descripción (no lo es para los vendedores).
- Bug 3 asumía que si había ítems, los mandatorios ya estaban completos (no necesariamente tras un error de validación).

---

## Impacto

- **Base de datos**: +1 columna en `crm_presupuestos` (`clasificacion_descripcion`, nullable). Sin backfill — presupuestos viejos quedan con `NULL`, se llena al próximo save.
- **Runtime**: cero cambios en el flujo happy path. Solo se activan los fixes en los escenarios degradados.
- **UI**: nuevo texto de descripción debajo del input de clasificación. Ocupa ~1 línea de altura adicional.

---

## Validación

- Migración corrida en local contra la base de dev (`php tools/run_migrations.php`). Se aplicó la nueva migración; no hubo otras pendientes.
- Lint/sintaxis: chequeo visual — no hay build formal para PHP/JS en este repo.
- Reprod del escenario del bug 3: cargar item → intentar guardar sin cliente → observar que el cliente sigue editable. ✅

---

## Bug 5 — Pickers de cliente y artículo devolvían vacío sin término (regresión)

**Síntoma**: al entrar a un presupuesto nuevo, enfocar el input de cliente (o artículo) y dar Enter / flecha abajo sin tipear, el dropdown mostraba "No se encontraron resultados" en vez de listar resultados scrolleables. En PDS el mismo gesto funcionaba bien.

**Por qué en PDS anda y en Presupuestos no**:
- PDS usa `PedidoServicioRepository::findClientSuggestions` y `findArticleSuggestions` (métodos propios) que sí manejan bien el caso `term === ''` (query alfabética simple, sin filtro extra).
- Presupuestos usa los repos compartidos `CrmClienteRepository::findSuggestions` y `ArticuloRepository::findSuggestions` que tenían `if (search === '') return [];` como early return.

**Causa raíz verificada**: [CrmClienteRepository.php:103-107](app/modules/CrmClientes/CrmClienteRepository.php:103) y [ArticuloRepository.php:269-274](app/modules/Articulos/ArticuloRepository.php:269) — ambos con guard temprano que pisaba el contrato del endpoint. El comentario en los controllers de Presupuestos (agregado en alguna release pasada) decía literal: "Sin guard de longitud mínima: al abrir el Spotlight Modal con Enter, el frontend hace fetch con q='' y esperamos que el backend devuelva los primeros resultados" — pero el repo no se enteró de ese contrato.

**Descartado**: la sospecha inicial era que venía de los cambios de Configuración/Connect. Los endpoints de sugerencias leen 100% local (`crm_clientes`, `crm_articulos`) — Connect no toca nada.

**Fix**:
- **Repo**: el early return se elimina. Cuando `search === ''`, se hace una query simple `ORDER BY razon_social ASC LIMIT :limit` (para clientes) o `ORDER BY nombre ASC LIMIT :limit` (para artículos). El branch con término se mantiene idéntico (CASE WHEN para priorizar matches exactos / starts-with / contains).
- **Controller** (solo en Presupuestos, PDS ya estaba bien): límite dinámico — `30` cuando el term está vacío (scroll cómodo para explorar), `5` / `6` cuando hay term (relevancia).

**Follow-up futuro**: Charly dijo "en algún futuro me parece que le tendríamos que dar una vuelta de tuerca a eso" — el ordenamiento alfabético es la opción más simple, pero para operaciones grandes se podría priorizar por `updated_at DESC`, recencia de uso, o algún proxy de popularidad. Lo dejo anotado para cuando haya tiempo.

## Punto 6 — Limpieza del campo legacy `clasificaciones_pds_raw`

A posteriori de cocinar el sync, Charly pidió sacar el textarea de la configuración ("Catálogo Local de Clasificaciones PDS"). Hecho en `app/modules/EmpresaConfig/views/index.php`:

- Se quitó el `<textarea name="clasificaciones_pds_raw">` y su label.
- Se quitó la llamada JS `fetchCatalog('tango-clasificaciones', ...)` que auto-llenaba el textarea durante "Validar Conexión" (ahora son 4 catálogos, no 5).
- Se borró la función `populateClasificacionesPds(items)` (código muerto).

Verificado: `EmpresaConfigService::save()` **nunca** leía `clasificaciones_pds_raw` desde `$_POST`, así que sacar el textarea no afecta la persistencia. El único writer real del campo es `UsuarioController::fetchTangoProfile` (que sigue existiendo y ahora quedó huérfano — candidato a remover en una release futura). La columna `empresa_config.clasificaciones_pds_raw` queda como fallback del endpoint de sugerencias hasta deprecarse formalmente.

---

## Pendiente / follow-ups

- **Migrar PedidosServicio al mismo patrón de descripción de clasificación**: el form de PDS sigue guardando solo el código. Cuando alguien toque PDS próximamente, replicar la solución de Presupuestos (agregar columna `clasificacion_descripcion` a `crm_pedidos_servicio`, hidden + display, hydration).
- **Re-lock post-selección de cliente sin reload**: si el usuario carga items + selecciona cliente mid-sesión, el lock no se re-activa hasta el próximo reload. Es cosmético — el guardado funciona. Dejo como mejora posible.
- **Deprecar formalmente `clasificaciones_pds_raw`**: el fallback legacy del endpoint todavía lo consulta. En una release futura (cuando haya confianza de que todos los entornos corrieron Sync Catálogos al menos una vez) se puede borrar la columna de `empresa_config_crm`, el branch legacy del controller, la referencia huérfana en `UsuarioController::fetchTangoProfile:137` y la entrada en `EmpresaConfig.php` y `EmpresaConfigRepository`.
- **Pickers: ordenamiento "inteligente" sin término**: hoy cuando `q=""` ordenamos alfabéticamente. Para bases grandes estaría bueno priorizar por `updated_at DESC`, recencia de uso u otro proxy de popularidad. Charly lo dejó anotado como "vuelta de tuerca futura".
- **Clasificación: lentitud en empresas sin raw legacy**: si una empresa no corrió Sync Catálogos post-1.13.1 **y** tampoco tiene `clasificaciones_pds_raw` cargado, el endpoint de sugerencias cae a fetch live a Tango (process 326) — ahí sí hay latencia de red. Workaround actual: correr Sync Catálogos una vez y queda instantáneo para siempre.

---

## Relevant Files

- `database/migrations/2026_04_17_add_clasificacion_descripcion_to_crm_presupuestos.php` — migración nueva.
- `app/modules/CrmPresupuestos/PresupuestoRepository.php` — INSERT/UPDATE/buildHeaderPayload/ensureSchema actualizados.
- `app/modules/CrmPresupuestos/PresupuestoController.php` — resolveDefault helper + hidratación del nuevo campo.
- `app/modules/CrmPresupuestos/views/form.php` — hidden + display de descripción.
- `public/js/crm-presupuestos-form.js` — headerRequiredFieldsFilled + onSelect del picker de clasificación.
- `app/config/version.php` — bump 1.13.1 / 20260417.3.
- `app/modules/RxnSync/Services/CommercialCatalogSyncService.php` — suma clasificación PDS al ciclo de sync.
- `app/modules/RxnSync/RxnSyncController.php` — mensaje de success menciona clasificaciones.
- `app/modules/RxnSync/views/index.php` — tooltip + confirm mencionan clasificaciones.
- `app/modules/RxnSync/MODULE_CONTEXT.md` — actualización de alcance.
- `app/modules/CrmPedidosServicio/PedidoServicioController.php` — endpoint de sugerencias con 3 prioridades (BD → raw → live).
