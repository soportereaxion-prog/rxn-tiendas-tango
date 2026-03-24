# Imágenes Productos — Refactorización Multi-Imagen y Fallback

## Contexto Principal
El alcance inicial asumió erróneamente un ratio 1:1 entre productos y fotografías con hardcoding sobre un fallback global `/assets/img/producto-default.png`. Tras una corrección de alcance prioritaria, se ordenó migrar el ecosistema hacia un soporte de hasta 5 imágenes concurrentes con posibilidad de aislar la portada principal, albergando en paridad un fallback institucional administrado individualmente por cada Tenant (Empresa).

## Auditoría Inicial vs Cambios (Fase 1)
- **Conservado:** La tabla `articulo_imagenes` ya poseía soporte relacional aislado y disponía nativamente de los campos `orden` y `es_principal` que garantizan la jerarquía de coberturas. El ruteo de archivos segmentado a la empresa también se preservó intacto.
- **Corregido:** Se purgó la imposición artificial de 1 archivo por artículo. Se reescribió `ArticuloController` para permitir captar buffers iterativos `max=5`. Se ajustó la consulta escalar `COALESCE` en repositorios. 

## Decisiones Estructurales

### Modelo
- Modificamos en vivo `empresa_config` insertándole la columna `imagen_default_producto` atada al Tenant.

### Reglas de Fallback
Las consultas ahora resuelven desde SQL en base al siguiente árbol de prioridades:
1. (SubQuery A): Extraer la `ruta` de la tabla de imágenes donde pertenezca al ID de la empresa en sesión, ID del artículo actual, y posea `es_principal = 1`.
2. (SubQuery B): Si "A" retorna NULL, extraer la `imagen_default_producto` cargada por la empresa propietaria desde su panel de configuraciones.
3. (Código PHP): Si ambas subqueries resolvieron NULL en cadena, inyectar estáticamente el `/assets/img/producto-default.png`.

## Archivos Afectados
- `[M]` `app/modules/EmpresaConfig/EmpresaConfig.php` (Binding Entidad)
- `[M]` `app/modules/EmpresaConfig/EmpresaConfigRepository.php`
- `[M]` `app/modules/EmpresaConfig/EmpresaConfigService.php`
- `[M]` `app/modules/EmpresaConfig/views/index.php` (Formulario Uploader Config)
- `[M]` `app/modules/Articulos/ArticuloRepository.php` (Lógica CRUD de imágenes)
- `[M]` `app/modules/Articulos/ArticuloController.php` (Proceso de Arrays Mutlipart ≤ 5)
- `[M]` `app/modules/Articulos/views/form.php` (Uploader Multiple + Dashboard de Galería)
- `[M]` `app/modules/Store/Controllers/StoreController.php`
- `[M]` `app/modules/Store/views/index.php`
- `[M]` `app/modules/Store/views/show.php` (Miniaturas ES6 Inline)

## Pruebas Realizadas y Riesgos Mapeados
- **Escalamiento N+1:** Eliminado por completo. Las consultas del Store recuperan el fallback y la imagen principal sin disparar iteraciones costosas usando el propio motor CBO de MariaDB/MySQL (`COALESCE`).
- **Riesgo Resuelto:** Almacenamiento perjudicial de más de 5 imágenes. El Backend detiene recursividades si `$remaining <= 0` abortando procesos intrusivos.

## Próximos Pasos 
- Permitir edición de la columna `orden` si diseño prefiere un orden forzado en la galería o seguir acogiéndose a `es_principal DESC, orden ASC` (id serial).
