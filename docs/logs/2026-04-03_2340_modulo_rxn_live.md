# Implementación Módulo RXN_LIVE V1
Fecha: 2026-04-03 23:40

## Qué se hizo
Se desarrolló e integró de forma completa la versión MVP (V1) del módulo `RXN_LIVE` para reporting y visualización de datos.

### Componentes creados:
1. **Controlador:** `RxnLiveController.php`, maneja el ruteo interno del módulo (`index`, `dataset`, `exportar`).
2. **Servicio (Capa de Acceso a Datos):** `RxnLiveService.php`. Se conectó a la BD legacy existente a través de `App\Core\Database::getConnection()` utilizando sentencias preparadas nativas para filtrar y paginar con total seguridad.
3. **Vistas UI (Frontend):** 
   - `index.php`: Dashboard principal con las tarjetas de acceso a cada Dataset.
   - `dataset.php`: Pantalla particular con filtros genericos, Exportación a CSV, tabla paginada y gráfico renderizado mediante `Chart.js` (incluido vía CDN).
4. **Rutas:** Expuestas en `app/config/routes.php` usando el guard `$requireAnyOperational`.
5. **Vistas SQL:** Generado un archivo `database/rxn_live_views.sql` con las definiciones en DDL estricto para `RXN_LIVE_VW_VENTAS` y `RXN_LIVE_VW_CLIENTES` como punto de anclaje (reemplazable a discreción en el motor final).

## Por qué
Requerimiento de explotar información del sistema pero aislando el riesgo comercial. Se solicitó un módulo de solo lectura respetando a rajatabla la arquitectura actual sin añadir bibliotecas SPA pesadas.

## Impacto
Nulo sobre el resto de las operacionales. RXN_LIVE vive en su propia carpeta bajo el namespace `App\Modules\RxnLive` y sus queries están enfocadas puramente a las vistas Vw para no agotar locks en tablas críticas, utilizando además Paginación estricta y Exportación limitada.

## Decisiones tomadas
- Se utilizó la conexión actual `App\Core\Database` que implementa PDO. Esta persistencia legacy conectada a MariaDB/MySQL sirve como receptáculo de las Vistas solicitadas en la consigna.
- Paginación implementada puramente en SQL y PHP, renderizando por request tradicional.
- Gráficos integrados directamente en frontend renderizando un script simple que adapta variables enviadas por PHP (con mitigación Json_Encode).
- Postergar integraciones complejas (como migraciones de db abstractas u OTA), limitándose a dejar los artefactos SQL legibles en `database/rxn_live_views.sql`.
