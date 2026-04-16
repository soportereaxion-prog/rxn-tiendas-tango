# Control de Cambio: Corrección Integral de Deploy para Producción Linux/Plesk

**Fecha:** 2026-04-02  
**Hora:** 19:45  
**Tipo:** Deploy / Infraestructura  
**Impacto:** Alto — corrección de estrategia de publicación  
**Entorno objetivo:** `https://suite.reaxionsoluciones.com.ar` (Plesk + Apache)

---

## Causa Raíz

El sistema tenía **dos estrategias de deploy coexistiendo** de forma incompatible:

### Estrategia A (obsoleta — eliminada)
El Document Root apuntaba a la **raíz del proyecto** y se dependía de una regla en `.htaccess` raíz:
```
RewriteRule ^$ public/index.php [L]
```
Esto requería que toda URL pública incluyera `/rxn_suite/public/` como base:
`https://dominio.com/rxn_suite/public/login`

### Estrategia B (actual — única oficial)
El Document Root apunta **directamente a `public/`**.  
Las URLs son limpias desde la raíz:
`https://suite.reaxionsoluciones.com.ar/login`

### Por qué coexistían
1. El código fuente (`app/`) fue migrado a Estrategia B en una iteración anterior (ver log `2026-04-02_1611_migracion_path_rxn_suite.md`)
2. **Pero**: la carpeta `build/` en el servidor contenía código generado antes de esa migración
3. El `build/public/.htaccess` tenía `RewriteBase /rxn_suite/public/` (Estrategia A)
4. El `.htaccess` raíz tenía `RewriteRule ^$ public/index.php` (también Estrategia A)
5. El script `tools/deploy_prep.php` copiaba archivos sin post-proceso de limpieza

Resultado: el servidor servía URLs incorrectas o generaba redirect loops dependiendo de cómo estaba configurado el Document Root en Plesk.

---

## Archivos Afectados

### Modificados

| Archivo | Cambio |
|---|---|
| `public/.htaccess` | Agregado comentario explícito de estrategia. `RewriteBase /` confirmado. |
| `.htaccess` (raíz) | Eliminada regla `RewriteRule ^$ public/index.php`. Ahora solo es protección de seguridad. |
| `build/.htaccess` | Mismo cambio que `.htaccess` raíz (coincide en el build). |
| `build/public/.htaccess` | Corregido de `RewriteBase /rxn_suite/public/` a `RewriteBase /`. |
| `tools/deploy_prep.php` | Agregado post-proceso de limpieza de referencias legacy + instrucciones de deploy al final. |
| `docs/deploy/PROCESO_BUILD_Y_DEPLOY.md` | Reescritura completa documentando Estrategia B como única. |
| `.env.example` | Agregadas variables `APP_SESSION_SECURE`, `APP_URL` y sección de correo. |

### Sin cambios (ya estaban correctos)

| Archivo | Motivo |
|---|---|
| `app/config/routes.php` | Todas las rutas ya usan paths limpios (`/login`, `/mi-empresa/*`, etc.) |
| `app/core/Request.php` | `BASE_PATH = ''` — correcto para Estrategia B |
| `app/shared/Services/OperationalAreaService.php` | Paths sin prefijo `/rxn_suite/public` |
| `app/shared/Services/BackofficeContextService.php` | `storeUrl` = `'/' . rawurlencode($slug)` — correcto |
| `app/shared/views/admin_layout.php` | Assets en `/css/`, `/js/` — correcto |

---

## Estrategia Final

**Estrategia B — Document Root en `public/`**

```
Plesk Subdominio: suite.reaxionsoluciones.com.ar
Document Root:    /var/www/vhosts/.../rxn_suite/public
```

El flujo de cada request es:
1. Apache recibe `/login` → busca `public/login` (no existe como archivo)
2. `public/.htaccess` (RewriteBase /) redirige a `public/index.php`
3. PHP procesa — `BASE_PATH = dirname(__DIR__)` apunta a raíz del proyecto correctamente
4. Router recibe `/login` limpio y despacha `AuthController::showLogin()`

