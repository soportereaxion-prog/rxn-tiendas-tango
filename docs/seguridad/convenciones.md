# Convenciones de seguridad — RXN Suite

**Vigente desde**: release 1.13.0 (2026-04-17)
**Propósito**: checklist de reglas defensivas que todo módulo nuevo (o modificación de uno existente) debe respetar. Se originó en la auditoría de Tiendas del 2026-04-17 (ver `docs/seguridad/2026-04-17_auditoria_tiendas_multitenant.md`).

Si algo acá no te cierra para un caso particular, **hablalo con Charly antes de saltártelo**. Las excepciones se documentan, no se disimulan.

---

## 1. Aislamiento multi-tenant

### 1.1 Toda query a tabla con `empresa_id` filtra por tenant
- **SELECT / UPDATE / DELETE** deben incluir `WHERE empresa_id = :empresa_id` (salvo tablas explícitamente globales: `empresas`, `migrations`, configs de `.env`).
- **INSERT** debe setear `empresa_id` en el VALUES.
- **No hay excepciones de "ya lo validó el controller"**. Defense-in-depth: el repositorio nunca asume que el caller validó.

### 1.2 `empresa_id` se lee SIEMPRE de `Context::getEmpresaId()` (backoffice) o `PublicStoreContext::getEmpresaId()` (storefront)
- **Nunca** de `$_GET`, `$_POST`, body JSON, header, cookie ni ruta.
- El flag `$useDevFallback` de `Context.php` debe quedar en `false` en producción.

### 1.3 Repositorios: firma obligatoria con `int $empresaId`
Todo método de repositorio que haga UPDATE/DELETE/SELECT sobre tabla tenant debe recibir `$empresaId` como parámetro explícito:

```php
public function update(int $id, int $empresaId, array $data): void
{
    $sql = "UPDATE tabla SET ... WHERE id = :id AND empresa_id = :empresa_id";
    // ...
}
```

**Patrón referencia correcto**: `ClienteWebRepository::updateIfChanged` y `CrmClienteRepository::update`.

### 1.4 Controllers: validar pertenencia antes de operar
Antes de cualquier update/delete por ID, el controller hace `findById($id, $empresaId)` y aborta si retorna `null`. Esto es además del filtro en la query — no lo reemplaza.

### 1.5 Cache
- Keys de `FileCache` / Redis / cualquier almacén deben incluir `empresa_id` cuando el contenido sea específico del tenant.
- Si por alguna razón una key es compartida, documentar explícitamente en el código que el contenido **no depende del tenant**.

---

## 2. Autenticación y sesión

### 2.1 `session_regenerate_id(true)` tras cambio de identidad
Obligatorio en:
- Login exitoso (B2B y B2C).
- Cambio de password (reset exitoso).
- Cualquier switch de identidad (ej: "Ingresar" a otra empresa siendo super-admin).

### 2.2 Passwords
- **Hash**: `password_hash($pwd, PASSWORD_DEFAULT)`. Nunca MD5, SHA1 ni hash custom.
- **Verify**: `password_verify()`. Nunca comparar hashes con `==` o `===`.
- **Mínimo**: 8 caracteres (validado en el form).

### 2.3 Tokens (verificación, reset, session)
- **Largo mínimo**: 32 caracteres hex (`bin2hex(random_bytes(16))`).
- **PROHIBIDO**: PINs numéricos cortos (6 dígitos, 4 dígitos) — brute-force trivial.
- **Comparación**: `hash_equals()` siempre que se compare un token provisto por el usuario contra uno almacenado (defense-in-depth contra timing attacks).
- **Expiración obligatoria**: email verification 24h, password reset 30min máx.
- **Uso único**: invalidar (NULL-ear en DB) el token tras usarlo.

### 2.4 Rate limiting obligatorio
Todo endpoint que procese credenciales o enviare emails debe pasar por `App\Core\RateLimiter`:
- Login (B2B y B2C)
- Forgot password (B2B y B2C)
- Registro
- Reenvío de verificación
- Webhooks públicos sin auth (además de firma HMAC)

**Patrón**:
```php
use App\Core\RateLimiter;

if (!RateLimiter::allow('login:' . $email, maxAttempts: 5, windowSeconds: 900)) {
    // ... respuesta genérica + log server-side
}
RateLimiter::record('login:' . $email);
// ... lógica normal
// en éxito: RateLimiter::reset('login:' . $email)
```

