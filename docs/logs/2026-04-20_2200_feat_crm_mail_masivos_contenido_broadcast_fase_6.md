# 2026-04-20 · Fase 6 CrmMailMasivos — Contenido broadcast en envíos masivos

## Fecha y tema

Sesión dedicada a diseñar e implementar el mecanismo de "reportes de contenido broadcast" sobre el módulo `CrmMailMasivos`. El objetivo: poder seleccionar filas de una tabla (arrancando con novedades de producto para clientes finales) y renderizarlas como bloques HTML dentro del cuerpo de un envío masivo, sin romper el builder relacional existente.

Cierre de sesión con feature **implementada, commiteada, migraciones ejecutadas en local y seed sembrado**. Charly decidió al cierre bumpear igual y disparar OTA (la valida post-deploy, confía en lo implementado). Release empaquetada como **1.17.0 / build 20260420.5**.

**Nueva regla establecida al cierre**: a partir de esta release, cada bump de versión genera su propia migración de seed en `customer_notes` para que la data viaje en el OTA junto con el código. La regla quedó documentada en `CLAUDE.md` del proyecto.

## Qué se hizo

### Discovery y alineamiento arquitectónico
1. Exploración del módulo (ReportMetamodel, ReportQueryBuilder, JobDispatcher, BatchProcessor, designer JS).
2. Discusión de 3 modelos posibles:
   - **Modelo A (elegido)** — 2 reportes por envío (destinatarios + contenido), combinados en el form de "Nuevo envío".
   - **Modelo B (descartado)** — 1 reporte con 2 nodos desconectados en el canvas. Rompe la premisa del builder.
   - Formato de fila: default por entidad en código (Opción 1), descartando override editable (Opción 2) y plantillas-de-bloque separadas (Opción 3) por YAGNI.
3. Confirmación del caso de uso futuro: listas de precios y promos. Se diseña el pipeline genérico desde el día 1.

### Implementación (Fase 6)

#### Migraciones (`database/migrations/`)
- `2026_04_20_00_create_customer_notes.php` — tabla GLOBAL `customer_notes` (sin `empresa_id`). Campos: `title`, `body_html`, `category` (feature/mejora/seguridad/performance/fix_visible), `version_ref`, `status` (draft/published), `published_at`, soft delete, timestamps, índices.
- `2026_04_20_01_alter_crm_mail_jobs_add_content_report_id.php` — suma `content_report_id INT NULL` a `crm_mail_jobs` de forma idempotente (chequeo en INFORMATION_SCHEMA).

#### Metamodelo (`config/entities.php`)
- Sumada entidad `CustomerNotes` con `empresa_scope=false`, sin `mail_field`, sin `relations`. Fields: id, title, body_html, category, version_ref, status, published_at, created_at.

#### Builder
- `Services/ReportQueryBuilder.php::build()` acepta nuevo parámetro `bool $requireMailTarget = true`. Cuando false, `resolveMailTarget()` retorna null en lugar de tirar excepción.
- `mail_target` en el return type hint cambia a `array{...}|null`.

#### Renderers
- `Services/RowRenderers/RowRenderer.php` — interfaz `renderRows(array $rows): string`.
- `Services/RowRenderers/CustomerNotesRenderer.php` — HTML email-safe: cards apiladas con header de categoría (color + label), cuerpo con title + body_html + fecha es-AR. Colores: verde/azul/dorado/violeta/gris. Tablas inline, sin flex/grid, sin fonts externas. Compatible Gmail/Outlook/Apple Mail.
- `Services/BlockRenderer.php` — dispatcher por `root_entity`. Map `RENDERERS` (hoy solo `CustomerNotes => CustomerNotesRenderer`). Expone `knownContentEntities()` e `isContentEntity(string)` para que otros callsites pregunten.

#### Dispatcher y Repository
- `Services/JobDispatcher::dispatch()` acepta 5to param `int $contentReportId = 0`. Si > 0, llama `BlockRenderer::renderContentReport()` y hace `str_replace('{{Bloque.html}}', $blockHtml, $bodyHtml)` ANTES de `createJob`. El BatchProcessor NO se tocó — sigue resolviendo variables per-row sin enterarse del broadcast.
- `JobRepository::createJob()` suma campo `content_report_id` al INSERT.

