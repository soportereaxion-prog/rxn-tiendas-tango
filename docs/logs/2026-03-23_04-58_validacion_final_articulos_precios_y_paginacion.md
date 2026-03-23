# [Artículos] — [Validación Final, Precios y Paginación]

### 🧠 Lectura rápida
Se dictamina el Cierre Técnico del módulo de Artículos tras asegurar equivalencia nativa contra bases SQL (STA11). Adicionalmente, se construyó Paginación SSR para evitar colapsos de memoria en la interfaz web por la ingesta de ~2000 entidades y se blindó el orquestador eliminando las transmutaciones tipo `trim()`.

### 🔍 Diagnóstico
La iteración previa fracasaba silenciosamente al confrontar la realidad: al operar con Volumetrías reales (`2000` rows), la Inserción al DOM de JavaScript del Fitro generaba un cuello de botella espantoso. Además persistía un desfasaje estructural entre nuestro SKU decapitado por `trim()` frente al `COD_STA11` natural con eventuales espacios de la base relacional MS SQL Server.

### 🛠️ Correcciones aplicadas
1. **Remoción Mutativa (Mapper)**: Nos cercioramos de retirar `trim()` para `DESCRIPCIO` y `SINONIMO`, sumado a lo que veníamos corrigiendo del `COD_STA11`. A partir de ahora `ArticuloMapper` inyecta los diccionarios limpios.
2. **Purga Dinámica (Controller)**: Diseñamos el método `$router->post('/mi-empresa/articulos/purgar')` para facultar a Operaciones de resetear íntegramente la caché local de Artículos si notan des-sincronizaciones fatales, obligando posteriormente a Re-Sincronizar Maestro + L1/L2.
3. **Paginación Centralizada**: `ArticuloRepository->findAllPaginated()` ejecuta la labor pesada devolviendo clústeres limitados (`LIMIT 50`).

### 🧪 Validación contra SQL y Connect
- Las llaves contrastadas de Verdad local vs Origen son:
  - Local `codigo_externo` <==> Connect `COD_STA11` <==> MS SQL `STA11.COD_ARTICU`
  - Local `nombre` <==> Connect `DESCRIPCIO` <==> MS SQL `STA11.DESCRIPCIO`
  - Local `descripcion` <==> Connect `SINONIMO` <==> MS SQL `STA11.DESC_ADIC`
  - Local `precio_lista_1/2` <==> Connect `PRECIO` (según `NRO_DE_LIS`) <==> MS SQL `GVA17` (cruzado x `STA11.COD_ARTICU`).
- Ningún campo ha quedado invertido. `===` puro en Macheos.

### 🖥️ Paginación y responsive
- Modificamos la macro `views/index.php`. El viejo Javascript procedural caducó. Ahora el filtrado es Server-Side Rendering invocando un `<form method="GET">` con paramenos paramétricos (`?search=...&page=N`).
- La grilla mutó sus contornos aplicando clases Bootstrap tipo `table-responsive`, `text-nowrap` a columnas financieras y `text-wrap` con topes en píxeles a textos descriptivos interminables favoreciendo lectura Mobile/Tablet.

### ⚠️ Riesgos
- Queda librado al licenciatario hacer el **Purgado Absoluto** antes del próximo Batch si necesita normalidad tras las pruebas espurias. Omitir la Purga perpetuará SKUs decapitados.

### 📘 Documentación
- Este archivo se suma al log transaccional conforme solicitud. El estado global `current.md` actualizó el tópico de Artículos.

### 💾 Git
Push inyectado tras esta bitácora bajo el tag de validación requerido.

### 💬 Cierre
La iteración fortifica y cierra de facto el módulo de Sincronización, dejándolo listo para Producción con compatibilidad multi-empresa.