### 2.5 Mensajes de error genéricos en auth
- Login fallido → **siempre** "Credenciales inválidas o cuenta inactiva." (sin diferenciar user-not-found vs wrong-password vs not-verified).
- Forgot password → **siempre** "Si el email existe, vas a recibir instrucciones." (fallo silencioso incluido).
- **NUNCA** `echo $e->getMessage()` al usuario en auth. Log server-side con `error_log()`, respuesta genérica al cliente.

### 2.6 Cookie de sesión
Configurar en `App\Core\App::bootstrapSession()`:
- `httponly = true`
- `samesite = Lax` (o `Strict` cuando no haya redirects cross-site esperados)
- `secure = true` cuando la request sea HTTPS

### 2.7 Timeout de sesión
- Idle: 6 horas (`backoffice_last_activity`)
- Absoluto: 12 horas (`backoffice_created_at`)
- Logout: `session_unset()` + `session_destroy()` + limpiar cookie.

---

## 3. CSRF

### 3.1 Todo form POST emite `CsrfHelper::input()`
```php
<form method="POST" action="...">
    <?= \App\Core\CsrfHelper::input() ?>
    <!-- resto del form -->
</form>
```

### 3.2 Todo controller valida el token en POST
Usar el método base `$this->verifyCsrfOrAbort()` del `Controller` padre, o validar manual:
```php
use App\Core\CsrfHelper;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !CsrfHelper::validate($_POST['csrf_token'] ?? null)) {
    http_response_code(419);
    exit('CSRF token inválido.');
}
```

### 3.3 Excepciones legítimas (deben documentarse)
- **Webhooks externos** (ej: pasarelas de pago). Reemplazan CSRF por validación HMAC de firma.
- **APIs públicas con auth por token**: el token de auth ya sustituye al CSRF.
- **Endpoints AJAX con custom header** (ej: `X-Requested-With: XMLHttpRequest` + `SameSite=Lax` cookie): aceptable pero preferir CSRF explícito.

---

## 4. Uploads de archivos

### 4.1 Centralizar en `App\Core\UploadValidator`
**Nunca** validar sólo la extensión del archivo. El validator hace:
1. `$file['error'] === UPLOAD_ERR_OK`.
2. `mime_content_type($tmpName)` contra whitelist.
3. `getimagesize()` para imágenes — debe devolver dimensiones válidas.
4. Tamaño ≤ límite (`maxBytes`, default 5MB).
5. Extensión derivada del MIME, no del nombre del archivo.

```php
use App\Core\UploadValidator;

$validated = UploadValidator::image($_FILES['imagen'], maxBytes: 5 * 1024 * 1024);
```

### 4.2 Path de destino incluye `empresa_id`
```
public/uploads/empresas/{empresa_id}/{tipo}/{filename}
```

### 4.3 Filename generado, nunca el del usuario
```php
$filename = sprintf('%s_%d_%s_%s.%s',
    $tipo, $empresaId, date('YmdHis'), bin2hex(random_bytes(4)), $ext
);
```

### 4.4 Permisos `0755`, no `0777`
```php
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}
```

### 4.5 `.htaccess` en `public/uploads/`
Ya existe — niega ejecución de PHP. **No eliminar** en deploys. Si se migra a nginx, replicar la regla en el vhost.

---

## 5. IDOR (Insecure Direct Object Reference)

### 5.1 Validación de ownership en 2 capas
1. **Controller**: `findById($id, $empresaId)` — aborta si no pertenece al tenant.
2. **Repository**: toda mutación incluye `WHERE id = :id AND empresa_id = :empresa_id`.

### 5.2 IDs en URL se castean a `int`
`public function edit(int $id)` — PHP valida el cast. Si viene `edit/abc`, el router no matchea y no llega.

### 5.3 Recursos con ownership secundario
Cuando un recurso pertenece al tenant **Y** a un sub-usuario (ej: pedido pertenece al cliente_web X en la empresa Y), validar ambos:
```php
$pedido = $repo->findByIdWithDetails($id, $empresaId);
if (!$pedido || (int)$pedido['cliente_web_id'] !== $clienteWebIdLogueado) {
    // 404, no 403 (no revelar existencia)
}
```

---

## 6. Inputs y output

