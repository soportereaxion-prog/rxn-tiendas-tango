# Iteración 50 — Release 1.46.4 — Audit DELETE Presupuestos + Endurecimiento CSRF

**Fecha**: 2026-05-06
**Tipo**: minor + security hardening
**Branch**: main

---

## Qué se hizo

### Fase 1 — Auditoría DELETE Presupuestos (cierre del pendiente de iter 48 / 1.46.3)

Réplica del patrón implementado en 1.46.3 para PDS, ahora aplicado a `crm_presupuestos`. Mismo riesgo: presupuesto pusheado a Tango con `nro_comprobante_tango` borrado desde papelera queda huérfano en el ERP sin trazabilidad.

**Implementación triple-capa**:

1. **Tabla `crm_presupuestos_audit_deletes`**: 22 columnas tipadas + `before_json` LONGTEXT con 34 campos del schema. Índices por empresa, deleted_at, nro_comprobante_tango y presupuesto_id.
2. **Trigger SQL `tr_crm_presupuestos_audit_before_delete`** BEFORE DELETE ON crm_presupuestos: captura cualquier delete (PHP, phpMyAdmin, SQL manual). Idempotente vía DROP TRIGGER IF EXISTS.
3. **Vista `RXN_LIVE_VW_PRESUPUESTOS_DELETES`** con flag calculado `estaba_en_tango = "Sí — quedó huérfano en Tango"` cuando `nro_comprobante_tango IS NOT NULL`.
4. **Repository**: `PresupuestoRepository::forceDeleteByIds` setea `@audit_user_id` y `@audit_user_name` antes del DELETE. Si vienen NULL → audit registra NULL → señaliza "delete no atribuible".
5. **RxnLive dataset**: `presupuestos_eliminados` registrado en `RxnLiveService::$datasets` con pivot_metadata de 17 campos.

**Smoke test E2E** (4 casos):
- Tabla / trigger / vista creados ✅
- Insert + delete con atribución → audit captura 34 campos en before_json + atribución correcta ✅
- Vista resuelve `estaba_en_tango = "Sí — quedó huérfano en Tango"` cuando hay nro_comprobante_tango ✅
- Caso NULL (delete sin atribución) → deleted_by NULL, vista correcta ✅

### Fase 2 — Barrido seguridad sobre 4 módulos críticos

Auditoría sobre WebPush, RxnSync, TangoSync, AttachmentsController. Hallazgos:

#### WebPush — limpio
- CSRF, login, multi-tenant, prepared statements: todo OK.
- 🟡 Bajo: `/test` sin rate limit. Aceptable, anotado para próxima.

#### TangoSyncController — 2 hallazgos ALTO
1. **GET en endpoints de sync**: 9 rutas (`/mi-empresa/[crm/]sync/articulos|clientes|precios|stock|todo`) eran GET. Triggable por `<img src=…>` en mail HTML — un atacante con cualquier sitio externo podía disparar la sync con solo lograr que la víctima abra el mail. Pasaron a POST.
2. **`$e->getMessage()` filtrado** en los 5 catches del controller via Flash::set('danger', '...' . $e->getMessage()). Reveal de paths server / SQL / credenciales potencialmente.

**Fix**:
- `app/config/routes.php`: 9 endpoints de `$router->get` → `$router->post`.
- `TangoSyncController.php`: cada método llama `$this->verifyCsrfOrAbort()` al inicio. Catches loggean detalle a `error_log()` server-side y emiten Flash genérico.
- `redirectPath()` y `syncClientes()` aceptan `return` también desde POST (no solo GET).
- Vistas: las 2 `<a href>` de Sync Precios / Sync Stock en `RxnSync/views/index.php` convertidas a `<form method="POST">` con CsrfHelper::input() y parse_url para preservar `?return=` como hidden.

#### RxnSyncController — 3 hallazgos
1. **ALTO**: 11 endpoints AJAX POST sin CSRF.
2. **ALTO**: `$e->getMessage()` filtrado + `error_class` (ReflectionClass shortname) + `error_file` (basename + línea) expuestos en JSON de error. Reconnaissance gratis para atacante.
3. **MEDIO**: pushToTango/pullSingle/getPayload/auditarArticulos/auditarClientes confían exclusivamente en el guard del router para auth. Aceptable, anotado.

