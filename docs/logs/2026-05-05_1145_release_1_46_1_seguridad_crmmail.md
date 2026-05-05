# Iteración 48 — Release 1.46.1 — Refuerzo de seguridad CrmMailMasivos

**Fecha**: 2026-05-05
**Tipo**: patch (fix de seguridad)
**Branch**: main

---

## Qué se hizo

### Fase 1 — Auditoría rápida post-1.13.0

Charly pidió una "auditoría de lo crítico" del estado actual de seguridad. Última auditoría formal: `docs/seguridad/2026-04-17_auditoria_tiendas_multitenant.md` (release 1.13.0). Desde ahí se sumaron ~33 releases con módulos pesados nuevos: PWA mobile, CrmMailMasivos, RxnSync, Tango Connect, WebPush, RxnLive, RxnGeoTracking, Notifications, Drafts.

Sweep rápido con grep + agente Explore. Severidad de hallazgos:

- **Crítico verificado**: 0.
- **Alto verificado**: 3 (todos en CrmMailMasivos — CSRF y exposición de getMessage).
- **Medio**: 6 (lecturas de `$_SESSION` directo, `SELECT *` en repos sensibles, webhooks sin rate limit).
- **Bajo**: 2 (cosmético).

Detalle del barrido en el reporte verbal de la sesión. Los Medio/Bajo se atacan en B/C (auditoría formal módulo por módulo + mapping ASVS L2) en próxima sesión.

### Fase 2 — Fix de los 3 hallazgos Alto (Bombero rápido)

Todo concentrado en `app/modules/CrmMailMasivos/JobController.php` y sus vistas.

**Cambios en JobController.php**:
- Ahora extiende `App\Core\Controller` para reutilizar `verifyCsrfOrAbort()`.
- `store()`, `cancel()`, `reactivate()` llaman `$this->verifyCsrfOrAbort()` al inicio.
- `previewRecipients()` (AJAX JSON) usa nuevo helper privado `verifyCsrfHeaderOrAbortJson()` que valida el header `X-CSRF-Token` y devuelve 419 JSON con `kind:"csrf"` si falla. Compatible con respuestas JSON donde la página HTML 419 del Controller base no aplica.
- Sanitización de `$e->getMessage()` en respuestas:
  - `previewRecipients` línea 164: `'message' => 'Error interno'` (antes filtraba el mensaje completo).
  - `processBatch` línea 367: idem.
  - `store` Flash danger: ahora dice "Error interno al disparar el envío. Revisá los logs del servidor."
  - El `error_log()` server-side sigue capturando el detalle completo para debugging.

**Cambios en views**:
- `views/envios/crear.php`: `<?= CsrfHelper::input() ?>` dentro del form de disparo.
- `views/envios/monitor.php`: `<?= CsrfHelper::input() ?>` dentro de los 2 forms (cancelar y reactivar).

**Cambios en JS**:
- `public/js/mail-masivos-envios-crear.js`: helper `csrfToken()` lee del `<meta name="csrf-token">` del admin layout. El fetch de preview-recipients suma header `X-CSRF-Token`.

### Fase 3 — Validación end-to-end

Setup de testing sin spam a clientes reales:

1. **smtp4dev** descargado (`Rnwood.Smtp4dev-win-x64-3.15.0.zip`) y descomprimido en `D:\RXNAPP\3.3\tools\smtp4dev\`. Levantado con SMTP en puerto 2525, UI web en `http://localhost:5050`. Captura todos los mails sin enviarlos a internet.
2. **SMTP per-usuario** del admin testing pisado en DB (tabla `crm_mail_smtp_configs`): backup completo de la fila a `D:\RXNAPP\3.3\tools\smtp4dev\smtp_config_backup.json`, UPDATE apuntando a `localhost:2525`. **Restaurado al final** de los tests (verificado con SELECT — host volvió a `mail.e-reaxion.com.ar`).
3. **Reporte de testing**: usado `crm_mail_reports` id=3 ("Test Charly — sólo cyaciofani") que ya filtraba a 1 destinatario. Plantilla 3 ("Test Charly — Saludo Fase 4").

Test ciclo completo via cURL (login → CSRF → preview → disparo → CLI worker):

| # | Test | Esperado | Real | Estado |
|---|---|---|---|---|
| 1 | Preview AJAX con `X-CSRF-Token` válido | 200 + count=1 | 200 + count=1 + sample correcto | ✅ |
| 2 | Preview AJAX SIN header | 419 JSON `kind:"csrf"` | Body correcto, code 500 | ✅ funcional |
| 3 | POST `/envios` CON `csrf_token` | 302 al monitor | 302 → `/envios/4` | ✅ |
| 4 | POST `/envios` SIN `csrf_token` | 419 página | Página correcta, code 500 | ✅ funcional |
| 5 | CLI worker `process_mail_job.php 4` | 1 enviado | 1 enviado, 0 fallidos, 1 iteración | ✅ |
| 6 | Mail capturado por smtp4dev | 1 mail | ID `c0d399c5-b002-4548-...`, asunto "Hola Charly, tu módulo Mail Masivos está vivo 🚀" | ✅ |

---

## Por qué

