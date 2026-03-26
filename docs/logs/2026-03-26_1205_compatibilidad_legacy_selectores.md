# [CONFIGURACIÓN] — Compatibilidad Legacy en Selectores Connect

## Contexto
Durante el despliegue funcional de los **Selectores Dinámicos de Tango Connect** (ver log *2026-03-26_1156_connect_selectores*), Jefatura advirtió críticamente el riesgo de **destrucción de datos legacy**. Al inflar asíncronamente los menús `<select>` con datos inyectados por JavaScript desde la API de Axoft, cualquier registro persistido históricamente en DB (ej: depósitos con el string `00`, códigos manuales) que NO tenga correspondencia exacta (Match) en la respuesta JSON actual de Tango, quedaría huérfano y purgado del HTML.

Al guardarse el formulario "vacío", el sistema en cadena aniquilaría ese campo en la DB (sobreescritura destructiva silente).

## Decisión: Migración Automática vs UI Transitoria
### ¿Por qué no migrar en DB?
Los códigos históricos como `"00"` en Depósitos referencian al Código Visual de sistema del usuario (`CODIGO_DE_DEPOSITO`), pero la API estricta usa `ID_STA22` (Integer Proxy Key). Axoft **no provee** diccionarios automáticos bidireccionales en su endpoint 17668 para interpolar `00` y transformarlo mágicamente a `ID = 1` u `8`. Un UPDATE masivo correría el peligro de desincronizar toda la cadena logística, asignando sucursales equivocadas.

### Implementación
Se optó por una **Compatibilidad Transitoria de Interfaz (Rendering)** mediante Soft Fallbacks en JS.

## Algoritmo Implementado (`views/index.php`)
Al detonar `populateTangoSelects(data)`:
1. El JS itera todo el catálogo ofrecido por Tango y evalúa si el atributo `data-original` (el volcado PHP de la DB local) hace `match` con la realidad.
2. Si hubo match -> Perfecta armonía, se usa el de la API.
3. Si la iteración termina y **no hubo coincidencia**, el algoritmo fabrica y APENDE un `<option>` intrusivo adicional para salvar la integridad de esa request:
   `<option value="00" selected>⚠️ Cód obsoleto, elegí uno de arriba (Valor DB: 00)</option>`
4. **Respuesta funcional**: El usuario ve el warning. Puede cambiarlo al correcto si lo desea. O puede ignorarlo temporalmente y al Guardar, **el sistema inserta en DB exactamente el mismo código huérfano "00"**. Integridad de tabla %100 asegurada bajo un estado Transitorio-Asistido.