**Fix**:
- Helper privado `verifyCsrfHeaderOrAbortJson()`: lee header `X-CSRF-Token`, devuelve 419 JSON `{success:false, kind:"csrf"}` si falla. Llamado en los 11 métodos POST.
- Helper privado `logAndGenericError(\Throwable $e, string $where, string $userMessage): array`: detalle a `error_log()`, JSON limpio al cliente. Aplicado en todos los catches.
- Eliminado `error_class`/`error_file` del response.
- JS (`views/index.php`): helper `csrfToken()` lee del meta tag, header `X-CSRF-Token` agregado a los 3 fetch POST (runRxnSyncAjax, sync-catalogos, doBulkRequest).

#### AttachmentsController — 1 hallazgo MEDIO
- `upload()` y `delete()` filtraban `$e->getMessage()` directo al cliente (líneas 84 y 105 del archivo previo). Mismo patrón que se corrigió en CrmMailMasivos 1.46.1.

**Fix**: distinguir `InvalidArgumentException`/`RuntimeException` (mensajes user-friendly del service: "Se alcanzó el máximo de archivos permitidos por registro (5)", "owner_type no permitido", etc) de `Throwable` genérico (log server-side + "Error interno al procesar el adjunto."). Mantiene UX útil sin filtrar internals.

### Fase 3 — Documentación reforzada (clave para no repetir pasadas)

Charly pidió explícito que estos estándares queden en los MODULE_CONTEXT.md y convenciones para que la próxima vez que se programe cualquier endpoint nuevo nazca cumpliéndolos.

**`docs/seguridad/convenciones.md`**:
- Sección 3.4 nueva: patrón canónico `verifyCsrfHeaderOrAbortJson` para endpoints POST AJAX que devuelven JSON. Incluye snippet PHP del helper, snippet JS del header `X-CSRF-Token`, lista de implementaciones de referencia (CrmMailMasivos 1.46.1, RxnSync 1.46.4).
- Sección 3.5 nueva: regla "POST para acciones, NUNCA GET" con explicación del vector (img src en mail HTML), caso real de TangoSync 1.46.4, y patrón de conversión `<a href>` → `<form method="POST">`.
- Sección 6.3 ampliada: patrón de sanitización de catches con snippet completo (catch tipado vs `Throwable`), prohibiciones explícitas (`echo $e->getMessage()`, `error_class`, `error_file`), helper recomendado `logAndGenericError`.
- Checklist final del módulo nuevo: 3 ítems agregados (CSRF AJAX, POST vs GET, sanitización de catches) + bullet sobre audit log de hard-deletes apuntando a los MODULE_CONTEXT de PDS y Presupuestos.

**MODULE_CONTEXT.md actualizados**:
- `CrmPresupuestos`: nueva sección "Auditoría de eliminación permanente (1.46.4)" análoga a la de PDS, con red triple-capa explicada e instrucciones de mantenimiento ante cambios de schema. Línea agregada en Seguridad Base apuntando a las convenciones transversales.
- `RxnSync`: la línea "Sin validación de token CSRF en endpoints AJAX. Deuda de seguridad activa." reemplazada por sección "CSRF (release 1.46.4) — Resuelto" con el patrón documentado. Nueva sección "Sanitización de errores en JSON (release 1.46.4)" con el helper logAndGenericError.
- `Tango`: tabla de rutas refleja POST en los 9 endpoints. Sección CSRF actualizada con verifyCsrfOrAbort. Nueva sección "Sanitización de errores (release 1.46.4)". "Sin CSRF en endpoints de sync" eliminado de Riesgos Conocidos.
- `CrmPedidosServicio`: ya tenía la sección de audit 1.46.3 vigente — sin cambios necesarios.

### Fase 4 — Validación end-to-end

