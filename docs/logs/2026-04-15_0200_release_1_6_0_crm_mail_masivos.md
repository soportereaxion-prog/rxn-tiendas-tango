# Release 1.6.0 — CRM Mail Masivos (módulo completo end-to-end)

## Fecha y tema
2026-04-15 02:00 — Release mayor: módulo completo de envíos masivos de correo electrónico, cinco fases integradas (schema + SMTP → Reportes visuales → Plantillas HTML → Envíos con n8n → Tracking).

## Qué se hizo

### Fase 1 — Cimientos (schema + SMTP per-user)
- 6 migraciones nuevas en `database/migrations/2026_04_14_*`:
  - `00_create_crm_mail_reports.php` — diseños guardados del "diseñador Links".
  - `01_create_crm_mail_templates.php` — plantillas HTML reutilizables con variables.
  - `02_create_crm_mail_smtp_configs.php` — SMTP POR USUARIO (separado del transaccional).
  - `03_create_crm_mail_jobs.php` — cabecera de cada disparo de envío masivo.
  - `04_create_crm_mail_job_items.php` — un row por destinatario con tracking_token único.
  - `05_create_crm_mail_tracking_events.php` — eventos de open/click para analytics.
- Sección "SMTP para Mail Masivos" en `mi_perfil.php` con form completo + botón AJAX "Probar conexión" que usa `MailService::testConnection()` via PHPMailer.
- Metamodelo declarativo en `app/modules/CrmMailMasivos/config/entities.php` con 4 entidades base (CrmClientes, CrmPresupuestos, CrmPresupuestoItems, CrmPedidosServicio) y sus relaciones.

### Fase 2 — Reportes (Query Builder + Diseñador Visual)
- `Services/ReportMetamodel.php` — lee y valida contra `config/entities.php`. Expone entidades/campos/relaciones al frontend.
- `Services/ReportQueryBuilder.php` — traduce el JSON del reporte a SQL seguro validado contra el metamodelo. Placeholders únicos por uso (evita SQLSTATE[HY093] con emulate_prepares=false), inyección automática de `empresa_id` y soft-delete.
- `ReportController` + `ReportRepository` — CRUD completo con endpoints auxiliares `metamodel` (JSON) y `preview` (AJAX primeros 10 destinatarios).
- Diseñador visual "Links" estilo n8n/Crystal Reports en `public/js/mail-masivos-designer.js` + `public/css/mail-masivos-designer.css` (~900 líneas vanilla, sin libs). Nodos draggables, líneas SVG Bezier, filtros dinámicos por tipo de campo, preview en vivo reutilizando el endpoint del backend.

### Fase 3 — Plantillas HTML
- `TemplateController` + `TemplateRepository` con CRUD + endpoints AJAX `availableVars` (extractor) y `previewRender` (renderizador con datos reales).
- Editor side-by-side: textarea HTML a la izquierda, iframe con sandbox vacío (sin permisos, no ejecuta JS del usuario) a la derecha.
- Botonera de variables agrupadas por entidad, extraídas del `config_json.fields` del reporte asociado. Click-to-insert focus-aware (recuerda el último input/textarea focus-ado para saber dónde insertar `{{Entity.field}}`).
- Preview con debounce de 500ms: reusa `ReportQueryBuilder` con `LIMIT 1` para traer el primer row real y hacer el reemplazo. Muestra chips amarillos con "tokens sin valor" si algún placeholder no matchea.

