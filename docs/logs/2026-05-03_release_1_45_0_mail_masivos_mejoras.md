# Release 1.45.0 — Mejoras a CrmMailMasivos antes de la primera campaña real

**Fecha**: 2026-05-03
**Build**: 20260503.1
**Iteración**: 46

## Qué se hizo

Cuatro mejoras al módulo de envíos masivos de correo antes de que Charly mande la primera campaña a sus 5k clientes finales con las novedades de la suite.

### 1. Tokens dinámicos en filtros de reportes

- Nuevo servicio `App\Modules\CrmMailMasivos\Services\FilterTokenResolver` con tokens:
  - Estáticos: `{{HOY}}`, `{{AYER}}`, `{{MAÑANA}}`, `{{AHORA}}`.
  - Modificadores: `{{HOY±Nd}}`, `{{HOY±Nm}}`, `{{HOY±Ny}}` (días/meses/años).
  - Calendario: `{{INICIO_MES}}`, `{{FIN_MES}}`, `{{INICIO_ANIO}}`, `{{FIN_ANIO}}` (acepta `{{INICIO_AÑO}}` también).
- Resolución **siempre en backend** al ejecutar el query. El `config_json` del reporte queda con el literal — no hay riesgo de envenenamiento del SQL porque después se bindea como placeholder PDO.
- Soporta arrays (IN, NOT IN, BETWEEN), strings (LIKE, NOT LIKE) y operadores binarios simples.
- UI: botón calendario al lado del input del filtro abre menú flotante con la lista de tokens. Para BETWEEN se pueden combinar (`{{INICIO_MES}}, {{FIN_MES}}`).

### 2. Preview paginado server-side

- `ReportController::preview()` acepta `_page` y `_per_page` (default 25, sin tope porque el control humano sobre los destinatarios reales es valioso para Charly antes de mandar a 5k).
- Devuelve `total` (real, vía COUNT(*) sobre el mismo plan), `mail_count` (DISTINCT vía subquery), `total_pages`.
- `ReportQueryBuilder` ganó parámetro `offset` y método `buildCount()` (regex replace del SELECT por COUNT(*)).
- UI: pager con « ‹ [input numérico] de N › » + selector 10/25/50/100/200.

### 3. Preview del mail final en pantalla de envío masivo

- Card nueva en [`views/envios/crear.php`](app/modules/CrmMailMasivos/views/envios/crear.php) con iframe sandbox del mail completo (plantilla + bloque renderizado + datos del primer destinatario).
- `TemplateController::previewRender()` extendido para aceptar:
  - `template_id` → autoresuelve `asunto`, `body_html` y `report_id` desde la plantilla guardada.
  - `content_report_id` → aplica `BlockRenderer` antes del `renderTemplate`.
- Tres botones: **Refrescar** (manual, no auto), **Pantalla completa** (modal Bootstrap `modal-fullscreen`), **Nueva pestaña** (`window.open` + `document.write`).
- Si cambian reporte/plantilla/bloque, el status avisa "refrescá el preview" sin auto-disparar.

### 4. Logo de Reaxion en la plantilla "Novedades RXN — Newsletter"

- Asset físico en [`public/img/email/LogoRXN-SinFondo.png`](public/img/email/LogoRXN-SinFondo.png) (viaja con OTA porque `ReleaseBuilder` empaqueta toda `public/`).
- Nuevo helper `App\Modules\CrmMailMasivos\Services\SuitePlaceholderResolver` resuelve `{{Suite.base_url}}` y `{{Suite.logo_url}}` a URLs absolutas en runtime usando `$_SERVER['HTTP_HOST']` con fallback a `$_ENV['APP_URL']`. Multi-tenant: cada suite apunta a su propio dominio sin compartir config.
- Migración `2026_05_03_00_alter_mail_template_novedades_add_logo.php` inyecta el `<img src="{{Suite.logo_url}}">` arriba del `<h1>` del header de la plantilla para todas las empresas. Idempotente por presencia del placeholder.
- `JobDispatcher::dispatch()` resuelve los placeholders globales **antes** de congelar el `body_snapshot` para que el regex `{{Word.word}}` del `BatchProcessor` no los confunda con tokens de destinatario y los reemplace por string vacío.

## Por qué

Charly se está preparando para mandar la primera campaña real con las novedades de la suite. Antes de disparar a 5k clientes querían 4 cosas:
- **Validar destinatarios completos**: paginación del preview para auditar quién va a recibir.
- **Logo en el mail**: imagen corporativa en el header del newsletter, empaquetada en el OTA.
- **Filtros sin tocar a mano cada vez**: tokens dinámicos para evitar editar el reporte antes de cada disparo.
- **Ver el mail antes de mandar**: preview del cuerpo final con el bloque y los datos reales, en pantalla completa si hace falta.

## Impacto

- Módulo `CrmMailMasivos`: 4 mejoras incrementales sin breaking changes en la API existente. Reportes y plantillas viejos siguen funcionando.
- Multi-tenant: el asset del logo y el resolver `{{Suite.*}}` están diseñados para que cada suite use su propio dominio.
- Performance: el COUNT(*) usa el mismo plan de joins que el SELECT (sin overhead), y el COUNT DISTINCT sobre mails se hace via subquery porque ese SI tiene overhead pero corre una vez por página.

## Decisiones tomadas

- **Sin tope total en el preview**: Charly explicitó que el control humano sobre los 5k es útil; preferimos pagar el costo de scroll a páginas N versus cortar en N=100 y obligar a confiar.
- **Refresh manual del preview del mail**: evitar requests innecesarios al cambiar selectores rápido. El status visualmente avisa "refrescá".
- **Pantalla completa = ambos modal y nueva pestaña**: Charly pidió las dos. Modal mantiene contexto, nueva pestaña permite imprimir/guardar.
- **`{{Suite.logo_url}}` resuelto en JobDispatcher (no en BatchProcessor)**: el logo no varía por destinatario y queda fijo en `body_snapshot`. Resolverlo en BatchProcessor sería redundante y rompe el regex existente.

## Validación

- `php tools/run_migrations.php` corrió la migración del logo en local sin errores.
- `php -l` sobre todos los archivos modificados (8 PHP) sin syntax errors.
- Falta: smoke test manual del preview en `/mi-empresa/crm/mail-masivos/envios/crear` con la plantilla "Novedades RXN — Newsletter" + bloque "Novedades de CRM" para confirmar que el logo aparece y el bloque se renderiza correctamente.

## Pendiente

- Para próxima sesión: notificaciones de la PWA (Web Push para vendedores en campo).
- Eventual: extender los tokens dinámicos del filtro para soportar también el campo de nombre del usuario logueado (`{{Yo.id}}`, `{{Yo.email}}`) si aparece la necesidad.