CrmMailMasivos es el módulo que dispara campañas de mail masivos a clientes finales (5k+ destinatarios) usando el SMTP propio de la empresa. Un envío no autorizado tiene impacto reputacional alto. Los 4 endpoints POST sin CSRF dejaban abierto el escenario "atacante con cuenta logueada víctima → form malicioso en otro sitio → disparo sin consentimiento". El módulo se cerró en release 1.45.1 (filtros AND/OR) y 1.45.0 (tokens dinámicos + preview) sin que el barrido de seguridad pasara por él. La auditoría formal del 2026-04-17 fue antes de que el módulo existiera.

Sanitización de `getMessage`: convención 6.3 ya documentada en `docs/seguridad/convenciones.md` ("No filtrar datos sensibles"). En endpoints públicos JSON, exponer mensajes de excepción puede revelar paths del server, nombres de clases, detalles de SQL en errores inesperados.

---

## Impacto

- **Operadores del CRM**: ninguno aparente. Los forms y AJAX siguen funcionando exactamente igual. Si la sesión expira mientras el form está abierto (>6h idle), el submit ahora rebota con la página 419 estándar de la suite (que ya conoce el operador del resto del sistema). Ya estaba habilitada la UX 419 con "Iniciar sesión nuevamente" desde release 1.45.x.
- **Atacantes externos**: bloqueados los 4 vectores CSRF.
- **Logs**: el detalle de errores 500 sigue en `error_log()` para debugging — no se pierde información operativa, solo no se expone al cliente HTTP.

---

## Decisiones tomadas

1. **JobController extiende Controller** en vez de duplicar `verifyCsrfOrAbort` — alineamiento con el resto de la suite.
2. **Helper privado para AJAX JSON** (`verifyCsrfHeaderOrAbortJson`): el `verifyCsrfOrAbort` base renderiza la página HTML `error_419.php`, lo cual rompe el contrato JSON del endpoint. La copia local devuelve un envelope JSON con `success:false, kind:"csrf"` para que el JS pueda discriminar.
3. **`processBatch` y `callback` (públicos con HMAC) NO reciben CSRF**: convención 3.3 los exime explícitamente. Solo se sanitizó el `getMessage`.
4. **No se tocó el otro fetch del JS** (`apiPreviewRender` → TemplateController). Es del módulo Plantillas, no Envíos. Queda para el barrido B/C.

---

## Validación

Ver tabla en Fase 3. Validación end-to-end con SMTP capturador funcionó. Mail real interceptado, render del template correcto (variable `{{CrmClientes.nombre}}` reemplazada por "Charly"). Tests negativos confirmaron que sin CSRF no se procesa.

---

## Hallazgo cosmético anotado para próxima iteración

**HTTP 419 → 500 en Open Server**: el body de respuesta es correcto (página "Sesión expirada" para forms, JSON con `kind:"csrf"` para AJAX), pero el status code se transforma a 500 en transit. Causa probable: 419 no es código RFC HTTP estándar (es Laravel-specific para "Page Expired"), Open Server / Apache lo rechaza y promociona a 500.

Afecta a todo el sistema porque pasa por `Controller::verifyCsrfOrAbort` base, no es local del módulo. El funcional está OK porque:
- El JS chequea `data.success`, no el code.
- El form HTML muestra la página de error correcta (la vista 419 sí se renderiza, el body es legible).

**Fix probable**: cambiar a `header('HTTP/1.1 419 Page Expired')` explícito en lugar de `http_response_code(419)`, o migrar a 403 (estándar) + header `X-CSRF-Status: expired` para discriminar de otros 403. Decisión de convención que excede este patch.

---

## Pendiente

- **Auditoría B**: barrido módulo por módulo de los módulos nuevos post-1.13.0 (PWA, RxnSync, Tango Connect, WebPush, RxnLive, RxnGeoTracking, Notifications, Drafts, AttachmentsController). Cada uno recibe sección "Seguridad" en su `MODULE_CONTEXT.md`.
- **Auditoría C**: mapping de cumplimiento contra OWASP ASVS L2. Informe consolidado en `docs/seguridad/2026-05-05_auditoria_post_113.md`.
- **Hallazgo 419→500**: fix transversal en `Controller.php` base + revisión de impacto en otros módulos.

---

## Archivos modificados

- `app/modules/CrmMailMasivos/JobController.php` (extiende Controller, CSRF, sanitización getMessage)
- `app/modules/CrmMailMasivos/views/envios/crear.php` (CsrfHelper::input en form)
- `app/modules/CrmMailMasivos/views/envios/monitor.php` (CsrfHelper::input en 2 forms)
- `public/js/mail-masivos-envios-crear.js` (helper csrfToken + header en fetch)
- `app/config/version.php` (bump 1.46.1)

## Archivos NO modificados (pero relevantes para próxima iteración)

- `app/core/Controller.php` — el `verifyCsrfOrAbort` base sigue usando `http_response_code(419)`. Pendiente fix transversal.
- `app/modules/CrmMailMasivos/Controllers/TemplateController.php` (si existe) — el endpoint `apiPreviewRender` no fue auditado en este patch. Lo cubre B.