#### Controller y vista
- `JobController::create()` separa los reportes de destinatarios y los de contenido usando `BlockRenderer::knownContentEntities()`; pasa dos listas a la vista.
- `JobController::store()` lee `content_report_id` del POST y lo pasa a `dispatch()`.
- `views/envios/crear.php` suma sección **"Paso 3 — Bloque de contenido (opcional)"**. El selector lista solo los reportes de contenido. Renumerados los pasos 4/5/6 (SMTP / Destinatarios / Confirmar).

#### MODULE_CONTEXT.md
- Actualizado con la sección "Alcance Fase 6", reglas editoriales para `customer_notes` (lenguaje de capacidad, no de defecto), backlog explícito (override editable, plantillas-de-bloque, CRUD UI, preview del bloque), y checklist post-cambio.

#### Seed
- `tools/seed_customer_notes.php` — idempotente (skip si la tabla tiene filas). Siembra 4 novedades reales reescritas en lenguaje de cliente final (1.15.0 sync manual, 1.16.0 agrupación tango, 1.16.1 cálculo en vivo PDS, 1.16.x mejoras de seguridad tipo NASA). Todas con `status=published`.

### Bug detectado al probar Charly (y fix)

Al intentar previsualizar/guardar el reporte "Novedades Abril" con root=`CustomerNotes`, el designer tiraba:
> *"No se pudo resolver el campo de destinatario. Declarar 'mail_field' explícitamente o marcar un campo con is_mail_target."*

**Causa**: 3 callsites del builder en el designer/template editor seguían invocando `build()` sin el flag nuevo.

**Fix**:
- Helper centralizado `BlockRenderer::isContentEntity(string): bool`.
- `ReportController::preview` (línea 238) — detecta content entity y pasa `requireMailTarget=false`. Response ahora incluye `is_content_report`.
- `ReportController::validatePayload` (línea 347) — idem para guardar.
- `TemplateController::previewRender` (línea 349) — idem para preview de template.
- `public/js/mail-masivos-designer.js` línea 636 — ya no asume `mail_target.entity` presente. Banner distinto para reportes de contenido: *"N fila(s) de contenido. Este reporte es broadcast, se elige en 'Bloque de contenido' al crear un envío."*

## Por qué

- Reutilizable para cualquier entidad futura (listas de precios, promos, artículos destacados). El diseño del BlockRenderer es extensible por entidad desde el día 1 — solo hay que agregar al `RENDERERS` map y crear un renderer.
- No refactora el builder existente: `CustomerNotes` es relacional normal, solo no tiene mail_field.
- El body_snapshot se congela en `JobDispatcher::dispatch()`, ahí es el hook limpio para inyectar contenido broadcast sin tocar el BatchProcessor.
- Charly descartó CRUD dedicado de customer_notes — el editor de reportes hace de CRUD de lectura/filtrado. Las notas se insertan en DB durante el ritual de cierre.

## Impacto

- Nuevo placeholder convencional `{{Bloque.html}}` en las plantillas. Se reemplaza una sola vez por el HTML del bloque broadcast; si el reporte de contenido no devuelve filas o no se eligió ninguno, queda string vacío (sin romper nada).
- `crm_mail_jobs` suma columna `content_report_id` (idempotente).
- Form de "Nuevo envío" gana un paso opcional (renumerados los pasos siguientes).
- Sin cambios de comportamiento para envíos sin content_report_id (backwards compatible).

## Decisiones tomadas

- **Modelo A (2 reportes combinados en el envío)** sobre Modelo B (canvas compartido).
- **Default HTML por entidad en código** (Opción 1) en vez de override editable (Opción 2) o plantillas-de-bloque separadas (Opción 3). Razón: YAGNI. Plan para la transición a Opción 2 documentado en MODULE_CONTEXT backlog.
- **`customer_notes` es global** (sin `empresa_id`): son novedades del producto comunes a todas las empresas destino.
- **Sin CRUD UI de customer_notes** por ahora. Inserción directa en DB durante el ritual de cierre.
- **Seed idempotente** para que Charly pueda probar sin configurar nada.
- **Commit al cierre sin bump de versión ni OTA** porque la feature no está validada end-to-end todavía (Charly cerró por agenda).

## Validación