### Fase 4 — Envíos (Jobs + n8n + Monitoreo)
- `JobController` + `JobRepository` — 10 acciones (index, create, monitor, previewRecipients AJAX, store, status AJAX, cancel, processBatch público con token, callback público con token).
- `Services/JobDispatcher.php` — orquesta la creación: valida contexto, ejecuta reporte con `MAX_RECIPIENTS=5000`, dedup por email lowercase, filtra inválidos con `filter_var(FILTER_VALIDATE_EMAIL)`, crea job + items con `bin2hex(random_bytes(24))` por token, POST webhook a n8n.
- `Services/BatchProcessor.php` — el motor de envío:
  - Claim de N items pending (MVP: 1 worker simultáneo aceptable).
  - Abre UNA conexión SMTP por batch con `SMTPKeepAlive=true` (ahorra handshake entre items).
  - Renderiza subject + body con `recipient_data_json` por item.
  - Envía con PHPMailer, `markItem('sent'|'failed'|'skipped')` según resultado.
  - Actualiza contadores del job atómicamente.
  - Chequea `cancel_flag` entre batches → `closeCancelled` marca todos los pending como skipped.
  - Si no quedan pending → `closeCompleted` con `finished_at`.
- **Decisión arquitectónica clave**: n8n ORQUESTA pero NO ENVÍA. El CRM mantiene el control del envío real (PHPMailer con SMTP del usuario). Razón: el node "Send Email" de n8n requiere credenciales pre-configuradas estáticas, lo cual rompe multi-tenant. Con este diseño cada empresa usa sus propias credenciales SMTP de Mi Perfil.
- Worker CLI `tools/process_mail_job.php <job_id>` — standalone, llama `BatchProcessor::processBatch` en loop con `sleep(pause_seconds)`. Útil para testing sin n8n y para fallback ante caída.
- Workflow n8n creado via MCP: "CRM Mail Masivos — Dispatcher" (ID `fTtS4GzJpxxqDDGp`, publicado). 3 nodos: Webhook POST `/crm-mail-masivos` con `responseMode=responseNode`, Respond 202 Accepted inmediato (en paralelo), Code node JS con loop de hasta 500 iteraciones llamando `process-batch` con header `X-RXN-Token` y pause entre batches.
- Pantalla de creación en 5 pasos numerados (reporte/plantilla/SMTP/preview/confirmar+disparar). Monitor con polling 3s, barra de progreso 3-color (verde/rojo/amarillo), 5 stats boxes, botón cancelar con modal de confirmación.

### Fase 5 — Tracking (Opens + Clicks)
- `TrackingController` con 2 endpoints PÚBLICOS sin login protegidos por `tracking_token` único por item:
  - `GET /m/open/{token}.gif` → inserta evento 'open' + devuelve pixel 1x1 transparente (43 bytes base64) con headers `no-cache`.
  - `GET /m/click/{token}?u=<url>` → inserta evento 'click' con `url_clicked` + redirige 302 a la URL destino.
- Validación del redirect: `filter_var(FILTER_VALIDATE_URL)` + whitelist de scheme `http`/`https` → previene open-redirect.
- `BatchProcessor::injectTracking()` reescribe el HTML antes del envío:
  - Reemplaza `<a href="http(s)://...">` por `<a href="{APP_URL}/m/click/{token}?u={encoded}">`.
  - Inyecta `<img src="{APP_URL}/m/open/{token}.gif" 1x1>` antes de `</body>` (o al final si no tiene body tag).
  - Respeta `mailto:`, `tel:`, `#anchor`, `javascript:`, `data:`, relativos.
- Analytics en el monitor: aperturas totales/únicas, clicks totales/únicos, open rate, click rate. Columnas 👁 y 🔗 por destinatario en la tabla.
- `JobRepository::findItemsForJob()` modificado: LEFT JOIN con `crm_mail_tracking_events` + GROUP BY para conteos por item. Nuevo método `findTrackingSummaryForJob()` para los totales.

## Por qué

