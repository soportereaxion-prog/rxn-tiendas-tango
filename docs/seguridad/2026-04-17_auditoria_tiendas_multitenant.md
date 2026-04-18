# Auditoría de seguridad — Módulo Tiendas + aislamiento multi-tenant

**Fecha**: 2026-04-17
**Scope**: Módulo Tiendas (storefront público B2C + backoffice), con revisión transversal de aislamiento multi-tenant.
**Auditor**: Lumi (Arquitecta RXN Suite)
**Iteración sugerida**: release 1.13.0 (grande, probablemente en tramos)

---

## 1. Contexto

RXN Suite es una app PHP multi-tenant con:
- **Tenant activo** en `$_SESSION['empresa_id']`, leído vía `Context::getEmpresaId()`.
- **Storefront público** resuelve tenant por slug en URL (`/{slug}/...`) → `StoreResolver` → `PublicStoreContext`.
- **Dos tipos de usuarios**: operadores B2B (`usuarios`) y clientes web B2C (`clientes_web`).
- **Dos contextos de sesión paralelos**: `$_SESSION['user_id']` (B2B) y `$_SESSION['store_*']` (B2C).

El módulo Tiendas abarca: catálogo público, carrito, checkout, mis pedidos, y backoffice (artículos, categorías, clientes web, pedidos, sync Tango).

---

## 2. Hallazgos verificados

Los hallazgos están ordenados por severidad real (no la sugerida por análisis automático). Los falsos positivos detectados en la revisión cruzada se listan al final.

### 2.1 🔴 CRÍTICO — Checkout sin validación de stock ni control de race condition

**Path**: [app/modules/Store/Services/CheckoutService.php:29-103](app/modules/Store/Services/CheckoutService.php:29)

El método `processCheckout()` crea el pedido directamente sin:
- Validar que haya stock disponible para cada artículo del carrito.
- Decrementar stock (ni siquiera reservarlo) — queda a cargo de sync Tango asíncrono.
- Tomar un lock para evitar race condition entre dos checkouts simultáneos.

**Impacto**: Overselling real. Dos clientes compran las últimas 10 unidades al mismo tiempo y ambos pedidos quedan aceptados. Cuando el backoffice intenta enviar a Tango, uno falla. Cliente ya tiene "comprobante" local.

**Fix sugerido** (no trivial — requiere diseño):
1. En `processCheckout`, antes del `createPedido`, recorrer los items y validar stock con `FOR UPDATE` dentro de una transacción.
2. Decidir política: ¿reservar stock temporal en una tabla `stock_reservas`, o decrementar directo `articulos.stock_actual`?
3. Si Tango es la fuente de verdad, asumir el riesgo pero al menos validar stock al inicio del checkout con un `SELECT ... FOR UPDATE` sobre `articulos` y fallar si `cantidad > stock_actual`.

**Tradeoff**: validar stock local que se sincroniza con Tango cada N minutos puede dar falsos positivos/negativos según lag de sync. Hay que conversarlo con Charly — depende de cuánto se confía en el stock cacheado de `articulos` vs Tango en tiempo real.

---

### 2.2 🔴 CRÍTICO — Token de reset de password B2C es un PIN de 6 dígitos + sin rate limiting

**Paths**:
- [app/modules/ClientesWeb/Services/ClienteWebAuthService.php:122](app/modules/ClientesWeb/Services/ClienteWebAuthService.php:122) — `random_int(100000, 999999)`
- [app/modules/Auth/AuthController.php](app/modules/Auth/AuthController.php), [app/modules/Auth/PasswordResetController.php](app/modules/Auth/PasswordResetController.php), [app/modules/ClientesWeb/Controllers/ClienteAuthController.php](app/modules/ClientesWeb/Controllers/ClienteAuthController.php) — sin throttle

Combo tóxico:
- **6 dígitos = 1.000.000 combinaciones**. Con 30 minutos de validez, sin rate limiting, un script puede probar todas en minutos.
- Para operadores B2B el token es de 32 hex (bien), pero **login y forgot no tienen ningún throttle** — tampoco el registro. Brute force puro.