- ✅ `php -l` limpio en los 16 archivos nuevos/modificados.
- ✅ 3 migraciones ejecutadas en local (`run_migrations.php`) sin errores.
- ✅ Seed inicial sembró 4 novedades correctamente.
- ✅ Migración de seed 1.17.0 sumó la 5ª novedad (idempotente — las 4 previas quedaron skip).
- ✅ Bump a 1.17.0 / build 20260420.5 aplicado en `app/config/version.php` con history entry completo.
- ✅ Regla "cada release genera migración de seed de customer_notes" documentada en `CLAUDE.md` del proyecto.
- ⚠️ **Validación end-to-end post-deploy**: Charly cerró con la feature implementada pero sin probar el flow completo (reporte de novedades → guardar → combinar con reporte destinatarios → disparar envío real). Confía en la implementación y valida en producción con un envío de prueba a una casilla propia.

## Pendiente

### Para el próximo hilo (orden sugerido)

1. **Validar el bugfix**: ir a `/mi-empresa/crm/mail-masivos/reportes`, abrir o recrear el reporte "Novedades Abril" con root=Novedades, tildar title + body_html + category + version_ref, apretar Previsualizar. Debería mostrar 4 filas con el nuevo banner informativo ("reporte de contenido broadcast"). Apretar Guardar Reporte y confirmar que guarda sin error de "campo de destinatario".
2. **Crear reporte de destinatarios** si no existe uno. Por ejemplo root=`CrmClientes`, mail_field=email.
3. **Crear/editar un template** con `{{Bloque.html}}` en el cuerpo (asociado al reporte de destinatarios).
4. **Ir a "Nuevo envío"** y confirmar que aparece el paso 3 "Bloque de contenido" con "Novedades Abril" seleccionable.
5. **Disparar un envío de prueba a una casilla propia** y revisar que el mail llega con las 4 cards renderizadas (color/ícono por categoría).
6. Si todo OK:
   - Bump `app/config/version.php` a 1.17.0 (feature grande).
   - Sumar entry en `history` con este log.
   - Factory OTA si Charly lo pide explícitamente.

### Backlog documentado (no prioritario)

- Override editable del HTML por-fila (`row_template_html` en `crm_mail_reports`) si aparece la necesidad de variantes visuales por reporte.
- "Plantillas de bloque" como entidad propia si se acumulan 3+ variantes por entidad.
- CRUD UI de `customer_notes` si el volumen de notas crece o se delega a otro rol.
- Preview del bloque renderizado en el UI de crear-envío antes de disparar.
- Sumar chip/botón `{{Bloque.html}}` en la botonera de variables del editor de plantillas (hoy se escribe a mano).

## Relevant Files

- `database/migrations/2026_04_20_00_create_customer_notes.php` — tabla nueva.
- `database/migrations/2026_04_20_01_alter_crm_mail_jobs_add_content_report_id.php` — ALTER idempotente.
- `app/modules/CrmMailMasivos/config/entities.php` — entidad `CustomerNotes` declarada.
- `app/modules/CrmMailMasivos/Services/ReportQueryBuilder.php` — flag `requireMailTarget` agregado.
- `app/modules/CrmMailMasivos/Services/BlockRenderer.php` (nuevo) — dispatcher + helpers.
- `app/modules/CrmMailMasivos/Services/RowRenderers/RowRenderer.php` (nuevo) — interfaz.
- `app/modules/CrmMailMasivos/Services/RowRenderers/CustomerNotesRenderer.php` (nuevo) — HTML email-safe.
- `app/modules/CrmMailMasivos/Services/JobDispatcher.php` — dispatch acepta content_report_id y resuelve placeholder.
- `app/modules/CrmMailMasivos/JobRepository.php` — createJob inserta content_report_id.
- `app/modules/CrmMailMasivos/JobController.php` — create separa reportes, store pasa content_report_id.
- `app/modules/CrmMailMasivos/ReportController.php` — preview y validatePayload respetan isContentEntity.
- `app/modules/CrmMailMasivos/TemplateController.php` — previewRender respeta isContentEntity.
- `app/modules/CrmMailMasivos/views/envios/crear.php` — paso 3 "Bloque de contenido (opcional)".
- `app/modules/CrmMailMasivos/MODULE_CONTEXT.md` — Fase 6 documentada, reglas editoriales, backlog.
- `public/js/mail-masivos-designer.js` — preview tolera `mail_target=null`, banner de content report.
- `tools/seed_customer_notes.php` — seed idempotente.
