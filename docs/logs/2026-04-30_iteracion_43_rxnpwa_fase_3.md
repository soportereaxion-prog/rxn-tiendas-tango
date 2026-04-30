# Iteración 43 — RXN PWA Fase 3 (release 1.33.0)

**Fecha**: 2026-04-30
**Build**: 20260430.4
**Tema**: Cierre del Bloque C de la PWA mobile de Presupuestos: cola de sync con reconciliación + envío a Tango desde mobile.

## Qué se hizo

### Backend

- **Migración**: nueva columna `crm_presupuestos.tmp_uuid_pwa VARCHAR(50) NULL UNIQUE` (`database/migrations/2026_04_30_02_alter_crm_presupuestos_add_tmp_uuid_pwa.php`). Idempotencia del sync mobile.
- **Service nuevo `RxnPwaSyncService`**: mapea el draft JSON del cliente al payload de `PresupuestoRepository::create()`, resuelve catálogos por `código` reusando `CommercialCatalogRepository::findOption`, calcula totales server-side. Idempotente por `findByTmpUuidPwa` con manejo de race condition.
- **Controller**: 3 endpoints POST nuevos:
  - `/api/rxnpwa/presupuestos/sync` — header + items, devuelve `id_server`.
  - `/api/rxnpwa/presupuestos/{id}/attachments` — multipart, reusa `AttachmentService` (`owner_type='crm_presupuesto'` ya whitelisteado).
  - `/api/rxnpwa/presupuestos/{id}/emit-tango` — reusa `PresupuestoTangoService::send()` (mismo path que web).
- **Repository**: `tmp_uuid_pwa` sumado al INSERT + `buildHeaderPayload` + ALTER defensivo. Método `findByTmpUuidPwa`. Update `unset` el placeholder para no pisarlo post-creación.

### Frontend

- **`rxnpwa-sync-queue.js`** (nuevo): cola con flujo 2-step + backoff exponencial 5 reintentos (1s/2s/4s/8s/16s) + emit a Tango + Background Sync registration + pub/sub para reactividad de UI.
- **`rxnpwa-form-sync.js`** (nuevo): wire-up de la sección "Enviar al servidor" del form mobile. Estados visibles + botones Sincronizar/Tango gateados por status + red.
- **`rxnpwa-shell-drafts.js`** (rewrite): cards interactivas con acciones contextuales por estado + sección "Cola de envío" con resumen por bucket + badge de red.
- **`rxnpwa-form.js`**: expone `window.RxnPwaForm.flushSave()` para que el form-sync garantice persistencia antes de encolar.
- **Vistas**: `presupuesto_form.php` y `presupuestos_shell.php` reemplazaron los placeholders Fase 3 con la UI real.
- **SW**: bumpeado a `rxnpwa-v3` con handler `sync` para Background Sync API. Web Push y resto del SW intactos.

### Routing y assets

- 3 rutas POST nuevas en `routes.php`.
- Script `tools/generate_rxnpwa_icons_from_source.php` para regenerar 192/512 desde `public/icons/rxnpwa-source.png` con safe-area 12%. **Pendiente**: Charly tiene que dejar el PNG fuente con la estrella RXN final.

## Por qué

Bloqueante #1 del crecimiento del módulo Presupuestos: los vendedores en campo no podían cotizar offline y emitir a Tango sin volver a la oficina. Cierra el roadmap PWA original (3 fases en 2 sesiones) y deja a la app **funcionalmente completa** para campo.

## Decisiones P0 (con Charly)

1. **Tango desde mobile = SÍ, condicional a red**. Botón "Enviar a Tango" en form y cards. Si offline, deshabilitado con tooltip. Si online y `synced`, habilita y dispara el endpoint.
2. **Endpoint shape = 2-step**. POST sync (header+items) → POST attachments uno por uno. Idempotencia por `tmp_uuid_pwa` (header) + `attachment_uuid` futuro (sino, server detecta duplicado por orig_name + size + tmp_uuid si fuera necesario; hoy se confía en que el cliente NO reintente attachments ya marcados como `uploaded`).
3. **Política Tango**: el draft tiene que estar `synced` server-side primero. Sin id_server no hay Tango. El usuario decide manualmente cuándo emitir.
4. **Background Sync API + fallback `online` event** porque iOS Safari no soporta el primero.
5. **No CSRF token explícito por ahora**, hardening pendiente. Cookie de sesión `SameSite` cubre el caso típico.

## Validación

- ✅ Migración corrió OK en local.
- ✅ Lint PHP OK (`php -l`) en los 4 archivos backend.
- 🔲 Smoke test end-to-end mobile real: crear draft offline → recuperar red → sincronizar → enviar a Tango. Pendiente del rey.
- 🔲 Smoke test "matar el wifi en medio del upload" para verificar que el reintento desde el attachment 3 de 5 funciona sin re-crear cabecera.

## Pendiente (post-release)

- **Íconos finales**: que Charly drope `public/icons/rxnpwa-source.png` y corra `tools/generate_rxnpwa_icons_from_source.php`.
- **CSRF en endpoints POST**: sumar header `X-CSRF-Token` desde el meta `csrf-token` del shell.
- **Rate limiting** en `/sync` y `/attachments`.
- **Eliminación / GC de drafts ya sincronizados** en IndexedDB (tras N días o botón explícito).
- **Updates de drafts ya `synced`**: hoy el form se puede editar pero no hay flujo "re-sync con cambios". Decisión a charlar cuando aparezca el caso real.
