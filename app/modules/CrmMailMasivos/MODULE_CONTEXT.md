# MODULE_CONTEXT: CrmMailMasivos

## Propósito

Módulo de envíos masivos de correo electrónico del CRM. Permite:

1. Diseñar **Reportes** de destinatarios con un editor visual estilo "Links" de Crystal Reports (sin SQL libre — todo sobre un metamodelo declarativo y seguro).
2. Crear **Plantillas** HTML con variables basadas en los campos del reporte, con preview en vivo.
3. Disparar **Envíos Masivos** que corren de forma autónoma en n8n (pendiente Fase 4), con pausas, batch, cancelación y tracking de aperturas/clicks.

## Alcance (Fase 6, vigente)

- **Todo lo anterior** + **reportes de contenido broadcast** (Fase 6):
  - Nueva entidad `CustomerNotes` en el metamodelo (tabla global `customer_notes`) — sin `empresa_scope`, sin `mail_field`, sin `relations`. Actúa como fuente de CONTENIDO, no de destinatarios.
  - Un reporte cuya `root_entity` es una entidad de contenido se llama "reporte de contenido". No puede usarse como fuente de destinatarios (no tiene mail_field) — su rol es iterar filas para producir un bloque HTML.
  - Al disparar un envío, el admin puede (opcionalmente) elegir un reporte de contenido además del reporte de destinatarios y la plantilla. El `JobDispatcher` ejecuta el reporte de contenido vía `BlockRenderer`, que despacha al `RowRenderer` correspondiente (`CustomerNotesRenderer` para `CustomerNotes`), y el HTML concatenado reemplaza el placeholder `{{Bloque.html}}` del template ANTES de guardar el `body_snapshot`. El `BatchProcessor` sigue igual — solo resuelve variables per-row.
  - Los `RowRenderers` producen HTML email-safe (tablas inline, sin flex/grid, sin JS, sin fonts externas). `CustomerNotesRenderer` pinta cada nota como card con color e ícono de categoría (feature/mejora/seguridad/performance/fix_visible) y fecha en formato es-AR.
  - Columna nueva `content_report_id INT NULL` en `crm_mail_jobs` — trazabilidad del bloque usado.
  - **Reutilizabilidad**: el mismo pipeline sirve para listas de precios, promos, productos destacados, etc. Solo hay que declarar la entidad en `config/entities.php` y crear un renderer en `Services/RowRenderers/`.
- **Edición del contenido**: las `customer_notes` se crean directo en DB durante el ritual de cierre de sesión (no hay CRUD dedicado por ahora — YAGNI hasta que aparezca la necesidad). El editor visual del reporte hace de "CRUD de lectura/filtrado": el admin crea un reporte sobre `CustomerNotes`, filtra por categoría/estado/fecha, y ese reporte queda guardado como selección reutilizable.

## Alcance (Fase 5)

- Tracking de aperturas y clicks.
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
  - `Services/ReportQueryBuilder.php`: traduce JSON del reporte a SQL safe (prepared statements). Valida cada entidad/campo/operador contra el metamodelo antes de construir. Acepta flag `$requireMailTarget` — en `false` permite reportes de contenido sin destinatario. Reutilizado por `TemplateController::previewRender` para obtener el row de muestra.
  - `Services/BlockRenderer.php` (Fase 6): dispatcher para reportes de contenido. Carga el reporte, ejecuta el query con `requireMailTarget=false`, y despacha al `RowRenderer` correspondiente según la `root_entity`. El map `RENDERERS` define qué entidades son "de contenido" (expuestas vía `knownContentEntities()`).
  - `Services/RowRenderers/RowRenderer.php` (Fase 6): interfaz — `renderRows(array $rows): string`.
  - `Services/RowRenderers/CustomerNotesRenderer.php` (Fase 6): implementación para novedades; produce cards HTML con color/ícono por categoría.
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
- `crm_mail_jobs`, `crm_mail_job_items`, `crm_mail_tracking_events`: envíos y tracking (Fase 4/5). En Fase 6 suma columna `content_report_id INT NULL`.
- `customer_notes` (Fase 6): novedades del producto en lenguaje de usuario final. Tabla GLOBAL (sin `empresa_id`). Campos: `title`, `body_html`, `category`, `version_ref`, `status` (`draft`|`published`), `published_at`.

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

## Reglas Editoriales para `customer_notes` (Fase 6)

Las novedades viajan a clientes finales — el lenguaje tiene que estar cuidado:

- **Prohibido**: nombres de archivos, endpoints, tablas, librerías, versiones de dependencias, CVEs, descripciones de bugs que revelen el vector de ataque.
- **Seguridad**: lenguaje de **capacidad**, no de **defecto**. ✅ "Reforzamos el aislamiento multi-empresa con controles de nivel aeroespacial". ❌ "Parcheamos IDOR en el endpoint de pedidos".
- **Foco**: beneficio para el cliente — qué puede hacer hoy que ayer no, o qué está más seguro/rápido/prolijo.
- Los drafts los redacta Lumi durante el ritual de cierre de sesión (a partir del log de `docs/logs/`), traduciendo lo técnico al idioma del cliente. Charly revisa y publica.

## Backlog del Módulo (no implementado todavía)

- **Override editable de renderers** (Opción 2 discutida): sumar campo opcional `row_template_html` a `crm_mail_reports` para que el admin pueda override el HTML por-fila desde el editor de plantillas. Se evalúa cuando aparezca una entidad de contenido donde el default no alcance.
- **"Plantillas de bloque" como entidad propia** (Opción 3): CRUD separado de templates por entidad, combinable al dispatch. Overkill hoy; considerar cuando haya 3+ variantes visuales por entidad.
- **CRUD UI de `customer_notes`**: hoy se edita vía INSERT directo en DB durante el cierre de sesión. Considerar si el volumen de notas crece o si se delega a otro rol.
- **Preview del bloque en el UI de crear-envío**: mostrar el HTML renderizado del reporte de contenido antes de disparar. Hoy se confía en el preview del reporte + el criterio editorial.

## Checklist Post-Cambio

1. Verificar que `ReportQueryBuilder` rechaza entidades/campos no declarados con excepción explícita (no fallback silencioso).
2. Probar preview con filtros de varios tipos (string, int, date, LIKE, IN).
3. Verificar aislamiento cross-empresa: crear reporte en empresa A, entrar como user de empresa B, confirmar 404/403.
4. Soft-delete de reporte: confirmar que queda out del listado pero accesible via admin si hace falta (Fase 2b+).
5. **Fase 6**: crear un reporte de contenido sobre `CustomerNotes`, asociarlo a un envío, verificar que `{{Bloque.html}}` del template se reemplaza por el HTML de las notas publicadas en el `body_snapshot` del job (mirando la DB).
6. **Fase 6**: si se agrega una entidad de contenido nueva, verificar que su `RowRenderer` produce HTML válido en Gmail, Outlook web y Apple Mail antes de merge.
