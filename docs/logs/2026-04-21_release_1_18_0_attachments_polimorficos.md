# Release 1.18.0 — Adjuntos polimórficos reusables (Notas + Presupuestos)

**Fecha**: 2026-04-21
**Build**: 20260421.1
**Tema**: Nueva capa transversal de adjuntos, integrada en CrmNotas y CrmPresupuestos.

---

## Qué se hizo

### Fase 1 — Core reutilizable

Una sola tabla polimórfica `attachments` + un service único + un controller único sirven a cualquier módulo que quiera adjuntar archivos a una entidad. Agregar un módulo nuevo (ej: CrmPedidosServicio, Tratativas) a futuro es sólo sumar el `owner_type` al whitelist del config + incluir el partial en el form.

Piezas nuevas:
- `database/migrations/2026_04_21_00_create_attachments.php` — tabla polimórfica (empresa_id, owner_type, owner_id, original_name, stored_name, mime, size_bytes, path, uploaded_by, soft-delete, índice compuesto, FK cascade sobre empresas).
- `app/config/attachments.php` — config central: 10 archivos por registro, 100 MB por archivo, 100 MB total acumulado. Whitelist MIME → extensión canónica. Blacklist dura de extensiones ejecutables.
- `app/core/UploadValidator.php` — nuevo método `anyFile()` con 3 redes de validación:
  1. Blacklist extensión (rechaza php/phtml/phar/exe/bat/sh/html/svg/js/etc aunque pasen MIME).
  2. Whitelist MIME real (via finfo, no se confía en `$_FILES['type']`).
  3. Alineación ext↔MIME (anti-polyglot: si es .jpg pero el MIME detectado no es image/jpeg → rechazo).
- `app/core/Services/AttachmentService.php` — API: `attach/listByOwner/delete/deleteByOwner/getForDownload/getForPreview/getLimits`. Auto-inyecta un `.htaccess` con `Require all denied` en la raíz attachments/ de cada empresa para que los archivos sean servibles únicamente via endpoint.
- `app/shared/Controllers/AttachmentsController.php` — endpoints:
  - `POST /attachments/upload` (JSON)
  - `POST /attachments/{id}/delete` (JSON)
  - `GET /attachments/{id}/download` (stream forzado con `Content-Disposition: attachment` + `X-Content-Type-Options: nosniff`)
  - `GET /attachments/{id}/preview` (stream inline — sólo image/\*, con CSP `default-src 'none'; img-src 'self' data:`)
- `app/shared/views/partials/attachments-panel.php` — partial reusable con drag&drop, lista, delete inline, botón 👁 solo para imágenes, modal singleton (overlay oscuro + ESC/click para cerrar). Idempotente si se incluye múltiples veces. Config de límites inyectada via `data-*` para que el JS respete lo que dice el backend.

### Fase 2 — Integración en CrmNotas

- `app/modules/CrmNotas/views/form.php` — partial embebido debajo del form. En create muestra aviso "guardá primero".
- `app/modules/CrmNotas/views/show.php` — partial embebido al final del main.
- `app/modules/CrmNotas/CrmNotaRepository.php::forceDelete` — hook que llama a `AttachmentService::deleteByOwner()` ANTES del DELETE físico. Soft-delete NO borra archivos (el usuario puede restaurar).

### Fase 3 — Integración en CrmPresupuestos

- `app/modules/CrmPresupuestos/views/form.php` — partial embebido al final (nivel cabecera, no por línea).
- `app/modules/CrmPresupuestos/PresupuestoRepository.php::forceDeleteByIds` — hook que itera los ids y llama `deleteByOwner()` por cada uno.

### Cierre editorial

- `database/migrations/2026_04_21_01_seed_customer_notes_release_1_18_0.php` — nota visible para el cliente final del nuevo release ("Ya podés adjuntar archivos a tus notas y presupuestos").

---

## Capas de seguridad aplicadas

Checklist de `docs/seguridad/convenciones.md` sección 4 — todas las cajas tildadas:

