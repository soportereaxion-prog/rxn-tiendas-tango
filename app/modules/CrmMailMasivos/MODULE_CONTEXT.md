# MODULE_CONTEXT: CrmMailMasivos

## Propósito

Módulo de envíos masivos de correo electrónico del CRM. Permite:

1. Diseñar **Reportes** de destinatarios con un editor visual estilo "Links" de Crystal Reports (sin SQL libre — todo sobre un metamodelo declarativo y seguro).
2. Crear **Plantillas** HTML con variables basadas en los campos del reporte, con preview en vivo.
3. Disparar **Envíos Masivos** que corren de forma autónoma en n8n (pendiente Fase 4), con pausas, batch, cancelación y tracking de aperturas/clicks.

## Alcance (Fase 5, vigente)

- **Todo lo anterior** + tracking de aperturas y clicks.
- Endpoints públicos sin login protegidos por `tracking_token` único por item:
  - `GET /m/open/{token}.gif` → registra apertura + devuelve pixel 1x1 transparente.
  - `GET /m/click/{token}?u=<url>` → registra click + redirige a la URL (valida scheme http/https para evitar open-redirect).
- `BatchProcessor` reescribe el HTML antes de enviar: reemplaza `<a href="http(s)://...">` por la URL de redirect, e inyecta el pixel al final del body. Links tipo `mailto:`, `tel:`, `#anchor`, `javascript:`, `data:` y relativos quedan intactos.
- Monitor del job muestra panel de tracking: aperturas totales / únicas, clicks totales / únicos, open rate y click rate.
- Tabla de destinatarios en el monitor tiene columnas 👁 (opens) y 🔗 (clicks) por item.

## Alcance (Fase 4)

- Landing del módulo con 3 tarjetas: Envíos / Reportes / Plantillas — TODAS activas.
- CRUD completo de Reportes con diseñador visual "Links" (Fase 2b).
- CRUD de Plantillas HTML con editor side-by-side + botonera de variables + preview en vivo (Fase 3).
- CRUD de Envíos:
  - Pantalla de creación: elegir reporte + plantilla + preview de destinatarios + confirm + disparo.
  - Pantalla de monitoreo con polling de 3 seg: barra de progreso por estado, contadores, lista de items.
  - Cancelación via `cancel_flag` chequeado entre batches.
- Motor de envío:
  - `Services/JobDispatcher`: valida contexto, ejecuta reporte, crea job + items con tracking_token único, dispara webhook a n8n.
  - `Services/BatchProcessor`: procesa N items pending con PHPMailer + SMTP del usuario (reutiliza conexión SMTP entre items del batch via SMTPKeepAlive), renderiza templates, update estado y contadores.
- Worker standalone: `tools/process_mail_job.php <job_id>` procesa un job completo sin depender de n8n (útil para testing y fallback).
- Endpoint `POST /envios/process-batch` (token-protected) para que n8n llame en loop con pausa entre invocaciones.

## Piezas Principales

- **Controladores:**
  - `MailMasivosDashboardController.php`: landing del módulo (3 cards).
  - `ReportController.php`: CRUD de reportes + preview + metamodel endpoint.
  - `TemplateController.php`: CRUD de plantillas HTML + `availableVars` (extractor de variables del reporte) + `previewRender` (renderizador con datos de muestra).
- **Repositorios:**
  - `ReportRepository.php`: queries contra `crm_mail_reports`.
  - `TemplateRepository.php`: queries contra `crm_mail_templates` + helpers para listar reportes disponibles.
- **Servicios:**
  - `Services/ReportMetamodel.php`: lee y valida contra `config/entities.php`. Expone entidades, campos y relaciones al frontend.
  - `Services/ReportQueryBuilder.php`: traduce JSON del reporte a SQL safe (prepared statements). Valida cada entidad/campo/operador contra el metamodelo antes de construir. Reutilizado por `TemplateController::previewRender` para obtener el row de muestra.
- **Configuración:**
  - `config/entities.php`: metamodelo declarativo. Sólo las entidades/campos acá listados son alcanzables. **Seguridad by design**: prevent SQL injection y acceso a tablas sensibles.
- **Vistas (`views/`):**
  - `dashboard.php`: landing con 3 cards.
  - `reportes/index.php`: listado de reportes guardados.
  - `reportes/designer.php`: diseñador visual "Links" (Fase 2b).
  - `reportes/form.php`: form crudo legacy (backup de Fase 2a).
  - `plantillas/index.php`: listado de plantillas con reporte asociado.
  - `plantillas/editor.php`: editor side-by-side HTML ↔ preview con botonera de variables.
- **Assets frontend:**
  - `public/css/mail-masivos-designer.css` + `public/js/mail-masivos-designer.js`: diseñador de reportes.
  - `public/css/mail-masivos-template-editor.css` + `public/js/mail-masivos-template-editor.js`: editor de plantillas.

## Rutas y Pantallas