**Fix**:
1. PIN de cliente web → reemplazar por `bin2hex(random_bytes(16))` (32 hex, igual que B2B). Enviarlo como link en el email, no como PIN para tipear.
2. Crear un `RateLimiter` mínimo en `app/core/` que persista intentos en `sessionStorage` server-side (tabla `rate_limit_attempts` con índice por `key`+`window_start`). Aplicar en login (B2B y B2C), forgot password (B2B y B2C), registro, y reenvío de verificación. Límite sugerido: 5 intentos por 15 min por `ip+email`.

---

### 2.3 🔴 CRÍTICO — Infraestructura CSRF existe pero no se aplica en ningún form

**Paths**:
- [app/core/CSRF.php](app/core/CSRF.php) y [app/core/CsrfHelper.php](app/core/CsrfHelper.php) — dos helpers duplicados con la misma funcionalidad.
- [app/modules/Store/views/checkout.php](app/modules/Store/views/checkout.php), [cart.php](app/modules/Store/views/cart.php), [auth/login.php](app/modules/Store/views/auth/login.php), [auth/registro.php](app/modules/Store/views/auth/registro.php) — ninguno emite `csrfField()`.
- Controllers del storefront y backoffice **no validan** `csrf_token` en POST.

**Grep confirma**: las únicas ocurrencias de `CsrfHelper`/`CSRF::` están en los archivos `.php` de los helpers mismos y en `MODULE_CONTEXT.md` (documentación). **Cero uso real.**

**Impacto**:
- En storefront: un sitio malicioso puede hacer que un cliente web logueado agregue ítems, complete un checkout con su dirección, cambie perfil, etc. Mitigación parcial por `SameSite=Lax` en cookie (ver §3.1), pero POST top-level sigue pasando con Lax.
- En backoffice: igual, pero más grave porque opera sobre productos, precios, clientes, pedidos.

**Fix**:
1. **Consolidar en un solo helper** — borrar uno de los dos (`CSRF.php` o `CsrfHelper.php`). Son duplicados. Recomiendo quedarse con `CsrfHelper` (naming más explícito).
2. Agregar un **middleware/método base en controllers** que valide `$_POST['csrf_token']` en todo request POST — salvo whitelist explícita (webhooks firmados por HMAC).
3. Agregar `<?= CsrfHelper::csrfField() ?>` a **todos** los forms POST de views. Priorizar por impacto: checkout, carrito, login B2C, registro, update perfil, update producto, update precios, update stock, delete recursos.

---

### 2.4 🔴 ALTO — IDOR en `ClienteWebRepository` (3 métodos sin `empresa_id` en WHERE)

**Path**: [app/modules/ClientesWeb/ClienteWebRepository.php](app/modules/ClientesWeb/ClienteWebRepository.php)

```php
// línea 315-349 — update()
UPDATE clientes_web SET ... WHERE id = :id

// línea 383-404 — clearTangoData()
UPDATE clientes_web SET ... WHERE id = :id

// línea 406-432 — updateRelacionOverrides()
UPDATE clientes_web SET ... WHERE id = :id
```

**Contexto**: el controller (`ClienteWebController::update`) hace `findById($id, $empresaId)` antes y aborta si el cliente no pertenece al tenant. En la práctica eso previene el ataque end-to-end **hoy**. Pero:
- Es **defense-in-depth roto**: si mañana alguien llama a estos métodos desde otro controller o desde un comando CLI sin validar primero, actualiza cross-tenant sin red.
- `updateIfChanged` (línea 152) **sí** valida `empresa_id` en el WHERE — confirma que el patrón correcto existe en el propio archivo, lo cual hace el oversight aún más peligroso (inconsistente).

