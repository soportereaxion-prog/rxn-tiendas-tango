# Iteración 45 — Release 1.43.2

**Fecha**: 2026-05-02
**Build**: 20260502.3

## Tema

Hotfix sobre 1.43.1: la PWA NO se marcaba como installable completa en Chrome (solo aparecía "Agregar a la pantalla principal" como atajo simple, no "Instalar app" standalone). Más una plantilla de mail de novedades pedida por Charly para sumar al OTA.

## Bugs encontrados (vía DevTools del celu)

**Bug 1 — Iconos 404 en producción**

```
GET https://suite.reaxionsoluciones.com.ar/icons/rxnpwa-192.png 404
GET https://suite.reaxionsoluciones.com.ar/rxnpwa-192.png 404
```

Verificado: los iconos SÍ están en el ZIP del OTA (`public/icons/rxnpwa-192.png` y `512.png`). Si dan 404 en producción, el SystemUpdater no los extrajo o el document root del vhost está desalineado. Hay que verificar en disco después del deploy.

**Bug 2 — Scope ignorado por Chrome (raíz del "no installable")**

```
Manifest: property 'scope' ignored. Start url should be within scope of scope URL.
```

El manifest tenía `start_url: /rxnpwa` (sin slash) y `scope: /rxnpwa/` (con slash). El primero NO está dentro del prefijo del segundo → Chrome ignora el scope → PWA marcada como "instalable parcial" → solo "Agregar a la pantalla principal" como atajo simple.

**Bug 3 — `.htaccess` excluido del OTA**

`SystemUpdater::EXCLUDED_PREFIXES` incluye `.htaccess` con la regla "el config del server es sagrado". Mi `AddType application/manifest+json` agregado al .htaccess local NO se aplica vía OTA. Charly tiene que tocar el .htaccess a mano UNA SOLA VEZ en producción.

## Qué se hizo

### Fix PWA installable

- **`public/manifest.webmanifest`**:
  - `start_url` y `scope` ambos como `/rxnpwa/` (consistentes con trailing slash).
  - Icons separados en 4 entries explícitos (192+512 con `purpose: "any"` y 192+512 con `purpose: "maskable"`) en vez del combinado `"any maskable"` que algunos Chrome buggean.
- **`app/config/routes.php`**: ruta extra `GET /rxnpwa/` que apunta al mismo `launcher()` (Apache no normaliza trailing slash si no hay directorio físico, así que necesitamos las 2 rutas registradas en el router).
- **`public/.htaccess`**: `AddType application/manifest+json .webmanifest` dentro de `<IfModule mod_mime.c>`. Sin esto Apache puede servir el manifest como `text/plain`, que algunos Chrome rechazan silenciosamente. ⚠️ **No se aplica via OTA** — hay que copiarlo a mano al `.htaccess` de producción una sola vez.

### Plantilla mail de novedades

Migración seed `2026_05_02_02_seed_mail_template_novedades_rxn.php` que inserta una plantilla **"Novedades RXN — Newsletter"** en todas las empresas activas (idempotente por nombre + empresa_id, cada tenant tiene su copia editable).

**Características de la plantilla**:
- HTML email-safe (tablas + inline styles, compatible Gmail / Outlook / Apple Mail).
- Header con gradiente dark (`#0f172a` → `#1e293b`), título "✨ Hay novedades en tu suite" + subtítulo.
- Saludo personalizado al cliente: `Hola {{CrmClientes.razon_social}} 👋`.
- Mensaje introductorio breve.
- Bloque dinámico `{{Bloque.html}}` donde el `BlockRenderer` inyecta las cards de `customer_notes` (con sus categorías feature/mejora/seguridad/etc).
- CTA visible: botón **"Abrir Reaxion Suite →"** que va a `/login`.
- Footer con datos de contacto (mail, teléfonos, web) + leyenda "te damos de baja si querés".

Asunto sugerido: `🚀 Novedades de Reaxion Soluciones — {{CrmClientes.razon_social}}`.

Variables disponibles documentadas en `available_vars_json`:
- `{{CrmClientes.razon_social}}`, `{{CrmClientes.email}}`, `{{CrmClientes.documento}}`
- `{{Bloque.html}}` (bloque dinámico via BlockRenderer)

## Por qué

1. **PWA installable**: Charly subió el OTA 1.43.1 al reino y al intentar instalar desde el celu, Chrome solo le ofrecía "Agregar a la pantalla principal" (atajo simple), no "Instalar app" (PWA standalone). Sin instalación standalone, la barra de URL del browser nunca se va a ocultar al navegar entre pantallas, lo que estaba pidiendo desde el principio.
2. **Plantilla mail**: Charly viene queriendo arrancar a usar el sistema de mails masivos para novedades. La plantilla por defecto del seed ("Novedades" id 4) era básica, sin estilo. Esta plantilla nueva queda como base reutilizable y editable.

## Validación

- ✅ Lint PHP en archivos tocados.
- ✅ Migración corre OK en local.
- ⚠️ Falta validación real: Charly tiene que subir el OTA, **tocar el .htaccess a mano una vez**, y reintentar la instalación PWA en su celu.
- ⚠️ Si los iconos siguen dando 404 en producción después del re-deploy, hay que investigar SystemUpdater / document root.

## Acción manual requerida en producción

Después de subir el OTA, **antes** de probar la PWA:

1. Tocar `public/.htaccess` en el servidor y agregar al inicio (o donde corresponda):
   ```apache
   <IfModule mod_mime.c>
       AddType application/manifest+json .webmanifest
       AddType application/x-web-app-manifest+json .webapp
   </IfModule>
   ```
2. Verificar que los archivos `public/icons/rxnpwa-192.png` y `public/icons/rxnpwa-512.png` existen físicamente en el server. Si no, copiarlos manualmente desde el ZIP.
3. Recargar Ctrl+Shift+R en el celu.
4. Probar `Agregar a la pantalla principal` en Chrome — ahora debería pasar a "Instalar app" o instalar como PWA standalone.

## Pendiente

- Investigar por qué SystemUpdater puede no extraer iconos (no confirmado, hipótesis).
- Considerar que el SystemUpdater tenga un mecanismo de "config files dignos de actualizar" (whitelist) en vez de excluir todo lo del config del server.
