# Proceso de Build y Deploy (rxn_suite)

Este documento detalla el procedimiento estándar para generar una versión "limpia" del sistema lista para desplegar en producción, excluyendo elementos residuales de desarrollo y empaquetando ordenadamente los recursos de base de datos.

---

## Estrategia de Deploy: Estrategia B (Document Root = public/)

> [!IMPORTANT]
> **ESTRATEGIA ÚNICA Y OFICIAL DE PUBLICACIÓN**
> El sistema se despliega bajo la **Estrategia B**: el Document Root del subdominio en Plesk/Apache apunta **directamente** a la carpeta `public/` del proyecto.
>
> - ✅ **Correcto:** `suite.reaxionsoluciones.com.ar` → Document Root → `rxn_suite/public/`
> - ✅ Las URLs públicas son: `/login`, `/mi-empresa/...`, `/{slug}`, etc.
> - ❌ **Incorrecto:** Document Root apuntando a la raíz del proyecto (`rxn_suite/`)
> - ❌ **Incorrecto:** Rutas públicas con `/rxn_suite/public/` en la URL (eso era estrategia A, eliminada)

### Diferencia entre estrategias

| | Estrategia A (obsoleta) | Estrategia B (actual) |
|---|---|---|
| Document Root | Raíz del proyecto | `public/` del proyecto |
| URL base | `dominio.com/rxn_suite/public/login` | `dominio.com/login` |
| RewriteBase | `/rxn_suite/public/` | `/` |
| `.htaccess` raíz | Redirigía a `public/index.php` | Solo seguridad, sin rewrite |

---

## Herramienta de Preparación

Se ha implementado un script reutilizable de preparación que automatiza la limpieza y estructuración.

**Ubicación:** `tools/deploy_prep.php`

### Cómo ejecutarlo

Para preparar la build, abra una terminal (consola) en la raíz del proyecto y ejecute:

```bash
php tools/deploy_prep.php
```

### Resultados de la ejecución

El script generará (o sobrescribirá si ya existen) dos carpetas en la raíz de su proyecto:

1. `/build`: Contiene una copia exacta y funcional del sitio _sin basura de desarrollo_.
2. `/deploy_db`: Empaqueta únicamente los recursos oficiales de base de datos.

El script también realiza un **post-proceso de verificación** que detecta y limpia automáticamente cualquier referencia legacy `/rxn_suite/public` que pudiera haber quedado en los archivos copiados. Si el código fuente está limpio (lo esperado), el post-proceso reportará `[OK]` sin cambios.

---

## Estructura de Salida

### 1. `/build` (Sitio Limpio)

**Mecánica Oficial del Proyecto:** La carpeta `/build` siempre representa una copia limpia del estado **ACTUAL** del código fuente al momento de ejecutar el script de preparación.

Esta carpeta contiene todos los archivos PHP, assets, configuraciones `.example` y dependencias necesarias para que el sistema funcione en un servidor web en producción.

**Elementos excluidos intencionalmente:**
- Carpetas y herramientas de desarrollo: `.git`, `.vscode`, `.agents`, `/tools`, `/docs`, `/logs`.
- Base de datos local o dumps sql: `/database` ha sido excluido de la subida web para prevenir fugas de estructura.
- Variables de entorno locales: El archivo real `.env` es excluido para evitar pisar la configuración de producción. Solo se transfiere `.env.example`.
- Archivos experimentales en `public/`: Basura como `test_*.php`, `db-debug*.php`, `cli_*.php`, `*.log`, `dump.php`, y scripts de migraciones de desarrollo son filtrados.
- Temporales de Storage: Se crean las carpetas de `storage/` pero se limpia el contenido JSON y logs que son de desarrollo.

### 2. `/deploy_db` (Base de Datos del Proyecto)

En esta carpeta encontrará reunidos los scripts SQL estructurales del proyecto necesarios para implementar la base de datos vacía o actualizarla en su servidor de producción.

**Elementos agrupados:**
- `schema.sql`: Estructura oficial del sistema.
- `seeds.sql`: Datos iniciales (si aplica).
- `database_migrations_*.php` y scripts anexos: Scripts utilitarios oficiales.

---

## Configuración de Producción en Plesk

### Paso 1: Configurar Document Root

En el panel de Plesk, para el subdominio `suite.reaxionsoluciones.com.ar`:

1. Ir a **Hosting Settings** del subdominio
2. En **Document Root**, ingresar: `rxn_suite/public`
3. Guardar cambios

> [!WARNING]
> El Document Root debe apuntar a `rxn_suite/public` — **no** a `rxn_suite`. Si apunta a la raíz del proyecto, el sistema intentará servir archivos PHP del backend directamente al navegador.

### Paso 2: Configurar SSH / FTP

Subir **el contenido** de la carpeta `/build` (no la carpeta en sí) a la raíz del proyecto en el servidor. La estructura resultante debe ser:

