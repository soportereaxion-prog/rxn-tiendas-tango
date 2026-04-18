# Release 1.13.0 — Seguridad transversal: auditoría Tiendas + hardening multi-tenant

**Fecha**: 2026-04-17
**Build**: 20260417.2
**Tipo**: feature grande de seguridad (bump minor)
**Scope**: 4 tramos de fixes + convenciones documentadas. No toca stock/checkout race (queda para 1.13.1).

---

## Qué se hizo

Iteración completa de hardening sobre los hallazgos de la auditoría del mismo día (ver `docs/seguridad/2026-04-17_auditoria_tiendas_multitenant.md`). La aproximación fue: **barrer con todo lo que no requiere decisión de diseño**, dejar para después los 2 puntos que sí (stock + CSP enforcement).

### Tramo 1 — Fixes mecánicos

- **IDOR en `ClienteWebRepository`** (4 métodos): `update()`, `updateTangoData()`, `clearTangoData()`, `updateRelacionOverrides()` ahora reciben `int $empresaId` como parámetro obligatorio y lo incluyen en `WHERE`. Antes sólo filtraban por `id` — defense-in-depth estaba roto aunque el controller validara ownership antes.
- **`ClienteWebController`**: 7 callers actualizados para pasar `$empresaId`.
- **Session hygiene B2C**: `ClienteWebContext::login` ahora llama `session_regenerate_id(true)`. `logout()` limpia `$_SESSION['cart']` + regenera ID.
- **Open redirect en login B2C**: `ClienteAuthController::processLogin` valida que `$_GET['next']` sea relativo local (rechaza `//`, `://`, URLs absolutas). Reemplazo de `filter_var(..., FILTER_SANITIZE_URL)` que sanitizaba pero no validaba origen.
- **User enumeration B2B + B2C**: tanto `AuthController::processLogin` como `ClienteAuthController::processLogin` ya no reflejan `$e->getMessage()` al usuario. Se loguea server-side con `error_log` y al frontend se responde siempre con el mismo mensaje genérico.

### Tramo 2 — Uploads blindados

- **`app/core/UploadValidator.php` nuevo**: class centralizada con `image()`, `favicon()`, `prepareDir()`, `generateFilename()`. Valida MIME real (`finfo_open` → fallback `mime_content_type`), `getimagesize()` para imágenes, tamaño máximo (5MB default), whitelist de MIMEs canónicos. Filename generado server-side — nunca usa el nombre del archivo del usuario.
- **`public/uploads/.htaccess` nuevo**: `<FilesMatch>` deniega ejecución de `.php`, `.phtml`, `.phar`, `.pl`, `.py`, `.jsp`, `.asp`, `.sh`, `.cgi`. Defense-in-depth ante polyglot malicioso que pase el validator.
- **Migración de 4 uploaders**:
  - `Articulos\ArticuloController`: imágenes de producto.
  - `Categorias\CategoriaService`: imagen de portada de categoría.
  - `EmpresaConfig\EmpresaConfigService`: 3 uploads (imagen_default, impresion_header, impresion_footer) colapsados en `handleImageUpload()` reutilizable.
  - `EmpresaConfig\EmpresaConfigController`: logo + favicon (store y CRM) refactorizados a `storeBrandingAsset()` — usa `favicon()` para favicon (acepta .ico) e `image()` para logo.
- **Permisos**: `mkdir(..., 0777)` → `0755` en todos los uploaders, via `UploadValidator::prepareDir()`.

### Tramo 3 — CSRF everywhere (storefront + auth)

- **Consolidación**: `app/core/CSRF.php` ELIMINADO (era duplicado exacto de `CsrfHelper.php` y nadie lo usaba — confirmado por grep). `app/core/CsrfHelper.php` queda como único helper.
- **`Controller::verifyCsrfOrAbort()` nuevo**: método base para que los controllers POST validen en una sola línea. Responde 419 + termina si falla.
- **`csrfField()` inyectado en 11 views**:
  - Storefront: `checkout.php`, `cart.php` (update + remove), `auth/login.php`, `auth/registro.php`, `index.php` (add-to-cart), `show.php` (add-to-cart).
  - Auth: `login.php` (B2B), `forgot.php`, `reset.php`, `resend.php`.
- **Validación aplicada en 8 action handlers**:
  - `CartController::add/update/remove` — `add()` además valida `HTTP_REFERER` como relativo local (mitiga open redirect en el retorno post-add).
  - `CheckoutController::confirm`.
  - `ClienteAuthController::processLogin/processRegister`.
  - `AuthController::processLogin`.
  - `PasswordResetController::processForgot/processReset`.
  - `VerificationController::processResend`.