### 6.1 SQL: prepared statements, siempre
- **Prohibido**: concatenar variables en SQL.
- **Whitelisting**: nombres de tabla/columna dinámicos validados contra regex o array.
- **`ORDER BY` dinámico**: whitelist de columnas permitidas.

### 6.2 Output en vistas: `htmlspecialchars()` por default
Cualquier dato de DB o input de usuario que se renderice:
```php
<?= htmlspecialchars((string) $var, ENT_QUOTES, 'UTF-8') ?>
```

Excepciones permitidas (debe ser obvio al leer):
- Integers castados explícitamente: `<?= (int) $id ?>` — seguro.
- HTML deliberadamente confiable (ej: plantillas generadas por el sistema con `View::escape` ya aplicado por dentro).

### 6.3 No filtrar datos sensibles
`password_hash`, `verification_token`, `reset_token`, credenciales Tango, config SMTP **nunca** en respuestas JSON ni en HTML. Las queries explícitas de read deben enumerar columnas, no usar `SELECT *`.

### 6.4 Redirects validados
Parámetros tipo `?next=`, `?redirect=`, `?return_url=`:
```php
$next = $_GET['next'] ?? '/default';
if (!str_starts_with($next, '/') || str_starts_with($next, '//') || str_contains($next, '://')) {
    $next = '/default';
}
```

---

## 7. Webhooks y endpoints sin auth

### 7.1 Validación HMAC obligatoria
Cualquier webhook que reciba POSTs de un sistema externo (Mercado Pago, Tango, Anura, etc.) debe:
1. Extraer firma del header (`X-Signature`, `X-Hub-Signature-256`, etc.).
2. Calcular HMAC del body con un shared secret configurado por empresa.
3. Comparar con `hash_equals()`.
4. **Abortar 403** si no coincide.

### 7.2 Idempotencia
Webhooks deben poder re-ejecutarse sin efectos colaterales duplicados. Persistir el `event_id` del proveedor y descartar duplicados.

### 7.3 Confirmar estado vía pull
No confiar solo en el payload. Después de recibir "pago aprobado", hacer un GET a la API del proveedor para confirmar el estado real antes de marcar el pedido como pagado.

---

## 8. Headers y CSP

### 8.1 Headers ya configurados (mantener)
- `X-Frame-Options: SAMEORIGIN`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`

### 8.2 CSP
Agregar en `public/index.php` o `App\Core\Response`. Arrancar en modo `Content-Security-Policy-Report-Only` antes de enforcement.

---

## 9. Checklist previo a mergear un módulo nuevo

Antes de dar por cerrado un módulo, revisar:

- [ ] Todas las queries tenant filtran por `empresa_id` (SELECT, UPDATE, DELETE, INSERT).
- [ ] Todo método mutador del repositorio recibe `int $empresaId` como parámetro.
- [ ] Controllers validan ownership con `findById($id, $empresaId)` antes de operar.
- [ ] Todos los forms POST tienen `<?= CsrfHelper::input() ?>` y los controllers validan el token.
- [ ] Endpoints de auth tienen rate limiting aplicado.
- [ ] Uploads pasan por `UploadValidator`, con destino que incluye `empresa_id`.
- [ ] Mensajes de error de auth son genéricos.
- [ ] Output en vistas está escapado con `htmlspecialchars()` (salvo casts `(int)` obvios).
- [ ] Redirects externos validan destino relativo.
- [ ] No se exponen campos sensibles en responses.
- [ ] Sesión se regenera tras login/reset.
- [ ] Webhooks externos validan HMAC y son idempotentes.

Si marcaste "no aplica" en alguno, dejalo documentado en el MODULE_CONTEXT.md del módulo con la razón.

---

## 10. Referencias

- **Informe auditoría 2026-04-17**: [docs/seguridad/2026-04-17_auditoria_tiendas_multitenant.md](docs/seguridad/2026-04-17_auditoria_tiendas_multitenant.md)
- **OWASP Top 10**: https://owasp.org/Top10/ (cuando el link esté disponible)
- **Core helpers relevantes**:
  - [app/core/CsrfHelper.php](app/core/CsrfHelper.php)
  - [app/core/UploadValidator.php](app/core/UploadValidator.php) (release 1.13.0)
  - [app/core/RateLimiter.php](app/core/RateLimiter.php) (release 1.13.0)
  - [app/core/Context.php](app/core/Context.php)
