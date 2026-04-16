# 2026-04-07 04:05: Handover a Claude para Debugging Profundo de JS (RXN Live)

## Objetivo del Documento
Este documento sirve como un volcado de estado y contexto exacto para que el Agente Interviniente ("Claude") pueda retomar el hilo de depuración sobre un problema severo en `app/modules/RxnLive/views/dataset.php`, el cual provoca que al cargar la página NO se rendericen los componentes y la tabla de información ("Vista Plana") quede completamente en blanco (invisible).

## Estado de Visualización Reportado por el Rey
1. **Contenedores de UI vacíos**: La pestaña del "Gráfico Analítico" se ve gris y oscura (sin contenido), al igual que el listado `planaResultContainer`.
2. **Badge TotalRegistros Reactivo**: Curiosamente, el badge HTML superior (renderizado de lado de backend PHP) imprime \`93 regs\` (funcionando bien y probando que los datos JSON de `rawDatasetRows` sí se inyectan correctamente al front-end).
3. **Consola y Errores JS**: La DevTools captura hasta **7 Errores de JS** al recargar la pantalla (`ctrl+f5`), según la última captura.
4. **Almacenamiento (Storage)**: El `sessionStorage` y `localStorage` pertinentes al módulo parecieran no tener datos volátiles poblados o la ejecución se detiene antes de guardarlos.

## Hipótesis Previas y Fixes Ya Aplicados (Por Lumi)
Antes de escalar el problema, Lumi abordó los siguientes ángulos:

1. **Bug `TypeError` por null en diccionarios**:
   * **El problema:** Funciones como `applyLocalFilters()` hacían check y loop ciego sobre `flatDiscreteFilters`, el cual, si venía nulo o corrompido, lanzaba un `TypeError` fatal.
   * **Solución aplicada:** Se insertaron comprobaciones de tipado explícito (`typeof === 'object'`) al hidratar configuración en `applyViewConfig()`. También se protegieron las validaciones dentro del loop de `renderPlana()`.

2. **Pérdida de `<option selected>`**:
   * **Solución aplicada:** Se insertó la validación condicional desde el Backend (PHP) usando el parámetro HTTP GET `view_id`, de modo que el `[ VistaDetalles ]` sea seleccionado nativamente en el Dropdown del HTML desde que nace el DOM.

3. **Template Literal Sin Cerrar (Syntax Error)**:
   * **El problema:** Lumi detectó y cerró un \`\`\`(backtick)\`\`\` faltante al final del string concatenado en `buildDiscreteDropdown()`, el cual formaba un error letal `Unterminated template literal`.
   * **Solución aplicada:** Se validó el cierre en `html += '</div>\`;'`.

## Manda al Nuevo Cerebro (Instrucciones para Claude)

1. **Analizar la causa raíz profunda (Esos 7 Errores)**: Lumi mitigó la sintaxis obvia, pero el ciclo de vida `DOMContentLoaded` o la rehidratación final en `dataset.php` (específicamente la triada `applyViewConfig` -> `applyLocalFilters` -> `renderPlana`) todavía revienta frente a casos anómalos o de cache.
2. **Revisar `json_encode($datasetRows)`**: Validar si `rawDatasetRows` está rompiendo el Parser JS en su inicialización a pesar de los fixes lógicos si existen datos sucios.
3. **Puntos Críticos de Interrupción**: 
   - Líneas de inicialización: `let u = new URL(...)`
   - Función `renderDynamicChart()`: ¿Estará fallando alguna función que interactúe con el Canvas causando Exception si es invocado antes de tiempo? (Especialmente `window.dispatchEvent(new Event('resize'))`).
4. **Mandato Principal**: Resucitar la UI para que los filtros y reportes vuelvan a graficar, restaurar memoria, y devolver el reino funcional.

¡El Rey espera una iteración precisa y milagrosa! 👑✨