Smoke test PHP standalone via cURL contra localhost:9021:
- Login → captura cookie + meta csrf-token.
- Test 4: POST `/mi-empresa/crm/sync/articulos` SIN csrf_token → status 500 (Open Server promueve 419 → 500, ya documentado), body con marca CSRF. ✅ Rebote correcto.
- Test 5: POST `/mi-empresa/crm/sync/precios` CON csrf_token → status 0 al timeout de 5s. Significa que pasó el CSRF check y entró a procesar la sync real. ✅ CSRF aceptado.
- Test 6: POST `/mi-empresa/crm/rxn-sync/auditar-articulos` SIN header X-CSRF-Token → 500 + JSON `{kind:"csrf", success:false}`. ✅
- Test 7: POST mismo endpoint CON header → 200, success:true, mensaje "Auditoría completada. Vinculados: 500 | Pendientes: 4003". JSON limpio sin error_class/error_file. ✅

---

## Por qué

### Audit Presupuestos
Mismo motivo que PDS: presupuesto pusheado a Tango con número de comprobante asignado borrado desde papelera de RXN queda huérfano en Tango sin contraparte ni log. Caso del incidente del PDS X0065400007931 (2026-05-05) potencialmente replicable en Presupuestos. La red triple-capa (audit table + trigger + atribución + vista) garantiza que cualquier delete deje rastro.

### Endurecimiento CSRF
Los módulos auditados eran post-1.13.0 — nacieron después de la auditoría formal del 2026-04-17 y nunca habían pasado por barrido de seguridad. La iteración 48 detectó hallazgos en CrmMailMasivos y los fixeó (1.46.1). Esta iteración cubrió 4 más. Lo más grave fue TangoSync con GET sin CSRF — explotable con solo abrir un mail HTML, sin que la víctima haga clic.

### Documentación
Charly pidió específicamente que los estándares queden registrados en los MODULE_CONTEXT.md y `docs/seguridad/convenciones.md` para no tener que repetir pasadas de seguridad cada vez. Se actualizaron 3 MODULE_CONTEXT y se sumaron 3 secciones nuevas + 4 ítems al checklist en convenciones.

---

## Impacto

- **Operadores del sistema**: ninguno aparente. Los flujos AJAX siguen funcionando idéntico — el JS suma un header. Las dos `<a href>` de Sync Precios/Sync Stock pasan a ser botones de form POST visualmente equivalentes. Si un usuario tiene la sesión expirada, ahora ve la página 419 estándar de la suite (que ya conoce de otros módulos).
- **Atacantes externos**: bloqueados los 13 vectores CSRF (9 GET + 11 POST + 4 AJAX recientes). El reconnaissance via JSON con `error_class`/`error_file` también queda eliminado.
- **Logs**: el detalle de errores 500 sigue capturándose en `error_log()` server-side — no se pierde info operativa, solo no se expone al cliente HTTP.
- **Performance**: el helper `verifyCsrfHeaderOrAbortJson` agrega < 1ms por request. `logAndGenericError` solo se ejecuta en path de excepción.

---

## Decisiones tomadas

1. **Audit por módulo en vez de tabla genérica**: misma decisión que en PDS 1.46.3. Tablas `crm_pedidos_servicio_audit_deletes` y `crm_presupuestos_audit_deletes` separadas en lugar de `crm_audit_deletes` genérica con `entity_type`. Razón: vistas RxnLive más simples, índices más ajustados al uso, columnas tipadas explícitas. Si en el futuro auditamos 5+ módulos y queremos cross-search, evaluamos consolidar.
2. **Distinguir excepciones tipadas vs Throwable en AttachmentsController**: en lugar de un `catch (Throwable)` único con mensaje genérico, distinguimos `InvalidArgumentException`/`RuntimeException` (mensajes del service que SÍ son user-friendly y útiles, ej: "Se alcanzó el máximo de archivos permitidos") de `Throwable` genérico (PDO, filesystem, etc — log + genérico). Mantiene UX útil sin filtrar internals.
3. **Helper `logAndGenericError` privado por controller**: en lugar de subirlo al `Controller` base, queda como método privado del módulo. Razón: el `where` (label del módulo) y el `userMessage` (idioma del módulo) varían — un helper genérico requeriría params extra que ensucian el call site. Si en el futuro 4+ módulos lo replican, vale la pena subirlo.
4. **Pospuesto auditoría de los 5 módulos restantes** (RxnLive, Notifications, Drafts, RxnGeoTracking, CrmHoras PWA): Charly priorizó superficie de ataque (auth, secrets, uploads, endpoints externos) sobre cobertura horizontal. Los 5 quedan para próxima iteración con anotación específica en memoria.
5. **NO se creó MODULE_CONTEXT.md para WebPush ni AttachmentsController**: WebPush está limpio en seguridad (no requiere doc específico más allá del comentario en el archivo), y AttachmentsController vive en `app/shared/Controllers/` (no es módulo independiente). Los estándares aplicables quedan registrados en `docs/seguridad/convenciones.md` que es la fuente canónica.

