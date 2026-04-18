# 2026-04-18 — Release 1.14.0: UX Documentos CRM

## Fecha y tema

**Release**: 1.14.0 / build `20260418.2`
**Fecha**: 2026-04-18
**Scope**: Experiencia transversal sobre el flujo de documentos (Presupuestos + PDS): formato de fecha, CC automático, hotkeys unificadas y visibilidad de envíos por correo.

## Qué se hizo

### Fase 1 — Formato de fecha visible es-AR / 24hs

- **Problema**: los `<input type="datetime-local">` nativos heredan el locale del SO. En Windows en inglés aparecía `2026-04-16 08:50:00` o con AM/PM. Charly pidió ver `16/04/2026 08:50:00` en pantalla sin cambiar nada server-side.
- **Fix**: `public/js/rxn-datetime.js` — config de Flatpickr extendida con `altInput: true` + `altFormat: "d/m/Y H:i:S"` (datetime) y `altFormat: "d/m/Y"` (date). El input original queda hidden manteniendo `dateFormat: "Y-m-d H:i:S"` que es lo que el backend recibe. Cero cambios server-side.

### Fase 2 — CC automático para correos de PDS y Presupuestos

- **Decisión clave**: el CC solo aplica a correos de documentos (PDS + Presupuestos). No a welcome / verification / password reset / mail masivos — no tiene sentido meter a soporte en copia de esos.
- **Arquitectura**:
  - `MailService::send()` recibe un 6to parámetro opcional `array $cc = []`. No rompe llamadores existentes.
  - `DocumentMailerService::sendDocument()` lee `empresa_config.documentos_cc_enabled` + `documentos_cc_emails`, parsea y pasa la lista a `send()`.
  - UI en `EmpresaConfig/views/index.php` con switch + input multi-email (`,` o `;`) + validación en vivo (regex JS con feedback rojo/verde y contador).
  - Sanitización server-side en `EmpresaConfigService::save()` con `preg_split('/[,;\s]+/', ...)` + `filter_var(FILTER_VALIDATE_EMAIL)`; descarta inválidos.
- **Gotcha aceptado**: auto-envío duplicado cuando el From propio también está en el CC (ej. listas de distribución). Charly lo decidió intencional.
- **Migración**: `2026_04_18_add_documentos_cc_to_empresa_config.php` — itera sobre `empresa_config` y `empresa_config_crm` (patrón dual).

### Fase 3 — Hotkeys unificadas PDS / Presupuestos + ALT+O para copiar

- **Foto inicial**: ya existía `public/js/rxn-shortcuts.js` con sistema completo (`RxnShortcuts.register` + modal Shift+?). PERO las hotkeys del form PDS estaban implementadas con `document.addEventListener('keydown')` manual — no se registraban en el overlay de ayuda. Y el form de Presupuestos no tenía hotkeys.
- **Fix**:
  - PDS form: script inline reescrito — las hotkeys ALT+P (Tango) y ALT+E (correo) ahora se registran en `RxnShortcuts.register` con `group: 'Pedido de Servicio'`. Se ven en Shift+?.
  - Presupuestos form: agregado bloque `<script>` equivalente con `group: 'Presupuesto'` — ALT+P y ALT+E unificadas con PDS.
  - `public/js/rxn-list-shortcuts.js` NUEVO: registra ALT+O como atajo global que copia la fila "activa" (con foco por keyboard navigation o con mouse hovering). Usa `data-copy-url` en el `<tr>` como hook declarativo.
  - Tracking del hover via delegación en `document.addEventListener('mouseover', ...)` + `mouseout` que setea/limpia `data-rxn-row-hover="1"`.
  - `<tr>` de listados de PDS y Presupuestos marcados con `data-copy-url="/mi-empresa/crm/{...}/copiar"` (endpoint existente).
  - Submit dinámico: crea form POST al vuelo, incluye `csrf_token` si hay meta, submit.
- **Carga global**: `admin_layout.php` incluye `/js/rxn-list-shortcuts.js` después de `rxn-shortcuts.js`.

### Fase 4 — Tracking persistente de envíos + badge en listados