- `/mi-empresa/crm/mail-masivos`: (GET) landing con las 3 tarjetas.
- `/mi-empresa/crm/mail-masivos/reportes`: (GET) listado de reportes.
- `/mi-empresa/crm/mail-masivos/reportes/crear`: (GET/POST) crear nuevo reporte.
- `/mi-empresa/crm/mail-masivos/reportes/{id}/editar`: (GET) form de edición.
- `/mi-empresa/crm/mail-masivos/reportes/{id}`: (POST) actualizar reporte.
- `/mi-empresa/crm/mail-masivos/reportes/{id}/eliminar`: (POST) soft-delete.
- `/mi-empresa/crm/mail-masivos/reportes/preview`: (POST JSON) preview primeros 10 destinatarios.
- `/mi-empresa/crm/mail-masivos/reportes/metamodel`: (GET JSON) devuelve el metamodelo completo (para el diseñador visual).
- `/mi-empresa/crm/mail-masivos/plantillas`: (GET) listado de plantillas.
- `/mi-empresa/crm/mail-masivos/plantillas/crear`: (GET/POST) crear plantilla.
- `/mi-empresa/crm/mail-masivos/plantillas/{id}/editar`: (GET) editor side-by-side.
- `/mi-empresa/crm/mail-masivos/plantillas/{id}`: (POST) actualizar plantilla.
- `/mi-empresa/crm/mail-masivos/plantillas/{id}/eliminar`: (POST) soft-delete.
- `/mi-empresa/crm/mail-masivos/plantillas/available-vars/{reportId}`: (GET JSON) variables disponibles del reporte para la botonera.
- `/mi-empresa/crm/mail-masivos/plantillas/preview-render`: (POST JSON) renderiza plantilla con el primer row del reporte asociado.

## Persistencia

- `crm_mail_reports`: reportes guardados. `config_json` contiene la definición completa del diseño.
- `crm_mail_templates`: plantillas HTML (Fase 3).
- `crm_mail_smtp_configs`: SMTP por usuario (ya implementado en Fase 1, vive en `/mi-perfil`).
- `crm_mail_jobs`, `crm_mail_job_items`, `crm_mail_tracking_events`: envíos y tracking (Fase 4/5).

## Dependencias e Integraciones

- `App\Core\Context` para aislamiento multiempresa (obligatorio).
- `App\Modules\Auth\AuthService` para permisos.
- Metamodelo referencia tablas: `crm_clientes`, `crm_presupuestos`, `crm_presupuesto_items`, `crm_pedidos_servicio`.
- n8n (Fase 4): instancia `https://n8n.srv1045108.hstgr.cloud` con un workflow dedicado que va a leer/escribir `crm_mail_jobs` y `crm_mail_job_items`.

## Reglas Operativas y Seguridad (Política Base)

- **Aislamiento Multiempresa:** Toda query contra `crm_mail_reports` (y las tablas hermanas) DEBE filtrar por `Context::getEmpresaId()`. El Query Builder que ejecuta los reportes también DEBE inyectar `empresa_id = ?` en cada entidad del metamodelo que tenga `empresa_scope => true`.
- **SQL Injection Prevention:**
  - Ninguna query se construye con concatenación de input del usuario.
  - El Query Builder **sólo acepta** entidades/campos/operadores declarados en el metamodelo.
  - Los valores de filtros van siempre como placeholders (`:param`).
  - Los identificadores de tabla/columna se whitelist-matchean con el metamodelo antes de escribirse en SQL.
- **Acceso:** Requiere login. No expone datos cross-tenant.
- **Permisos:** Por ahora requiere login. Si en el futuro se necesita acotar a ciertos roles (ej. sólo admin pueden crear envíos), se agrega en el controller.
- **CSRF:** Los formularios POST siguen el estado general del proyecto (deuda técnica conocida que se resolverá transversalmente).
- **Preview:** El endpoint de preview ejecuta SELECT contra la DB. Sólo usuarios logueados de la empresa pueden hacerlo. No se filtra por rol porque el preview es read-only y respeta `empresa_scope`.

## Riesgos y Sensibilidad

- **Inyección por metamodelo mal declarado:** Si en `config/entities.php` se declara una tabla sensible (ej. `usuarios`), el Query Builder la va a exponer. Revisar siempre antes de agregar una entidad.
- **Previews costosos:** Un reporte sin filtros sobre una tabla grande puede tardar. El preview se fija en `LIMIT 10` para mitigar; los envíos reales (Fase 4) tendrán un `LIMIT` duro más alto y paginado.
- **config_json inmutable post-envío:** Una vez que un reporte se usa en un envío disparado, cambiarlo podría romper jobs en curso. En Fase 4 evaluar snapshot del config en el job o prohibir edición durante running.

## Checklist Post-Cambio

1. Verificar que `ReportQueryBuilder` rechaza entidades/campos no declarados con excepción explícita (no fallback silencioso).
2. Probar preview con filtros de varios tipos (string, int, date, LIKE, IN).
3. Verificar aislamiento cross-empresa: crear reporte en empresa A, entrar como user de empresa B, confirmar 404/403.
4. Soft-delete de reporte: confirmar que queda out del listado pero accesible via admin si hace falta (Fase 2b+).