### Tramo 4 — RateLimiter (FileCache) + token 32hex + auth hardening

- **`app/core/RateLimiter.php` nuevo**: throttle por key dentro de ventana temporal. Persistencia en `FileCache` (sin migración DB — suficiente para Plesk single-node; si mañana hay múltiples workers, se migra a tabla o Redis manteniendo la misma API).
  - API: `allow()`, `hit()`, `attempt()`, `reset()`, `retryAfter()`, `clientKey($scope, $email)`.
  - `clientKey()` compone scope + email + IP para defense-in-depth.
- **Aplicado en 5 endpoints de auth**:
  - Login B2B: 5 intentos / 15 min por `email + IP`.
  - Login B2C: 5/15min por `empresa + email + IP`.
  - Registro B2C: 3/15min por IP + `filter_var(FILTER_VALIDATE_EMAIL)` previo al throttle (no gasta cuota con inputs basura).
  - Forgot password (B2B y B2C en el mismo endpoint): 3/15min por `email + IP`. Respuesta idéntica cuando está throttled — mantiene fallo silencioso.
  - Resend verification: 3/15min por `email + IP`.
- **Token reset B2C**: `ClienteWebAuthService::requestPasswordReset` reemplaza `random_int(100000, 999999)` (PIN 6 dígitos, brute-forceable en minutos) por `bin2hex(random_bytes(16))` (32 hex, 2^128 keyspace). El UPDATE del token además incluye `empresa_id` en el WHERE (defense-in-depth — ya está filtrado por `empresa_id` en el SELECT previo, pero explícito es mejor).

### Convenciones documentadas

- **`docs/seguridad/convenciones.md` nuevo**: checklist obligatoria para todo módulo nuevo. 10 secciones cubriendo aislamiento multi-tenant, auth y sesión, CSRF, uploads, IDOR, inputs/output, webhooks, headers/CSP, checklist de merge. Es el documento de referencia — si mañana alguien mergea un módulo que no tilda los puntos, se documenta explícitamente en su `MODULE_CONTEXT.md` por qué no aplica.
- **`CLAUDE.md` del proyecto**: agregado principio defensivo transversal que apunta al checklist. Toda futura sesión va a levantar el archivo automáticamente.

---

## Por qué

Auditoría del 2026-04-17 identificó 13 hallazgos verificados en Tiendas. Charly pidió **barrer con todo** para ser prolijos antes de seguir sumando módulos. La convención documentada evita que los mismos bugs se repitan en módulos futuros.

---

## Impacto

**Superficie de ataque reducida drásticamente en todo lo que es autenticación y forms públicos**:
- CSRF en storefront completo → no más ataques de tipo "sitio malicioso logrea al cliente en su nombre".
- RateLimiter en auth → brute force inviable (5 intentos por ventana de 15 min).
- Token reset B2C de 32 hex → no más PIN guessable.
- Uploads validados por MIME real → no más bypass con `.php` renombrado a `.jpg`.
- IDOR en `ClienteWebRepository` cerrado → defense-in-depth consistente en todo el repo.

**Compat**: ninguna ruta/feature se rompió. Los 4 métodos de `ClienteWebRepository` que cambiaron firma ya se actualizaron en los 9 callers (todos dentro de `ClienteWebController`).

**Tradeoff aceptado**: `verifyCsrfOrAbort()` se aplica en auth y storefront, no en backoffice completo todavía. El backoffice tiene CSRF disponible (helper + método base) pero se va a aplicar orgánicamente cuando se toque cada módulo. La razón es el volumen — backoffice tiene 50+ forms y meter CSRF global de una rompería cualquier form que no se testee. La convención dice que todo módulo nuevo/tocado lo aplica.

---

## Decisiones tomadas

- **RateLimiter con FileCache, no tabla DB**: Charly lo pidió explícito. Suficiente para deploy single-node actual. La API está preparada para migrar a DB/Redis sin romper callers.
- **Stock/checkout race → 1.13.1**: requiere decisión sobre reserva vs decremento directo. Charly pidió dejarlo para la última fase.
- **CSP → 1.13.1+**: requiere iteración con modo `Report-Only` primero. No se arranca en este release.
- **Helper CSRF duplicado**: borrado `CSRF.php`, queda sólo `CsrfHelper.php`. Grep confirmó cero uso real del primero — riesgo nulo.
- **Backoffice CSRF global**: aplicado orgánicamente, no en este release. La convención lo exige para módulos nuevos/tocados.

