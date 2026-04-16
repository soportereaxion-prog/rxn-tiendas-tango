# Corrección de Compatibilidad PSR-4 para Entornos Linux (Case-Sensitive)

## Fecha y Hora
2026-04-02 15:37

## Problema Detectado
Al realizar despliegues desde Windows a hosting Linux, se reportaban errores severos como `Class not found` o `Failed to open stream` en rutas estructurales como `include(.../app/core/App.php)`. Si bien la aplicación funcionaba en Windows y el entorno local, se caía en producción remota.

## Causa Raíz Diagnósticada
La discrepancia radica en el comportamiento Case-Insensitive (NTFS de Windows) frente al Case-Sensitive (ext4 de Linux) en conjunto con el estándar de autocarga PSR-4 de Composer:
1. Las clases modularizadas tenían namespaces en formato `TitleCase` (ej: `namespace App\Modules\Empresas;`).
2. Sin embargo, en el repositorio físico (y mantenido silenciado por Git y Windows) aquellas mismas carpetas de módulo y servicios estaban nombradas en formato `lowercase` (ej: `app/modules/empresas/`).
3. Composer y el script `/tools/deploy_prep.php` empaquetaban la carpeta heredando el lowercase original detectado por el NTFS y el historial de Git, provocando un desfasaje estructural indetectable en Windows pero catastrófico en un file-system de Linux estricto.

## Acciones Tomadas
1. **Renombrado Estricto en Git (Vía Consola Local):** 
   Se forzaron modificaciones en el tracking de Git renombrando `app/modules/auth`, `clientes`, `dashboard`, `empresas`, `pedidos`, `productos` y de igual forma en `app/shared/helpers` y `services` hacia sus variantes con letra capital para corresponder uniformemente a la estandarización PSR.
2. **Actualización de Referencias Duras:**
   Se sustituyeron strings de renderizado rígido dentro de archivos PHP como `routes.php` y controladores donde se llamaba a rutas relativas hardcodeadas que aún referenciaban a minúsculas (`View::render('app/modules/empresas/...`).
3. **Parchado del Script de Build Oficial (`tools/deploy_prep.php`):**
   A partir de este momento, el empaquetado intercepta y transforma estructuralmente todos los directorios nativos (`core`, `modules`, `shared`, `config`, `storage`). Para la carpeta de infraestructura se obliga a utilizar `Infrastructure` (con I grande) e implícitamente fuerza `TitleCase` a todo el interior del directorio lógico de módulos y recursos al generar el directorio remoto `/build`.
   > **Razón Operativa:** Esto otorga invulnerabilidad transversal en las builds; incluso si NTFS o un desarrollador arruinan el casing en local, la compilación de la build en el `deploy_prep.php` jamás fallará al trasladarse a Linux.
4. **Documentación Extendida de Respaldo:**
   Se incorporó en `docs/deploy/PROCESO_BUILD_Y_DEPLOY.md` una alerta obligatoria indicando que la Caché / `Classmap` de `vendor/composer/autoload_classmap.php` generada localmente en Windows podría arrastrar metadatos antiguos, estipulando la orden oficial de correr `composer dump-autoload -o` sobre el entorno de la nube para regenerarlo debidamente bajo su propia indexación Linux, de ser necesario.

## Impacto
El proyecto preservó intacto su núcleo de negocio (Regla Global de Oro). Se purgaron las fisuras de compatibilidad de cross-environments de modo escalable, sellando la mecánica oficial con un mecanismo de resiliencia automatizado contra descuidos en File-System futuros.