**Fix**:
1. Cambiar los 3 `WHERE id = :id` a `WHERE id = :id AND empresa_id = :empresa_id`.
2. Cambiar la firma para recibir `int $empresaId` como parámetro obligatorio.
3. Actualizar callers (`ClienteWebController` y donde sea que llame estos métodos).
4. Mismo patrón en `updateTangoData` (línea 351) — también tiene solo `WHERE id = :id`.

---

### 2.5 🔴 ALTO — Uploads validan solo extensión del archivo (no MIME real)

**Paths**:
- [app/modules/Categorias/CategoriaService.php:192-196](app/modules/Categorias/CategoriaService.php:192)
- [app/modules/Articulos/ArticuloController.php](app/modules/Articulos/ArticuloController.php) (línea ~361 según el audit)
- [app/modules/EmpresaConfig/EmpresaConfigController.php](app/modules/EmpresaConfig/EmpresaConfigController.php) (logo/favicon)

```php
$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
    throw new RuntimeException('...');
}
```

**Impacto**: un `.php` renombrado a `.jpg` pasa la validación. Si por accidente/config el directorio `public/uploads/` ejecuta PHP (depende del vhost/`.htaccess`), es RCE trivial. Incluso sin RCE, archivos maliciosos en path servible son un riesgo (polyglot JPG+HTML → XSS, SVG con `<script>` si se sirve como `image/svg+xml`).

**Fix**: centralizar en un `UploadValidator` en `app/core/` que haga:
1. `$mime = mime_content_type($tmpName)` o con `finfo_open(FILEINFO_MIME_TYPE)`.
2. Whitelist de MIME: `['image/jpeg', 'image/png', 'image/webp']`. **No incluir** `image/svg+xml` salvo que se sanitice con librería.
3. Validar tamaño máximo (por ej 5MB).
4. Validar que `getimagesize()` devuelva dimensiones válidas — detecta archivos que no son imagen real.
5. Agregar `.htaccess` en `public/uploads/` que niegue ejecución PHP (defense-in-depth):
   ```apache
   <FilesMatch "\.(php|phtml|php3|php4|php5|phar)$">
       Require all denied
   </FilesMatch>
   ```

**Bonus**: cambiar `mkdir(..., 0777, ...)` a `0755` (líneas de los 3 uploaders) — 0777 en multi-tenant es innecesariamente permisivo.

---

### 2.6 🟠 MEDIO-ALTO — User enumeration en login B2C

**Path**: [app/modules/ClientesWeb/Services/ClienteWebAuthService.php:35-36](app/modules/ClientesWeb/Services/ClienteWebAuthService.php:35)

```php
if ((int)$row['email_verificado'] !== 1) {
    throw new Exception("Cuenta pendiente de verificación. Buscá el enlace en tu correo electrónico.");
}
```

**Impacto**: el mensaje diferenciado "Cuenta pendiente de verificación" revela que el email **existe** en la DB del tenant. Combinado con la ausencia de rate limit, se puede enumerar la base de clientes web de cualquier tenant público (slug visible).

**Nota**: en forgot-password ya hacen fallo silencioso (línea 118-120: `// Falla silenciosa por seguridad...`). El login debería seguir la misma política.

**Fix**: colapsar todos los errores de login en un único mensaje genérico `"Credenciales inválidas"`. El detalle "pendiente de verificación" se puede mostrar **después** de un login con password correcta, no antes.

---

### 2.7 🟠 MEDIO — `ClienteWebContext::login()` no regenera session ID

**Path**: [app/modules/Store/Context/ClienteWebContext.php](app/modules/Store/Context/ClienteWebContext.php) (método `login()`)

A diferencia de `AuthService::attempt()` (B2B) que sí llama `session_regenerate_id(true)`, el login de cliente web solo setea keys de sesión. Riesgo de session fixation si hubiera XSS previo o un atacante logra plantar un session_id.

**Fix**: agregar `session_regenerate_id(true);` al inicio de `ClienteWebContext::login()`.

---

### 2.8 🟠 MEDIO — Logout B2C no limpia carrito ni datos residuales

**Path**: [app/modules/Store/Context/ClienteWebContext.php](app/modules/Store/Context/ClienteWebContext.php) (método `logout()`)