```
{home}/rxn_suite/
├── app/
├── public/         ← Document Root del subdominio
│   ├── .htaccess   ← RewriteBase /
│   ├── index.php
│   ├── uploads/
│   ├── css/
│   └── js/
├── vendor/
├── storage/
├── .env            ← Configurar con valores de producción
└── .htaccess       ← Solo seguridad, sin rewrite a public/
```

### Paso 3: Configurar variables de entorno

Copiar `.env.example` como `.env` en la raíz del proyecto en producción y configurar:

```env
APP_NAME=rxnTiendasIA
APP_ENV=production
APP_DEBUG=false
APP_SESSION_SECURE=true
APP_URL=https://suite.reaxionsoluciones.com.ar

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=nombre_base_produccion
DB_USER=usuario_db
DB_PASS=password_seguro
DB_CHARSET=utf8mb4

MAIL_HOST=...
MAIL_PORT=465
MAIL_USER=...
MAIL_PASS=...
MAIL_SECURE=ssl
MAIL_FROM_ADDRESS=...
MAIL_FROM_NAME="RXN Tiendas"
```

### Paso 4: Regenerar autoload (CRÍTICO para Linux)

En el servidor via SSH:

```bash
cd rxn_suite
composer dump-autoload -o
```

> [!IMPORTANT]
> Linux (ext4) es **case-sensitive** a diferencia de Windows (NTFS). El autoload generado en Windows puede fallar en Linux si los namespaces PSR-4 no coinciden exactamente con los nombres de carpetas. `composer dump-autoload -o` regenera el mapa de clases correctamente en el entorno de destino.

### Paso 5: Verificar permisos

```bash
chmod 755 public/uploads
# O si Apache/Nginx necesita escribir:
chown www-data:www-data public/uploads
chmod 775 public/uploads
```

### Paso 6: Verificar que mod_rewrite está activo

En cPanel/Plesk o via SSH:

```bash
apache2ctl -M | grep rewrite
# Debe mostrar: rewrite_module (shared)
```

---

## Configuración de Base de Datos en Producción

> [!IMPORTANT]
> El punto exacto del sistema que gestiona la conexión a la base de datos en `rxn_suite` se encuentra en:
> **`app/config/database.php`**

Este archivo **NO** debe modificarse directamente. Está preparado automáticamente para alimentarse de un sistema de variables de entorno, leyendo primero del archivo `.env` en la raíz (si existe) y alternativamente de las variables de entorno directas del sistema (`getenv`).

### Flujo estricto de conexión

1. **`public/index.php`**: Lee `.env` e inyecta variables al entorno
2. **`app/config/database.php`**: Mapea variables del entorno a array de configuración
3. **`app/core/Database.php`**: Singleton PDO que usa ese array para conectar

---

## Checklist de Deploy

### Pre-deploy (local)

- [ ] Ejecutar `php tools/deploy_prep.php` y verificar que el post-proceso reporta `[OK]` (sin referencias legacy)
- [ ] Verificar que `build/public/.htaccess` tiene `RewriteBase /`
- [ ] Verificar que `build/.htaccess` NO tiene `RewriteRule ^$ public/index.php`

### Deploy en servidor

- [ ] Subir contenido de `/build` a `rxn_suite/` en el servidor
- [ ] Configurar Document Root del subdominio a `rxn_suite/public`
- [ ] Crear `.env` en raíz con variables de producción
- [ ] SSH: `composer dump-autoload -o`
- [ ] SSH: `chmod 755 public/uploads`
- [ ] Verificar que `public/.htaccess` está presente en el servidor

### Verificación post-deploy

- [ ] `https://suite.reaxionsoluciones.com.ar/` → redirige a `/login`
- [ ] `https://suite.reaxionsoluciones.com.ar/login` → muestra formulario sin loop
- [ ] `https://suite.reaxionsoluciones.com.ar/uploads/imagen.jpg` → sirve archivos
- [ ] `https://suite.reaxionsoluciones.com.ar/{slug}` → muestra tienda pública
- [ ] Login funciona y redirige al dashboard
- [ ] NO aparece `/rxn_suite/public` en ninguna URL del navegador

---

## ATENCIÓN: Resolución PSR-4, Namespaces y Linux (Case-Sensitivity)

**Problema común:** Al desarrollar en Windows (NTFS), el sistema operativo ignora las mayúsculas/minúsculas en las carpetas. Por ejemplo, si tienes una carpeta `app/modules/empresas` pero la clase define `namespace App\Modules\Empresas`, en Windows el autoloader de Composer lo encuentra sin problemas. Sin embargo, en Linux (ext4), esto provocará **"Class not found"** o **"Failed to open stream"** de forma inmediata.

**Solución Implementada:**
1. **Reglas estrictas de Build:** El script `tools/deploy_prep.php` fuerza dinámicamente un formateo estricto `TitleCase` para todas las bases modulares durante la copia a la carpeta `/build`.
2. **Autoload Classmap:** Ejecutar `composer dump-autoload -o` en el servidor después de subir los archivos.