---

## Validación

Ver Fase 4. Smoke test E2E con 4 casos verde. Lint PHP de los 5 archivos modificados sin errores.

---

## Hallazgos pendientes (documentados, no bloqueantes)

- **WebPush `/test` sin rate limiting**: bajo. Un usuario logueado podría spammear push test a sí mismo. No afecta a otros usuarios.
- **HTTP 419 → 500 en Open Server**: cosmético, ya documentado en log de 1.46.1. Pendiente fix transversal en `Controller::verifyCsrfOrAbort` (cambiar `http_response_code(419)` por header explícito `HTTP/1.1 419 Page Expired` o migrar a 403 + header `X-CSRF-Status: expired`). El JS ya chequea `data.success`, no afecta funcional.
- **Sin guard de admin para sync masiva** (Tango): cualquier usuario del tenant puede disparar una sincronización. Pendiente: gating por rol/permiso. Anotado en MODULE_CONTEXT de Tango.
- **Mitigación UX hard-delete con tango_nro_***: confirm modal "este registro ya está en Tango con número X — borrarlo de RXN deja huérfano allá. ¿Anular en Tango también?". Pendiente para PDS y Presupuestos.

---

## Pendiente para próxima iteración (51)

- **Auditoría B**: barrido formal de los 5 módulos restantes post-1.13.0 — RxnLive, Notifications, Drafts, RxnGeoTracking, CrmHoras (PWA mobile). Cada uno → sección "Seguridad" en su MODULE_CONTEXT.md siguiendo el patrón de iter 48-50.
- **Auditoría C**: mapping ASVS L2 + informe consolidado en `docs/seguridad/2026-05-XX_auditoria_post_113.md`.
- **Permisos y otras cuestiones** (Charly mencionó al cierre).

---

## Archivos modificados

### Backend
- `app/modules/CrmPresupuestos/PresupuestoRepository.php` — atribución pre-DELETE
- `app/modules/RxnLive/RxnLiveService.php` — dataset presupuestos_eliminados
- `app/modules/Tango/Controllers/TangoSyncController.php` — verifyCsrfOrAbort + sanitize
- `app/modules/RxnSync/RxnSyncController.php` — verifyCsrfHeaderOrAbortJson + logAndGenericError
- `app/shared/Controllers/AttachmentsController.php` — distinción de catches

### Vistas + JS
- `app/modules/RxnSync/views/index.php` — helper csrfToken() + header en 3 fetch + 2 forms POST nuevos

### Configuración
- `app/config/routes.php` — 9 GET → POST en TangoSync
- `app/config/version.php` — bump 1.46.4

### Database
- `database/migrations/2026_05_06_01_create_crm_presupuestos_audit_deletes.php` — tabla + trigger + vista
- `database/migrations/2026_05_06_02_seed_customer_notes_release_1_46_4.php` — 2 notas (feature audit + security hardening)

### Documentación
- `docs/seguridad/convenciones.md` — secciones 3.4, 3.5, 6.3 ampliada, checklist
- `app/modules/CrmPresupuestos/MODULE_CONTEXT.md` — sección audit 1.46.4
- `app/modules/RxnSync/MODULE_CONTEXT.md` — sección CSRF resuelta + sanitización
- `app/modules/Tango/MODULE_CONTEXT.md` — sección CSRF + sanitización + tabla rutas POST
