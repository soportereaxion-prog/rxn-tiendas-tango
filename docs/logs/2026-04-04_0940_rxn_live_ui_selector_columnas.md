# Modificaciones RXN LIVE - DataSet UI (Selector de Columnas)

## Fecha y Cambio
- **Fecha:** 2026-04-04 09:40
- **Versión:** 1.1.52
- **Cambio:** Implementación de visibilidad de columnas por usuario con persistencia en exportación.

## Qué se hizo
- **UI (Selector)**: Se integró un botón `dropdown` en `dataset.php` con ícono `bi-layout-three-columns`. Se construyó su lista desplegable dinámicamente vía Javascript a partir del diccionario de columnas del metadata actual.
- **Lógica de Estado (Frontend)**: 
  - La función `renderPlana()` chequea en cada iteración de tabla si la columna pertenece al grupo apagado `hiddenColsArray = []`, en cuyo caso no inyecta ni las cajas de filtrado superior ni los datos `<td>` en la renderización html.
  - Al cargar una Vista pre-grabada (`loadSelectedView()`), automáticamente se vuelve a pintar la interfaz del Checkbox Selector para coincidir visualmente con el estado oculto y mostrar/ocultar correctamente la grilla.
- **Motor de Exportación (Backend / Frontend)**:
  - Se agregó un listener `submit` en el `<form action="/rxn_live/exportar">` para que el navegador inyecte `input[type="hidden"]` con un JSON dinámico avisando explícitamente cuáles columnas apagó el operador.
  - En el backend (`RxnLiveController.php -> exportar()`), previo al bucle de Spout (Excel) o `fputcsv`, se intersectan los resultados SQL extraídos (`$data`) barriendo mediante un `array_map/unset()` únicamente las keys informadas como `hidden_cols`. Esto unificó la visión en pantalla con la bajada del archivo estandarizando 100% lo que ve el usuario vs. lo que exporta.

## Compatibilidad
Si un usuario pre-filtró por una columna, y luego "apaga" dicha columna para que no interfiera en la UI, el filtro de contexto original seguirá existiendo y funcionando silenciosamente (el sistema sigue devolviendo X registros basados en ese filtro, pero simplemente achica la cantidad de columnas dibujadas en cada uno).
