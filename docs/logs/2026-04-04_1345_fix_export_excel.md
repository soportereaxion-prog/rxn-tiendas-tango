# Fix: Estabilización de Exportación Excel (RXN Live)

**Fecha:** 2026-04-04 13:45
**Autor:** Lumi

## Problema Detectado

El módulo de reportes **RXN Live** presentaba dos incidentes críticos al momento de exportar grandes volúmenes de datos hacia Excel (`.xlsx`):

1. **Corrupción Estructural (Error Excel):** Microsoft Excel rechazaba la apertura de los archivos descargados argumentando que "el formato o la extensión no son válidos".
2. **Ignorado de Filtros (Race-Condition):** Si el operador tipeaba un término en las cajas de búsqueda de variables y, sin hacer click afuera de la caja, apretaba de forma inmediata el botón "Exportar Excel", el archivo resultante exportaba toda la tabla perdiendo el filtro escrito.

## Análisis y Correcciones Implementadas

### 1. Resolución de Fatal TypeError en OpenSpout v4

Durante la transición al modo híbrido Dark/Light para Excel, se parametrizó la customización de las filas utilizando el constructor nativo de `OpenSpout`. 
En la versión moderna (`v4`), la firma estricta de `Row::__construct` requiere que el segundo parámetro sea un número (Altura en centímetros), a diferencia de versiones anteriores que admitían el objeto `Style`.
Al haberle transmitido el empaquetado de estilo en dicho parámetro, el intérprete PHP se abortaba con un `Fatal TypeError`. Como las cabeceras HTTP (`Content-Disposition: attachment`) ya se encontraban emitidas, el error textual html se descargaba con la extensión forzada `.xlsx` colisionando de frente contra la validación nativa de Microsoft Excel al intentar leer un ZIP OpenXML inexistente.

**Solución:** Se reemplazó el ensamblaje manual mediante el método puente nativo `\OpenSpout\Common\Entity\Row::fromValuesWithStyle()`, además de añadir una validación preventiva de limpieza de buffer temporario previo `ob_end_clean()` para sellar herméticamente posibles avisos (warnings o returns) del entorno CRM.

### 2. Bypass de Serialización Temprana (Race Condition)

El segundo error correspondía a un "Race-Condition" (Condición de carrera) en compiladores modernos de ecosistemas (Chromium/Safari). 
En particular, el formulario originaba que, si se clickeaba el botó de submiteo con foco activo en el campo, el navegador armaba y mandaba la cadena de serialización POST inicializando la petición HTTP nanosegundos **antes** de que el recolector de eventos Javascript (`addEventListener('submit')`) completase procesar e indexar las cajas de texto que simulaban los "filtros" aplicados. 
En consecuencia, el Excel se renderizaba sobre una tabla libre de parámetros dinámicos.

**Solución:** Se transformó el ecosistema `dynamic-export-input`. Se eliminó la inyección temporal al momento exclusivo del submit, vinculándola pasivamente a la función reactiva `updateExportForm()` atada interiormente a `renderPlana()`. De esta forma, el formulario HTML preexiste siempre perfectamente "Hardcodeado", garantizando confiabilidad transaccional sin importar la velocidad de tipeo/click del usuario.

## Impacto
El generador nativo fue estabilizado. El versionado pasa a release y todas las exportaciones a archivo nativo Office validarán de manera confiable.
