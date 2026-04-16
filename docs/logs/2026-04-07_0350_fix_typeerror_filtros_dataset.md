# 2026-04-07 03:50: Corrección de Carga Crítica en Data Grid (TypeError)

## Qué se hizo
Se identificó y corrigió un error crítico en el motor de renderizado de la Vista Plana (`dataset.php`) que provocaba que la grilla de datos y los filtros locales se volvieran invisibles (vacíos) al presentarse un estado corrompido o al guardar vistas.
Además, se aseguró de que las opciones del selector de vistas se rendericen con el atributo `selected` de HTML correcto, mitigando la pérdida visual del selector en la recarga.

## Por qué
Cuando un usuario creaba una Vista y el motor JSON guardaba los campos `flatDiscreteFilters` o `flatFilters` como nulos/vacíos en su respectiva hidratación (o si la limpieza de estados de JavaScript no infería que debían ser Inicializados como un Objeto `{}` en vez de `null`), el motor generaba una excepción `TypeError` silenciosa.
Al ocurrir esto dentro del ciclo de re-hidratación de persistencia (`applyViewConfig()`), el bucle de dibujado de la tabla `renderPlana()` sufría una interrupción, dejando la ventana vacía y sin inputs de filtrado locales, dando la ilusión de un cuelgue general.

## Dónde (Impacto)
- `app/modules/RxnLive/views/dataset.php`
  - Se añadieron verificaciones rigurosas tipo `typeof ... === 'object'` para garantizar que la asignación de variables vitales durante el ciclo de `applyViewConfig` respete objetos seguros en lugar de caer en `null`.
  - Se fortaleció la asignación dentro del loop `renderPlana` asegurando que las comprobaciones de booleanos sean cortocircuitadas de forma segura (`flatDiscreteFilters && ...`).
  - Se integró el bloque de chequeo `$isSelected` directamente al loop generador en PHP del Dropdown `savedViewsDropdown`.

## Aprendizajes / Decisiones (Learned)
Siempre se debe ser paranoico con el estado almacenado en JSON persistente, ya que cualquier clave manipulable u omitida por el Browser/DB puede hidratarse como `undefined` o `null`, causando la destrucción encadenada del render en el cliente. Cortocircuitar booleanos de forma explícita previene comportamientos fantasmas ("ghost empty states").