- **Problema**: Charly quería ver en el listado cuántas veces se envió un PDS o Presupuesto, y alerta clara si hubo error. Antes solo había Flash post-redirect.
- **Fix**:
  - Migración `2026_04_18_add_correos_tracking_to_crm_docs.php` agrega 4 columnas a `crm_presupuestos` y `crm_pedidos_servicio`:
    - `correos_enviados_count INT DEFAULT 0`
    - `correos_ultimo_envio_at DATETIME NULL`
    - `correos_ultimo_error TEXT NULL`
    - `correos_ultimo_error_at DATETIME NULL`
  - Cada repo gana 2 métodos:
    - `registrarCorreoEnviado(id, empresaId)` — incrementa count, setea `ultimo_envio_at = NOW()`, limpia error.
    - `registrarErrorCorreo(id, empresaId, mensaje)` — setea error + `ultimo_error_at = NOW()`, trunca a 2000 chars.
  - Controllers: después de `sendDocument()` éxito → `registrarCorreoEnviado`, falla/excepción → `registrarErrorCorreo` con el mensaje real.
  - Partial compartido `app/shared/views/components/correo_envio_badge.php`:
    - Sin envíos + sin error → sobre gris muted.
    - Count > 0 sin error vigente → sobre verde + badge con el count + tooltip "Enviado N veces, último: fecha".
    - Hay error posterior al último envío → sobre rojo + warning triangle (o badge rojo con count) + tooltip con el mensaje truncado a 200 chars.
  - Lógica de "error vigente": si `ultimo_error_at > ultimo_envio_at` (o no hay envío previo), el error prevalece como estado visible.
  - Listados de PDS y Presupuestos incluyen columna "Correo" con el partial.

### Fase 5 — Documentación defensiva

Agregadas 4 secciones CRÍTICAS a `CLAUDE.md` del proyecto:

1. **Cómo correr PHP local** — `D:\RXNAPP\3.3\bin\php\php8.3.14\php.exe` (Open Server / OSPanel no expone PHP al PATH de bash/cmd).
2. **Arquitectura centralizada de envío de mails** — `MailService::send()` es el único punto, `DocumentMailerService` es el wrapper para PDS/Presupuestos, `CrmMailMasivos` usa el core directo. Ningún módulo debe bypasear.
3. **Tablas duales `empresa_config` / `empresa_config_crm`** — checklist de 5 pasos al agregar columna (migración sobre ambas, ALTER idempotente en repo, modelo, save UPDATE+INSERT, service).
4. **Uso obligatorio de Engram** — `mem_context` + `mem_search` antes de preguntar al usuario, `mem_save` después de cada decisión.

Todas esas decisiones también guardadas en Engram con sus `topic_key`.

## Por qué

- Charly reportó 5 temas en un solo mensaje al final de la sesión; los abordamos en orden de menor a mayor complejidad.
- La sesión arrancó con un poco de dispersión — preguntas al usuario sobre cosas que ya estaban en el código o que debieron estar en memoria. Mid-session se frenó, se consultó Engram, se guardaron las arquitecturas faltantes, y se actualizó el CLAUDE.md para que futuras sesiones no repitan el mismo error.

## Impacto

- UX mejor para el usuario operativo del CRM (visibilidad de envíos, hotkeys consistentes, fecha en español).
- Infraestructura: `RxnShortcuts` consolidado — ahora todos los atajos van por el registry y aparecen en Shift+?.
- Preparación para crecer: el partial `correo_envio_badge.php` se puede reutilizar en cualquier módulo que tenga documentos con envío por correo. Los 4 campos de tracking son un patrón repetible.

## Decisiones tomadas

- **CC a nivel DocumentMailerService y no MailService global**: cada tipo de correo decide qué cross-cutting concerns le aplican.
- **Auto-envío no filtrado**: Charly aceptó el duplicado cuando el From y el CC coinciden (lista de distribución).
- **ALT+O usa `data-copy-url` declarativo**: cualquier tabla puede habilitarse agregando el atributo al `<tr>` (sin JS adicional). Extensible a clientes, artículos, notas, etc.
- **Formato visible d/m/Y**: para TODA la app (datetime y date), no solo PDS/Presupuestos. Es coherencia global de locale.

## Validación

- Migraciones ejecutadas localmente con `tools/run_migrations.php` — ambas OK, 0 errores.
- Testing manual pendiente del lado del usuario: probar form de Presupuestos con ALT+P, ALT+E; listados con hover + ALT+O; toggle de CC + input multi-email; badge de envíos después de enviar un correo real.
- La regla de UX datetime 24hs se ajustó al formato es-AR pero la semántica de 24hs (time_24hr: true, enableSeconds: true) se preservó.

## Pendiente

- Si al probar los tooltips del badge se nota que no se ven (por falta de init de Bootstrap Tooltip), inicializar globalmente en `admin_layout.php` con un `document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el))` post-DOMContentLoaded. Por ahora el `title` nativo del browser oficia de fallback.
- Considerar aplicar el patrón del badge a otros módulos con envío por correo (clientes B2B, notas internas).
