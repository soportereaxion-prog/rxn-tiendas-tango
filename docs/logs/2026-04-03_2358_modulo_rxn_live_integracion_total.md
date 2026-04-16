# RXN_LIVE V1 - Integración Total y Auto-Saneamiento
Fecha: 2026-04-03 23:58

## Qué se hizo
Se completó la fase definitiva del MVP del módulo `RXN_LIVE` de reportes. En esta iteración se abordó la integración a la navegación centralizada y la mitigación de pasos manuales de base de datos.
1. **Inyección en Dashboards:**
   Se incorporó la tarjeta de navegación `reporting` hacia la ruta `/rxn_live` dentro de los módulos `crm_dashboard`, `tenant_dashboard` y `admin_dashboard`. El módulo ya es alcanzable normalmente sin recordar URLs.
2. **Auto-instalación (Self-Healing):**
   Acatando la restricción de NO usar el motor de migraciones actual (OTA), se le otorgó autonomía a `RxnLiveService`. Durante su construcción, invoca `ensureViewsExist()` el cual verifica idempotentemente la presencia de las vistas y, de no existir, corre el script puro `database/rxn_live_views.sql`.
3. **Enriquecimiento del Endpoint de Datos:**
   Se expandió a 3 datasets principales: `ventas_historico`, `ventas_estados` (novedad) y `clientes`. La estructura de vistas fue modificada para exponer la columna `cantidad` directamente en MySQL y los gráficos ahora respetan tipos dinámicos (`bar`, `doughnut`, `pie`) mandados desde la declaración del backend.

## Por qué
Para dejar de lado el enfoque "teórico" y entregar un módulo MVP empacado, finalizado, listo para usarse desde el minuto cero que un usuario inicie sesión y haga clic, sin obligar al DBA a ejecutar queries manualmente en entornos productivos.

## Impacto
El módulo queda operativo y expuesto de forma unificada. La carga de vistas ocurrirá **una sola vez** ante el primer acceso al módulo, aliviando la transacción y resolviendo el despliegue físico instantáneo.
