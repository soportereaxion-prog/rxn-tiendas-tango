# [CONFIGURACIÓN] — Solución Estructural de Nodos Tango Connect

## Causa Raíz
Gracias a la telemetría instalada y disparada por Jefatura, se aisló que Axoft Connect encapsula sus arrays de retorno bajo un wrapper específico `resultData` que no formaba parte de nuestra convención estándar:
`data['data']['resultData']['list']`

Al estar nuestro código apuntando erróneamente a `data['data']['list']`, el Backend interpretaba consistentemente una "Lista Vacía". Esto desencadenaba una onda expansiva forzando las salvaguardas (Advertencias amarillas en pantalla de *No Match* o *Código obsoleto*) al creer que el ID proveniente de nuestra MySQL estaba huérfano de los catálogos.

## Solución Definitiva (Normalización y Silencio)
Se refactorizó completamente `TangoApiClient.php` y su parseo frontal:
1. **Nodos**: Se ruteó la recolección matemática directamente a `['resultData']['list']`.
2. **Llaves Obligatorias**: Acatando el instructivo técnico, los extractores numéricos fueron esterilizados para cazar específicamente las variables:
   - Para Depósitos Mapea `ID_STA22` (Proxy Value) -> `COD_DESCRIP` (String Label).
   - Para Listas Mapea `ID_GVA10` (Proxy Value) -> `COD_DESCRIP` (String Label).
3. **Purgado de Salvaguardas Agresivas (UI UX)**: Respondiendo a las regulaciones de Jefatura (*"NO depender de warnings para suplir falta de datos / Mostrar estado por defecto si no hay match"*), todo el código Javascript intrusivo de "⚠️ No Matchea API" fue removido de _views/index.php_. 

### Comportamiento Resultante:
- Si no hay match (ID es extremadamente antiguo o irrelevante), el Select sucumbe silenciosamente a `-- Sin asignar --`.
- Si el ID tiene match en Tango Connect, el select se bloquea instantáneamente sobre el Label legible (Ej: "1 - Venta Mayorista"), tal y como se pretendía que fuera desde el Sprint 1.