Hace `unset` de las keys `store_cliente_*` pero deja `$_SESSION['cart']` (y potencialmente cualquier otro dato de navegación B2C). Problema real en PCs compartidas o quioscos: el siguiente usuario ve el carrito del anterior.

**Fix**: en `logout()`, hacer `unset($_SESSION['cart'])` (o `unset($_SESSION['cart'][$empresaId])` para preservar otros tenants si el mismo browser navegó varios slugs, aunque ese caso es raro). Mejor aún: `session_regenerate_id(true)` + `session_unset()` en logout.

---

### 2.9 🟡 MEDIO — Open Redirect en parámetro `next` del login B2C

**Path**: [app/modules/ClientesWeb/Controllers/ClienteAuthController.php:59-60](app/modules/ClientesWeb/Controllers/ClienteAuthController.php:59)

```php
$next = $_GET['next'] ?? "/{$slug}";
header("Location: " . filter_var($next, FILTER_SANITIZE_URL));
```

`FILTER_SANITIZE_URL` **no valida origen**, solo sanea. Un atacante manda al usuario a `/slug/login?next=https://evil.com` → después del login legítimo, el usuario termina en `evil.com`.

**Fix**:
```php
$next = $_GET['next'] ?? "/{$slug}";
if (!str_starts_with($next, '/') || str_starts_with($next, '//') || str_contains($next, '://')) {
    $next = "/{$slug}";
}
header("Location: " . $next);
```

---

### 2.10 🟡 MEDIO — Error en login B2B filtra el mensaje de excepción

**Path**: [app/modules/Auth/AuthController.php:18-39](app/modules/Auth/AuthController.php:18)

```php
catch (\Exception $e) {
    $error = $e->getMessage();  // ← leak potencial
}
```

Si `AuthService::attempt` lanza una excepción diferenciada (ej: "Cuenta pendiente de verificación"), se refleja al usuario → enumeration. Similar al 2.6 pero en B2B.

**Fix**: mantener un mensaje genérico `"Credenciales inválidas o usuario inactivo."` para cualquier catch; loguear el `$e->getMessage()` server-side con `error_log` para debug, no reflejarlo.

---

### 2.11 🟡 MEDIO — Auto-registro en checkout sin CSRF ni verificación

**Path**: [app/modules/Store/Controllers/CheckoutController.php:97-101](app/modules/Store/Controllers/CheckoutController.php:97)

Si durante el checkout el cliente guest manda `password_registro` en el form, se ejecuta `ClienteWebAuthService::register()` y se crea cuenta + envía mail de verificación. Sin CSRF, sin rate limit → vector para spam de emails de verificación hacia terceros (usar la app como open-relay para phishing).

**Fix**: además del CSRF global (§2.3) y rate limit (§2.2), validar en `register()` que el email pase `filter_var($email, FILTER_VALIDATE_EMAIL)` y aplicar throttle específico por IP en ese endpoint.

---

### 2.12 🟡 BAJO-MEDIO — Falta Content-Security-Policy

