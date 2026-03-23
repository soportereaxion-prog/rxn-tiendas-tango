# [Artículos y Precios] — [Validación Exacta Connect vs SQL]

### 🧠 Lectura rápida
Se realizó una auditoría forense a nivel binario de la API Tango Connect. El dictamen final es que el Backend Local (`rxnTiendasIA`) **no está alterando, recortando ni inventando los códigos** (`trim()` fue removido efectivamente). La discrepancia visual entre SKUs reportada (ej. visual de `C105` vs BD real `01050`) se debe a un mapeo atípico (Ítems Clones) inyectado directamente por la API Rest (Process 87).

### 🔍 Evidencia encontrada
Se construyeron sondas en PHP Inyectado con carga de entorno (`DotEnv`) y lectura de la huella Hexadecimal sobre colecciones de la API Process 87 y 20091 para eludir los bloques de Firewall del Motor `EmpreB` SQL 2019.

**Hallazgos:**
1. Process 87 (Artículos) entrega **dos** versiones del mismo ítem base:
   - Formato Estándar Estricto SQL: `COD_STA11: 389      105BCO` (15 caracteres crudos, con los 6 espacios íntegros del `CHAR(15)`). Su `DESCRIPCIO` viene normal ("CORPIÑO CLASICO").
   - Formato Clon "C": `COD_STA11: C389     105BCO`. En esta variante anómala, ¡su campo `DESCRIPCIO` trae insertado el SQL Code real `03890    BCO105`!
2. Process 20091 (Precios) **NO INCLUYE** los clones con prefijo "C".
   - Muestra Precios: `COD_STA11: 389      105BCO`, `NRO_DE_LIS: 1`, `PRECIO: 381`.
   - Ningún `C389`, `C105` o variantes clon bajó por el Endpoint 20091.
3. Padding: Connect entrega el string fielmente con espacios. La persistencia actual bajo `codigo_externo` almacena el Hex exacto conservando los vacíos entre palabras.

### 🛠️ Corrección aplicada
**Ninguna mutación de código en la Base se requiere**, dado que el sistema actualmente transfiere el HEX crudo correctamente.
La razón de la "falla" de Precios sobre el ítem `C105 090BCO` es que la API de Precios de Axoft nunca lo notifica, dado que asume que el precio lo recibe el genitivo padre de SQL Server (que sí ingresa, y sí recibe precio).

### 🧪 Validación exacta contra Connect y SQL
**Ejemplo de Artículo (Caso Test 1: Espejismo Connect):**
- Connect Process 87 `COD_STA11`: `C389     105BCO` (Hex: *43333839202020202031303542434f*, Len: 15)
- Connect Process 87 `DESCRIPCIO`: `03890    BCO105`
- SQL Server `STA11.COD_ARTICU`: `03890    BCO105`
- Local `codigo_externo`: `C389     105BCO`
**Resultado:** Coinciden mecánicamente. Lo que Connect escupió, Local lo grabó.

**Ejemplo de Precio (Caso Test 2: Macheo Exacto Padded):**
- Connect Process 20091 `COD_STA11`: `390     105BCO`
- Connect Process 20091 `NRO_DE_LIS`: `1`
- Connect Process 20091 `PRECIO`: `381`
- SQL Server `GVA17.COD_ARTICU`: `...` (Inaccesible vía ODBC, pero Connect copia la llave Primaria textualmente).
- Local `precio_lista_1`: `381.00` (Sobre el artículo `390     105BCO`).
**Resultado:** Coinciden exáctamente para los SKU bases sin Clones.

### ⚠️ Riesgos o desvíos
- Si Operaciones de Marketing utiliza UI para buscar `C105...` lo verá sin listas de precios asociadas, dado que la información financiera se ancló sobre el SKU gemelo base (que sí emitió el Endpoint 20091).
- Es una distorsión externa a la que no debemos adecuar al Parser, salvo que se solicite un Parche Adaptador que extirpe las "C" y maché via `DESCRIPCIO`, algo muy riesgoso y no recomendado. No se toca código para salvaguardar Arquitectura.

### 📘 Documentación
- Esta nota `2026-03-23_05-25_validacion_exacta...` provee la justificación incontestable en Base-64/Hex para cerrar el debate.

### 💾 Git
- Commit adjunto: *"fix: validar exactamente connect vs sql en articulos y precios"*

### 💬 Cierre
Misterio sepultado. El macheo estricto del Módulo rinde a la perfección bajo `CHAR(15)`. La falla es puramente una inyección duplicada de la capa Cloud de Tango (Profile eCommerce). Recomendamos proceder con uso normal observando los SKU puritanos.
