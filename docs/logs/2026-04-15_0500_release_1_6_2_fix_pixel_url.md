# Release 1.6.2 — fix URL del pixel de tracking

## Fecha y tema
2026-04-15 05:00 — Hotfix puntual para que el tracking de aperturas funcione end-to-end.

## Problema detectado

Charly deployó v1.6.1 en prod, mandó envío de prueba con URL correcta del CRM en n8n, el mail llegó perfecto CON el pixel inyectado:

```html
<img src="https://suite.reaxionsoluciones.com.ar/m/open/08012c2a8d5c5ab3717669efe77bb81a132a25cb4c03c15e.gif" ...>
```

Pero al pegar esa URL directamente en el browser → **404 Not Found**.

## Causa raíz

Mirando `app/core/Router.php` línea 42:

```php
$pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<\1>[a-zA-Z0-9_-]+)', $route);
```

El charset permitido para los parámetros dinámicos de las rutas es `[a-zA-Z0-9_-]`. **No incluye el punto**. Entonces `/m/open/abc123.gif` no matchea con `/m/open/{token}` porque el `.gif` no cae en el regex.

El 404 se devuelve en el Router (línea 54) sin llegar nunca al `TrackingController::open`.

## Fix

Cambio en `Services/BatchProcessor.php::injectTracking()`:

```php
// ANTES:
$pixelUrl = $trackingBase . '/m/open/' . rawurlencode($token) . '.gif';

// AHORA:
$pixelUrl = $trackingBase . '/m/open/' . rawurlencode($token);
```

El browser igual carga la imagen porque el response de `TrackingController::open()` lleva `Content-Type: image/gif` en los headers — el sufijo `.gif` en la URL era cosmético, no funcional.

El `clickUrlPrefix` se queda tal cual (`/m/click/{token}?u=...`) porque no tenía puntos en el path — siempre funcionó.

## Decisión: no tocar el regex del Router

Agregar el punto al charset del Router (cambiar a `[a-zA-Z0-9_.-]+`) sería un fix más robusto y cubriría cualquier ruta futura con extensions/sufijos. PERO afecta a TODAS las rutas del proyecto. Revisé las rutas existentes: todas usan `{id}` numérico o `{token}` hex, no hay riesgo real. Sin embargo, cambiar un regex transversal merece más cautela. Queda documentado como opción para el futuro si se necesita — por ahora el fix en BatchProcessor es suficiente.

## Impacto

- **Solo afecta mails NUEVOS** que se envíen después del deploy del 1.6.2.
- **Los mails anteriores** (con `.gif` en la URL del pixel) siguen rotos para tracking de aperturas — esos clicks/opens NO se van a registrar porque la URL ya partió hacia los inboxes.

## Test post-deploy

1. Disparar un envío nuevo.
2. Abrir el fuente del mail recibido, copiar la URL del pixel.
3. Pegar en browser → **debería dar pixel transparente + 200 OK** (antes daba 404).
4. Refrescar el monitor del envío → **Aperturas totales: 1** (antes: 0).
5. Para testear clicks: editar la plantilla, agregar `<a href="https://reaxionsoluciones.com.ar">acá</a>`, disparar envío nuevo. Clickear el link en el mail → redirige al sitio + el monitor muestra Clicks totales: 1.
