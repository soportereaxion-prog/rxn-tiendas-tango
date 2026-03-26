# Ajuste Correctivo — Selectores Tango Connect y Macheo de IDs

## Contexto y Síntoma
Se detectaron advertencias falsas de incompatibilidad legacy (`⚠️ No matchea API / Cód obsoleto`) en la vista `EmpresaConfig`, a pesar de que los valores configurados originalmente (ej. Lista 1, Depósito 1) **SÍ** existían semánticamente en Tango. Las advertencias de Fallback bloqueaban la interfaz correcta indicando que "1" no existía en la respuesta API.

## Causa Raíz Detectada
El problema era multifactorial y se originó en la Iteración inicial:
1. **Falta de Endpoints Específicos**: Originalmente se emplearon los endpoints *transaccionales* limitados `17668` (Stock) y `20091` (Precios) intentando deducir los catálogos extrayendo el `ID_STA22`. El Operador, sin embargo, ingresaba la Clave Proxy visual (`CODIGO` = `1`), pero el JSON de stock ofrecía su Clave Subrogada ID (`3` o `8`). Por tanto, si el humano guardaba `1`, el comparador buscaba el ID_STA22 `1` (que quizá ni existía), fallando el match.
2. **Padding Binario y Tipado de Tango**: Tango Connect extrae nativamente los Varchars emulados del SQL Server `EmpreB`. El campo `CODIGO` suele presentarse como `string(" 1 ")` o `string("01")`. El Backend Tienda guardaba `"1"`, con lo cual el JS fallaba en equiparar `(" 1 " == "1")` debido al espaciado duro del payload.

## Solución Ejecutada

### 1. Rectificación de Endpoints y Campos (Mappers)
Se reescribieron los Extractores en `TangoApiClient.php` apuntando a las entidades maestras facilitadas por Jefatura:
- **Depósitos**: `process = 2941`
- **Listas de Precios**: `process = 984`

### 2. Algoritmo Caza-Llaves y Saneamiento (Heurística de Casteo)
Como los nombres de las columnas en la API pueden fluctuar sutilmente (`CODIGO_DE_DEPOSITO`, `CODIGO`, `ID_GVA10`), se construyó un Parseador Agnóstico en PHP que:
1. Busca el primer `key` del renglón que contenga `COD` o `ID`, y el segundo que contenga `DESC` o `NOMB`.
2. **Saneamiento Defensivo**: Al atributo `ID` se le aplica un `trim()` agresivo, casteo explícito a String, y una evaluación aritmética `($id + 0)` si resulta numérico, lo cual pulveriza los ceros a la izquierda (`01` -> `1`) y extrae los espacios (` " 1" ` -> `1`).
3. El FrontEnd JS recibe ahora un Array Inmaculado `$depositos["1"] = "DEPOSITO CASA CENTRAL"`.

### 3. Resolución del Match
Con el JSON sanitizado devuelto por `/mi-empresa/configuracion/tango-metadata`, el motor Javascript (`index.php`) logra un Match perfecto `origDep == d.id` (Ej: `"1" == "1"`). La advertencia obsoleta se apaga naturalmente (pues `$match = true`), y el select toma su atributo `selected` cargándose de la Descripción limpia.

## Resultado
Compatibilidad absoluta. Los catálogos son extraídos nativamente de sus Endpoint Maestros nativos. Los datos preexistentes resucitan automáticamente de la Base de Datos asociándose visualmente a su Descripción real sin generar falsas alertas en Pantalla.
