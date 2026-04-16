# Estandarización de Base Path para Despliegue Producción: `/rxn_suite`

## Fecha y Hora
2026-04-02 16:11

## Propósito
Migrar la aplicación completa y de forma definitiva desde la denominación local `/rxnTiendasIA` hacia su path productivo final `/rxn_suite`. Todo despliegue de aquí en adelante asume y exige la publicación bajo esa carpeta para poder acceder y enrutarse correctamente.

## Requerimientos Abordados
1. Reemplazo íntegro y quirúrgico en controladores, redirecciones, javascripts asíncronos y reglas `.htaccess`.
2. Conservación escrupulosa de Namespaces, clases, base de datos y lógica funcional estructural.
3. Asegurar validación en Linux y coherencia entre `deploy_prep.php` y enrrutamiento global.
4. Actualizar las advertencias en la documentación base.

## Alcance del Reemplazo
- **.htaccess (public/):** El `RewriteBase` fue establecido en `/rxn_suite/public/`.
- **Rutas y Controladores:** Un total de 136 archivos fueron escaneados y adaptados. Principalmente archivos como `app/config/routes.php` (ej. `header('Location: /rxn_suite/public/login')`) y numerosos controladores modularizados que expulsaban redirecciones tras transacciones CRUD exitosas o con error.
- **Componentes y Vistas:** Todos los archivos `*.php` que construían rutas absolutas o hardcodeadas.
- **JavaScript (Frontend):** Peticiones AJAX asíncronas de módulos clave (`rxn-crud-search.js`, `rxn-shortcuts.js`, `rxn-row-links.js`) han sido ajustados de raíz para invocar la nueva base `rxn_suite/public/`.

## Medidas de Seguridad
- No se tocaron `namespaces` (siguen validando con PSR-4).
- No se interfirieron rutas físicas internas de la maquina virtual, solo resolución Web (Front/URL).
- Componentes de `/storage` y `/vendor` y `/deploy_db` quedaron blindados en el empaquetado inicial.

## Compatibilidad de Linux Comprobada
Se mantuvo la coherencia forzada de `TitleCase` implementada previamente en el script de build. Las URI frontales en minúsculas `/rxn_suite/...` impactan directamente contra public/index.php. Posteriormente es el autoloader interno de PHP/Composer quien resuelve el casing. Como ambas partes coinciden ahora sin fallos de "Class not Found", la migración web se empalma perfectamente con el backend de Linux.