La auditoría de headers confirmó que ya están `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `X-XSS-Protection`, `Referrer-Policy`, y cookie con `HttpOnly`/`Secure`/`SameSite=Lax`. Bien. **Falta CSP.**

**Fix**: agregar header `Content-Security-Policy` en `public/index.php` o en `app/core/Response.php`. Empezar con modo report-only para no romper nada:
```
Content-Security-Policy-Report-Only: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: https:; frame-ancestors 'self'
```

Iterar hasta poder activar en modo enforce.

---

### 2.13 🟡 BAJO — `mkdir(..., 0777)` en uploaders

**Paths**: los tres uploaders mencionados en §2.5. En multi-tenant la permisividad 0777 es innecesaria. Cambiar a `0755`.

---

## 3. Lo que está bien (verificado)

Vale dejarlo escrito para no volver a auditarlo de cero en la próxima iteración.

### 3.1 Aislamiento de queries en la mayoría de repositorios
- `ArticuloRepository`, `CategoriaRepository`, `PedidoWebRepository` y casi todos los demás filtran por `empresa_id` en WHERE consistentemente.
- `ClienteWebRepository::findById` (línea 303) y `updateIfChanged` (línea 152) **sí** validan `empresa_id`. Los 3-4 métodos rotos son la excepción dentro del mismo archivo (de ahí la severidad).

### 3.2 SQL Injection — sin hallazgos
- Todas las queries usan PDO prepared statements con named placeholders. Grep no encontró concatenación de variables en SQL ni `->raw()` con input.
- `normalizeTableName()` en el core valida nombres de tabla con regex estricto.

### 3.3 Resolución de tenant en storefront
- `StoreResolver::resolveEmpresaPublica` busca por slug + `activa = 1 AND modulo_tiendas = 1`. Inicializa `PublicStoreContext` estático para el request.
- `Context::getEmpresaId()` **no tiene fallback dev** habilitado (el flag `$useDevFallback = false`). No hay bypass vía `$_GET['empresa_id']`.

### 3.4 Session management B2B
- `AuthService::attempt` llama `session_regenerate_id(true)` tras login exitoso. ✅
- Session timeout implementado (6h idle, 12h absoluto) en [app/core/App.php:30-79](app/core/App.php:30). ✅
- Cookie con `HttpOnly`, `SameSite=Lax`, `Secure` condicional a HTTPS en [app/core/App.php:17-24](app/core/App.php:17). ✅

### 3.5 Hashing de passwords
- `password_hash(PASSWORD_DEFAULT)` + `password_verify()` en todos los puntos (auth B2B, auth B2C, reset, registro). ✅

### 3.6 Email verification B2B
- `VerificationController` valida expiración 24h, no permite reuso, marca como verificado. ✅

### 3.7 Recálculo de total en checkout
- El server **recalcula** el total desde el snapshot de precios en sesión ([CartService::getTotal()](app/modules/Store/Services/CartService.php)). NO confía en un `total` del POST. ✅

### 3.8 Filename randomization en uploads
- Los archivos se renombran con patrón `{categoria/articulo}_{empresa_id}_{timestamp}_{bin2hex}.{ext}`. No usa `$_FILES[...]['name']` del user → no hay path traversal.

### 3.9 Guards por módulo
- `EmpresaAccessService::requireTiendasAccess()` valida `empresa.activa = 1 AND modulo_tiendas = 1`. Aplicado consistentemente a las rutas `/mi-empresa/*` de Tiendas.

### 3.10 Webhooks de pago
- **No hay integración de pago implementada** (Mercado Pago, Stripe, etc.). Los pedidos quedan en estado `pendiente_envio_tango`. Esto es **por diseño** según el flujo actual, pero es un riesgo latente: el día que se agregue hay que hacerlo con validación HMAC de firma, idempotencia, y confirmación por pull a la API (no solo payload del webhook).

---

## 4. Falsos positivos descartados

Durante la auditoría los análisis automáticos levantaron algunas cosas que verifiqué contra el código y **no son bugs**:

- ❌ "XSS en `$p['id']` sin escapar" en `mis_pedidos/index.php`. **Falso positivo**: `$p['id']` viene de DB como `INT`, no hay path para que contenga HTML. No amerita fix.
- ❌ "XSS en `$pedido['pedido_id']`" en `mis_pedidos/show.php`. Mismo caso.
- ❌ "Context bypass vía `$_GET['empresa_id']`". Verifiqué `Context.php` — el flag `$useDevFallback` está en `false`. No hay bypass.
- ❌ "Exposición de credenciales Tango en responses de pedidos". Verifiqué `PedidoWebRepository::findByIdWithDetails` — no incluye campos sensibles.

---

## 5. Plan de remediación sugerido

Dividido en tramos para no hacer un release monster. Priorizado por impacto real, no severidad nominal.

### Tramo 1 — "Cierre rápido" (release 1.13.0, 1 sesión)
Los fixes chicos, mecánicos, sin debate arquitectónico.

- [ ] 2.4 — Arreglar los 4 métodos de `ClienteWebRepository` agregando `empresa_id` al WHERE.
- [ ] 2.6 — Mensaje genérico en login B2C.
- [ ] 2.7 — `session_regenerate_id(true)` en `ClienteWebContext::login`.
- [ ] 2.8 — Limpiar carrito en logout B2C.
- [ ] 2.9 — Validar `next` relativo en login B2C.
- [ ] 2.10 — Mensaje genérico en login B2B (no leak de exception message).
- [ ] 2.13 — `mkdir` a 0755 en los 3 uploaders.

### Tramo 2 — "Uploads blindados" (release 1.13.1, 1 sesión)
Aislado porque toca 3 módulos y requiere testing manual con archivos reales.

- [ ] 2.5 — Crear `app/core/UploadValidator` centralizado. Migrar `Articulos`, `Categorias`, `EmpresaConfig` al helper.
- [ ] 2.5 — Agregar `.htaccess` en `public/uploads/` que niegue ejecución de PHP.

### Tramo 3 — "CSRF everywhere" (release 1.13.2, 1-2 sesiones)
Es el tramo que más views toca. Se puede hacer progresivo: primero storefront público (carrito + checkout + auth cliente), después backoffice Tiendas, después resto del backoffice.

- [ ] 2.3 — Consolidar `CSRF.php` y `CsrfHelper.php` en uno solo.
- [ ] 2.3 — Agregar método base en `Controller` (o middleware) que valide token en todo POST. Whitelist para webhooks.
- [ ] 2.3 — Inyectar `csrfField()` en todos los forms POST. Audit manual.
- [ ] 2.11 — Validar email format en auto-registro de checkout (se cubre parcialmente con CSRF).

### Tramo 4 — "Auth hardening" (release 1.13.3, 1-2 sesiones)
Requiere crear infraestructura nueva (RateLimiter) y tocar 3-4 módulos de auth.

- [ ] 2.2 — Crear `app/core/RateLimiter` con persistencia en tabla `rate_limit_attempts` (migración nueva).
- [ ] 2.2 — Aplicar en login B2B, login B2C, forgot B2B, forgot B2C, registro B2C, reenvío verificación.
- [ ] 2.2 — Reemplazar PIN de 6 dígitos por token de 32 hex en reset B2C.

### Tramo 5 — "Stock + CSP" (release 1.13.4, 1 sesión)
Dos cosas independientes pero del mismo nivel de ambición.

- [ ] 2.1 — Validación de stock en checkout + transacción con lock. **Requiere decisión previa de Charly**: ¿reserva vs decremento directo? ¿Qué hacemos si Tango falla después?
- [ ] 2.12 — Agregar CSP en modo report-only. Iterar una semana monitoreando violations. Pasar a enforce.

---

## 6. Preguntas para Charly antes de arrancar

Antes de empezar Tramo 1 hay que definir:

1. **¿Querés que empiece ya por el Tramo 1** (fixes mecánicos de bajo riesgo) para cerrar la tanda chica esta semana, o preferís que veamos primero el Tramo 3 (CSRF) que es el que más impacto tiene aunque toca más views?
2. **Checkout + stock (Tramo 5)**: ¿el stock de `articulos.stock_actual` es fuente de verdad confiable entre syncs de Tango, o preferís que el checkout consulte Tango en tiempo real? (hoy no lo hace por decisión estratégica según comentario en `CheckoutService`).
3. **RateLimiter (Tramo 4)**: ¿usamos tabla dedicada en DB, o nos conformamos con `FileCache` para ahorrar migraciones? Lo primero escala mejor con varios frontends, lo segundo es más simple.

Cuando me digas cuál tramo arrancamos, me pongo. No voy a tocar nada hasta que confirmes — esto es una iteración grande y prefiero ir por tramos limpios que mandar un ZIP con 50 archivos modificados.