- El CRM tenía solo mail transaccional (bienvenida, verificación, reseteo). Faltaba una herramienta propia del backoffice para hacer **campañas masivas** a listas de clientes/presupuestos/PDS, sin depender de plataformas externas (Mailchimp, Mailerlite) que cuestan por contacto y no integran con la data propia del CRM.
- La arquitectura soporta **multi-tenant**: cada empresa configura su SMTP, sus reportes y plantillas, y dispara jobs que están aislados por `empresa_id` en toda la cadena. Dos empresas pueden disparar simultáneamente sin colisión.
- La pieza de **seguridad crítica** es el Query Builder: el usuario NO escribe SQL libre. El metamodelo declarativo (`config/entities.php`) es la única superficie alcanzable. Prevent SQL injection by design.
- El tracking cierra el ciclo: no solo mandar, sino saber si llegó, si lo abrieron, si hicieron click — datos estándar de email marketing.

## Impacto

- **Nuevo módulo completo** `/mi-empresa/crm/mail-masivos` con 3 sub-secciones (Reportes, Plantillas, Envíos) y 23 rutas totales.
- Configuración SMTP de envíos masivos en `/mi-perfil` (separada del SMTP transaccional de la empresa).
- Integración n8n productiva: workflow publicado en la instancia `hstgr.cloud`, webhook listo para recibir disparos del CRM.
- 6 migraciones a ejecutar en prod al aplicar el OTA.
- **Env vars nuevas** requeridas en `.env` de prod:
  ```
  APP_URL=https://suite.reaxionsoluciones.com.ar
  N8N_MAIL_MASIVOS_WEBHOOK_URL=https://n8n.srv1045108.hstgr.cloud/webhook/crm-mail-masivos
  N8N_MAIL_MASIVOS_WEBHOOK_TOKEN=7a7534a36d20137e763151e102ec928856f3f157a699d203f7b40ed769d8ff15
  N8N_CALLBACK_TOKEN=7a7534a36d20137e763151e102ec928856f3f157a699d203f7b40ed769d8ff15
  ```

## Decisiones tomadas

- **Bump mayor a 1.6.0**: corresponde por feature nueva grande (módulo completo con 6 tablas, 4 controllers, workflow n8n, 3 fases de UI). No es refactor ni fix.
- **n8n orquesta, CRM envía**: por multi-tenant (SMTP por usuario) y por evitar hardcoding de credenciales en nodos n8n.
- **Query Builder declarativo por metamodelo**: no SQL libre nunca. Solo campos/entidades/operadores explícitamente declarados son alcanzables.
- **Tracking token como shared secret bearer**: 48 chars hex (bin2hex(random_bytes(24))). Sin auth adicional en los endpoints públicos — el hecho de tener el token es prueba de haber recibido el mail.
- **CLI worker como fallback de n8n**: si la instancia n8n cae, Charly puede seguir procesando jobs con `tools/process_mail_job.php <job_id>`. Ambos caminos convergen en `BatchProcessor`.
- **Seeds de prueba en `tools/seed_*.php`**: idempotentes, creados para facilitar testing repetido sin duplicar datos. Se pueden correr N veces sin romper.

## Validación

- **Fase 4 validada end-to-end en local** disparando `tools/process_mail_job.php 1`: mail real a `cyaciofani@e-reaxion.com.ar` enviado correctamente. Job #1 pasó a `completed` con 1/1 enviado, item marcado `sent` con timestamp `2026-04-14 22:36:35`.
- **Fase 5 validada** en test unitario de `injectTracking` con sample HTML: links http/https reescritos, mailto/anchor/relativos intactos, pixel inyectado antes de `</body>` correctamente.
- **Fase 5 end-to-end en prod** queda pendiente del deploy (el pixel y los clicks necesitan URL pública alcanzable).

## Pendiente para próximas iteraciones (Fase 4b / 6)

- **Adjuntos** con URLs firmadas + expiración corta (Fase 4b).
- **Scheduling** (programar envío a futuro) — requiere cron en n8n o en el CRM.
- **Cifrado real** de `smtp_password_encrypted` con `App\Core\Crypto` cuando se implemente ese servicio transversal.
- **Rate limit mejorado** con lock pesimista (SELECT FOR UPDATE) si se necesita más de 1 worker simultáneo.
- **Bounces** (parsing de respuestas SMTP) — más complejo, requiere IMAP reverso.
