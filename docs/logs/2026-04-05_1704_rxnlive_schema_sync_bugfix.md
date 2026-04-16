# Actualización de Esquema de Datos y Re-establecimiento Visual

## Qué se hizo
1. **Actualización del Esquema SQL en Módulo Clientes:** Agregamos una verificación fina en `RxnLiveService::ensureViewsExist()` que inspecciona `information_schema.columns`. Si detecta que faltan las columnas clave en la vista `RXN_LIVE_VW_CLIENTES` (como `estado`, `cantidad`, `fecha_registro` que habían sido añadidas al script SQL posteriormente pero no a la DB local), ejecuta el motor de reconstrucción SQL de las vistas.
2. **Corrección de Gráficos (ChartJS Crash):** Se solucionó la desaparición del "Gráfico Analítico" que explotaba en consola al intentar hacer `parseFloat(undefined)` sobre columnas excluidas.
3. **Tooltip de Tooltip en Reinicio Total:** Se actualizó el título del botón rojo para clarificar sintácticamente que su función reinicia *todo*, incluyendo columnas ("Borrar filtros, re-establecer columnas y recargar el punto de inicio de la vista").

## Por qué
- Había una divergencia entre la metadata local en disco (`RxnLiveService`) y la Base de Datos Local real, ya que el motor original se dio por satisfecho porque el módulo PDS existía y se saltaba la creación de los otros. 
- Al no viajar la columna `estado` requerida por el Gráfico para agrupar (X) en el dataset `Clientes`, se generaba un error fatal de pintado en JS logrando que colapsara por completo el panel y quedara un cuadrado negro sin controles.
- Adicionalmente, el purgado del "Limpiar Filtros" también fracasaba operativamente cuando intentaba lidiar con columnas ocultadas o columnas que literalmente no existían en las entrañas de la vista. Así que todo este desmadre visual del CRUD de Clientes era un síntoma de una Des-sincronización de Base de Datos vs Metadata de código.

## Impacto
- El Dataset de Clientes recobra su Gráfico Analítico por defecto (Gráfico Dona por Estado).
- Garantía de re-construcción automática de vista en cualquier entorno de producción (OTA deployment asegurado).
- Flujo volátil totalmente estable y listo para pasear al usuario.
