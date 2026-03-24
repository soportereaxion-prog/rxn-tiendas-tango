# Implementación Base: Manejo Múltiple de Imágenes de Productos

## Contexto
Se solicitó establecer la arquitectura base local para soportar un futuro sistema de carrusel de imágenes para los artículos, comenzando por el modelo transaccional y la capacidad de subir de a una imagen como portada estática, sentando las bases escalables.

## Decisiones e Implementación
1.  **Modelo DDL (`articulo_imagenes`):** Generamos la tabla vinculante que interconecta un `empresa_id`, un `articulo_id` y su `ruta` almacenada.
2.  **File System Rules:** Se automatizó en el controlador la construcción del árbol taxonómico `public/uploads/empresas/{empresaId}/productos/{articuloId}/`.
3.  **Naming Convention:** Todas las subidas pasan a la sintaxis `emp_X_art_Y_timestamp.JPG` garantizando cero colisiones.
4.  **Hidratación Activa (Consultas N+1 eludidas):** Añadimos un Subquery inline selectivo en `ArticuloRepository::findAllPaginated` y `findById` para chupar únicamente la foto `es_principal = 1` y conmenor `orden`.
5.  **Caché y Flush:** Integrada la demolición de archivos de Caché (`FileCache::clearPrefix`) cuando el administrador sube una foto.

## Archivos modificados y Estructuras Creadas
*   [N] `C:\Users\...\public\assets\img\producto-default.png`
*   [M] `app/modules/Articulos/Articulo.php`
*   [M] `app/modules/Articulos/ArticuloController.php`
*   [M] `app/modules/Articulos/ArticuloRepository.php`
*   [M] `app/modules/Articulos/views/form.php`
*   [M] `app/modules/Store/views/index.php`
*   [M] `app/modules/Store/views/show.php`

## Pruebas Físicas Constatadas
- Subida de archivo con MIME types validados (.png/jpg).
- Visualización renderizada mediante condicional estricto en el Front Store Catálogo.

## Riesgos y Fallbacks
- El fallback de carencia de foto apunta ciegamente a `/assets/img/producto-default.png`, el cual se pre-configuró con el path vacío para prevenir 404 en navegadores hasta que diseño envíe el dummy placeholder.

## Próximos Pasos (Arquitectura futura)
1. Integrar arrastrar y soltar múltiple (Dropzone) en `form.php`.
2. Habilitar UI para modificar el `orden` de las vistas o la selección de la "Portada Principal" (badge green).
