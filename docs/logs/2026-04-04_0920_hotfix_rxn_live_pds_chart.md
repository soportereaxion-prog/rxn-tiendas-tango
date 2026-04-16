# Modificaciones RXN LIVE - DataSet PDS Tiempos (Hotfix chart_group_col)

## Fecha y Cambio
- **Fecha:** 2026-04-04 09:20
- **Versión:** 1.1.50
- **Cambio:** Hotfix al motor de agregación de gráficos para RXN_LIVE.

## Qué se hizo
- Se corrigió el archivo `app/modules/RxnLive/RxnLiveService.php`.
- Se reemplazó el valor `chart_group_col` de `'tecnico'` a `'usuario'` en la configuración del dataset `pedidos_servicio`.

## Por qué
- Al haber renombrado la salida SQL a `usuario` (coincidiendo con UI), el motor de gráficos (`getChartData()`) seguía intentando agrupar (`GROUP BY`) por la columna `tecnico`.
- MariaDB rechazaba la consulta (`Unknown column`), lo que derivaba en una excepción controlada pero que generaba un reseteo de los paquetes de datos JSON entregados al frontend (gráfico y pivot vacíos).

## Impacto
- Restablecimiento integral de los datos en el dashboard de RXN_LIVE (tanto en gráfico de barras, como panel pivot y la sábana de la grilla de datos pura).