Ningún componente del sistema calcula ni depende del path físico del proyecto para construir URLs públicas. Las rutas son absolutas desde `/`.

---

## Riesgos

| Riesgo | Mitigación |
|---|---|
| `build/` sigue teniendo las referencias legacy hasta regenerarse | `tools/deploy_prep.php` ahora limpia automáticamente con post-proceso |
| `composer dump-autoload` no se ejecuta tras subir el build | Documentado explícitamente. Si no se hace, puede fallar PSR-4 en Linux |
| `.env` de producción sin `APP_SESSION_SECURE=true` | Documentado en `.env.example` |
| Colisión entre rutas estáticas del backoffice y `/{slug}` dinámico | El router evalúa rutas estáticas primero; `/{slug}` está al final de `routes.php` |

---

## Validaciones Realizadas

### Código fuente (`app/`)

- [x] **Cero referencias** a `/rxn_suite/public` en `app/`
- [x] Todos los `header('Location: ...')` usan rutas absolutas sin prefijo
- [x] `Request::getUri()` extrae URI limpia con `BASE_PATH = ''`
- [x] `OperationalAreaService` genera paths tipo `/mi-empresa/...`
- [x] `BackofficeContextService` genera `storeUrl` tipo `/{slug}`
- [x] `admin_layout.php` sirve assets con `/css/` y `/js/`
- [x] `routes.php` — ruta `/` redirige a `/login` (no a path hardcodeado)

### Configuración de servidor

- [x] `public/.htaccess` — `RewriteBase /` confirmado
- [x] `.htaccess` raíz — eliminado rewrite a `public/index.php`
- [x] `build/public/.htaccess` — corregido a `RewriteBase /`
- [x] `build/.htaccess` — corregido sin rewrite

---

## Checklist de Deploy en Plesk

### En Plesk Panel

1. **Subdominio** `suite.reaxionsoluciones.com.ar` → **Hosting Settings**
2. **Document Root**: `rxn_suite/public` ← crítico
3. Activar **mod_rewrite** si no está habilitado

### En el servidor (SSH/SFTP)

4. Subir contenido de `/build` a `rxn_suite/` en el servidor
5. Crear `.env` desde `.env.example` con valores de producción:
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_SESSION_SECURE=true
   APP_URL=https://suite.reaxionsoluciones.com.ar
   DB_HOST=...
   DB_NAME=...
   DB_USER=...
   DB_PASS=...
   ```
6. SSH: `cd rxn_suite && composer dump-autoload -o`
7. SSH: `chmod 755 public/uploads`

### Verificación funcional

8. `https://suite.reaxionsoluciones.com.ar/` → redirige a `/login` ✓
9. `https://suite.reaxionsoluciones.com.ar/login` → muestra login sin loops ✓
10. `https://suite.reaxionsoluciones.com.ar/uploads/foto.jpg` → sirve imagen ✓
11. Login → redirige al dashboard ✓
12. URL nunca muestra `/rxn_suite/public` ✓

---

## Decisiones Tomadas

1. **No se modificó el código de negocio** — el código fuente (`app/`) ya estaba correcto. Solo se corrigió la capa de configuración de servidor y el tooling de deploy.

2. **`tools/deploy_prep.php` con post-proceso**: Se agregó un barrido automático post-copia que detecta y limpia referencias legacy. Esto actúa como red de seguridad — si el código fuente se mantiene limpio, no tiene efecto.

3. **`APP_URL` como referencia informativa**: La app NO usa esta variable para construir URLs en runtime. Las rutas son absolutas desde `/`. `APP_URL` se documenta solo para referencia del entorno de producción.

4. **`.htaccess` raíz convertido a "solo seguridad"**: Con Estrategia B, Apache nunca sirve el `.htaccess` raíz directamente al tráfico web (porque el Document Root está en `public/`). El archivo queda como protección a nivel de directorio del sistema de archivos.
