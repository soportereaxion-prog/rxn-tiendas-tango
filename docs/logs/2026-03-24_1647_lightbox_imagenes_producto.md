# Lightbox en Detalle de Producto

## Contexto
Como mejora inmediata a la iteración de la Galería Multi-Imagen, se requirió una experiencia de "Lightbox" (Ampliación) en la vista pública de Detalle de Producto. La restricción principal de esta iteración fue la prohibición terminante de recaer en dependencias o librerías externas para evitar sobrecostos de renderizado y mantener un ecosistema lo más vanilla y nativo posible.

## Enfoque Aplicado
Se optó por inyectar un componente DOM al pie de la vista de detalle y unificar su manipulación lógica mediante ES6 estricto.

1. **Compilación Dinámica:** PHP itera e imprime las `N` imágenes posibles (incluso fallbacks y vacuos) hacia una variable JSON JavaScript `galleryImages`, resolviendo el dilema de tener que lidiar con selectores del DOM caprichosos luego en JS.
2. **Eventos y Delegación:** El click principal y los clicks por miniatura levantan un display Fixed global con _backdrop_ semi-transparente y una imagen central centrada y restringida al tamaño máximo del `object-fit: contain;` del Viewport absoluto.
3. **Escuchadores Nativos:** Se incorporó manipulación limpia basada en índices matriciales permitiendo Navegar izquierda (`ArrowLeft`), derecha (`ArrowRight`) y Cerrar (`Esc`) o clickeando por fuera.

## Archivos Tocados
- `[M]` `app/modules/Store/views/show.php` (Markup Base, CSS Encapsulado y Payload Lógica ES6)

## Pruebas Realizadas
- Transición suave opaca al accionar entre imágenes (con un `setTimeout` de 150/200ms para camuflar el repaint de `src`).
- Compatibilidad para Artículos con 1 Sola Imagen (Los cursores se ocultan limpiamente si `gallery.length <= 1`).
- Compatibilidad para Artículos regidos bajo el Fallback Institucional `/assets/img/`.

## Riesgos y Consideraciones
- Al recaer completamente en JSON generado por PHP inline (`json_encode`), cualquier inyección arbitraria queda desarmada automáticamente por la propia limpieza de HTML pre-renderizada; no obstante, debe evitarse el cacheo destructivo en views donde la galería varía bruscamente bajo el mismo SKU (ej. Vaciado y purgado desde el BackOffice sin refrecar FileCache).
  
## Próximos Pasos
- Convertir este componente en un Helper agnóstico global si otras entidades del ERP que salten a la Tienda ameriten Lightboxing (Ej: Comprobantes contables o Guías de transporte).