---

## Validación

- Tramo 1: verificado que los 9 callers del repo ya pasan `$empresaId`. Grep confirmó sin más callers fuera de `ClienteWebController`.
- Tramo 2: `UploadValidator` tiene fallback `mime_content_type` si `finfo_open` no está disponible (ambos existen en PHP 8.3 de XAMPP actual).
- Tramo 3: `CsrfHelper::input()` imprime el hidden correctamente. `verifyCsrfOrAbort()` valida con `hash_equals`.
- Tramo 4: `RateLimiter` usa `FileCache::set/get/delete` que ya está probado. Key se hashea a sha256 truncado para respetar el regex de sanitización de `FileCache::getFilePath` (`[a-zA-Z0-9_-]` only).
- **Testing manual recomendado post-deploy**:
  - Login B2B con credenciales mal 5 veces seguidas → debería throttlear.
  - Intentar upload de archivo `.php` renombrado a `.jpg` → debería rechazar con mensaje de tipo inválido.
  - Submit de carrito sin token CSRF → debería responder 419.
  - Login B2C con `?next=https://evil.com` → debería redirigir a `/{slug}` ignorando el external.
  - Reset password B2C → mail debería traer un link con token de 32 hex (no un PIN).

---

## Pendiente (para 1.13.1+)

- Validación de stock + transacción/lock en `CheckoutService::processCheckout`. Requiere decisión: reserva temporal vs decremento directo sobre `articulos.stock_actual`. Hablar con Charly antes de codear.
- `Content-Security-Policy` (arrancar en `Report-Only` una semana antes de enforcement).
- CSRF en backoffice completo (orgánico, no urgente — los forms requieren login previo y cookie `SameSite=Lax` ya mitiga casi todo).
- Auditoría de log de operaciones (quién creó qué pedido, cambios de precio/stock, uploads).
- Revisar si tiene sentido agregar RBAC más fino dentro del tenant (hoy todo operador con `modulo_tiendas=1` puede hacer todo).

---

## Env vars nuevas

Ninguna. El RateLimiter usa `FileCache` que vive en `storage/cache/` existente. UploadValidator no depende de nada externo (usa extensiones PHP built-in: `finfo`, `getimagesize`).

---

## Archivos tocados

**Nuevos**:
- `app/core/UploadValidator.php`
- `app/core/RateLimiter.php`
- `public/uploads/.htaccess`
- `docs/seguridad/convenciones.md`
- `docs/seguridad/2026-04-17_auditoria_tiendas_multitenant.md` (de la sesión previa)

**Eliminados**:
- `app/core/CSRF.php` (duplicado sin uso)

**Modificados**:
- `app/config/version.php` (bump 1.13.0 + history entry)
- `app/core/Controller.php` (verifyCsrfOrAbort)
- `app/modules/ClientesWeb/ClienteWebRepository.php` (4 métodos con empresa_id)
- `app/modules/ClientesWeb/Controllers/ClienteWebController.php` (9 callers actualizados)
- `app/modules/ClientesWeb/Controllers/ClienteAuthController.php` (CSRF + rate limit + next validation + mensaje genérico + email validate)
- `app/modules/ClientesWeb/Services/ClienteWebAuthService.php` (token 32 hex + empresa_id en UPDATE)
- `app/modules/Store/Context/ClienteWebContext.php` (session_regenerate + cart clear en logout)
- `app/modules/Store/Controllers/CartController.php` (CSRF + referer validation)
- `app/modules/Store/Controllers/CheckoutController.php` (CSRF)
- `app/modules/Store/views/checkout.php`, `cart.php`, `index.php`, `show.php`, `auth/login.php`, `auth/registro.php` (CSRF)
- `app/modules/Auth/AuthController.php` (CSRF + rate limit + mensaje genérico)
- `app/modules/Auth/PasswordResetController.php` (CSRF + rate limit)
- `app/modules/Auth/VerificationController.php` (CSRF + rate limit)
- `app/modules/Auth/views/login.php`, `forgot.php`, `reset.php`, `resend.php` (CSRF)
- `app/modules/Articulos/ArticuloController.php` (UploadValidator)
- `app/modules/Categorias/CategoriaService.php` (UploadValidator)
- `app/modules/EmpresaConfig/EmpresaConfigService.php` (UploadValidator)
- `app/modules/EmpresaConfig/EmpresaConfigController.php` (UploadValidator)
- `CLAUDE.md` (referencia a convenciones de seguridad)