- ✅ `UploadValidator` centralizado (no se valida extensión sola en ningún punto).
- ✅ MIME real detectado con finfo + whitelist.
- ✅ Extensión del filename original contra blacklist dura (red 1 de defensa).
- ✅ Alineación MIME ↔ extensión (defense-in-depth contra polyglots).
- ✅ `getimagesize()` existente para uploads de imagen (reutilizado; no necesario para attachments genéricos).
- ✅ Filename generado por sistema (`att_{empresa_id}_{ts}_{owner_type}-{owner_id}_{rand}.{ext}`). Nunca `$_FILES['name']`.
- ✅ Path con `empresa_id`: `public/uploads/empresas/{empresa_id}/attachments/Y/m/{stored_name}`.
- ✅ Permisos `0755` para directorios, `0644` para archivos.
- ✅ `.htaccess` "Require all denied" autoinyectado en attachments/ de cada empresa → los archivos NO son accesibles por URL directa. El endpoint es la única vía.
- ✅ Multi-tenant: queries filtran `empresa_id` en INSERT/SELECT/DELETE. Path físico también separado por empresa.
- ✅ IDOR: `getForDownload()` y `getForPreview()` validan `empresa_id` del registro vs empresa activa del usuario.
- ✅ Owner ownership: el controller valida antes de attach que el `owner_id` pertenezca a la empresa activa (vía lookup en `crm_notas` / `crm_presupuestos`). Previene IDOR cruzado.
- ✅ CSRF: POST /upload y POST /delete validan token via `Controller::verifyCsrfOrAbort()`.
- ✅ Preview endpoint restringido por CSP (`default-src 'none'; img-src 'self' data:; object-src 'none'; script-src 'none'; style-src 'none'`) + `X-Content-Type-Options: nosniff`. SVG no está en la whitelist (puede llevar `<script>`).
- ✅ Streaming en chunks de 8 KB para no cargar archivos grandes en memoria.
- ✅ `Content-Disposition: attachment` forzado en download para no-imágenes (previene XSS por contenido).

---

## Decisiones tomadas

1. **Tabla polimórfica única vs. tabla-por-módulo** (patrón de PDS con `crm_pedidos_servicio_adjuntos`). Elegí polimórfica porque:
   - Charly pidió explícitamente código reusable.
   - Agregar un módulo nuevo a futuro es una línea en config, no una migración.
   - La falta de FK física se resuelve con el hook en el `forceDelete` del repo padre.
   - Tradeoff aceptado: si alguien borra filas sin pasar por el service, quedan archivos huérfanos. Mitigación futura: script de GC (no crítico hoy).

2. **Preview solo para imágenes, no PDF**. Charly votó por la opción más conservadora. Los PDFs siguen el camino normal de descarga.

3. **Preview via modal in-page**, no nueva pestaña. Mejor UX, menos fricción.

4. **Límites confirmados**: 10 archivos, 100 MB por archivo, 100 MB total acumulado. Todos parametrizables en `app/config/attachments.php`.

5. **Soft-delete NO borra adjuntos**. Sólo el `forceDelete` (borrado físico desde papelera) dispara el cleanup de archivos. Consistente con la UX: restaurar una nota debe restaurar todo su contenido.

6. **Whitelist de owner_types** explícita en config (`crm_nota`, `crm_presupuesto`). Agregar un módulo nuevo requiere tocar: (a) `allowed_owner_types` en config, (b) el map `ownerBelongsToEmpresa()` en el controller, (c) el hook de `forceDelete` del repo del módulo.

---

## Pendiente explícito (anotado, NO se implementa hoy)

- **Switch en Presupuestos para enviar adjuntos por mail**: hoy los adjuntos son internos. Cuando se active (probablemente via flag en `empresa_config_crm`, tipo `presupuesto_adjuntos_en_mail`), el `DocumentMailerService` tendría que leer los attachments del presupuesto y pasarlos al `MailService::send()` vía el parámetro `$attachments` que ya existe. Decisión diferida a demanda.
- **GC de archivos huérfanos**: tool opcional que recorre `public/uploads/empresas/*/attachments/**` y borra físicos sin row en DB. No crítico hoy.
- **Múltiple a la vez real**: el JS hoy procesa un archivo a la vez en serie (más simple, más seguro con topes). Si se vuelve UX pobre con muchos archivos, se puede parelelizar con un pool de 2-3 concurrentes.

---

## Validación

- `php -l` OK sobre todos los archivos tocados (12 archivos).
- Migración `2026_04_21_00_create_attachments.php` ejecutada en local sin error.
- Smoke test en navegador confirmado por Charly:
  - Subida por drag&drop funciona.
  - Descarga funciona.
  - Delete con confirmación funciona.
  - Archivos `.php` son rechazados (blacklist).
  - Integración en Notas y Presupuestos confirmada visualmente.
- Preview de imágenes: agregado al final de la sesión; la validación en navegador queda para la próxima si Charly no la probó antes del cierre.

---

## Próxima sesión

Charly adelantó que la próxima iteración se ataca **Presupuestos completo** (hay una lista de pendientes en el módulo más allá de adjuntos) y más adelante **PWA**.
