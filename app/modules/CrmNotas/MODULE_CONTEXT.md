# MODULE_CONTEXT — CrmNotas

## Nivel de criticidad
MEDIO. Provee el registro de observaciones, anotaciones y bitácoras asociadas (o no) a clientes comerciales del CRM. Es clave para el seguimiento de la relación con el cliente (CRM Tracking).

## Propósito
Gestionar un sistema de notas internas, permitiendo a los operadores asentar actividades, sugerencias y registros, asociándolos de forma cruzada con la base de clientes y clasificándolos mediante etiquetas (Tags).

## Alcance
**QUÉ HACE:**
- ABM completo de Notas (Listado, Creación, Edición, Soft-Delete, Restauración).
- Búsqueda avanzada, ordenamiento y filtros (por estado, texto, etc.).
- Permite copiado rápido de una nota existente (acción `copy`).
- Brinda autocompletado rápido de Etiquetas (`tagsSuggestions`), Clientes (`clientSuggestions`) y Tratativas (`tratativaSuggestions`).
- Vinculación opcional de cada nota con una Tratativa del CRM (mismo patrón de FK blanda que PDS y Presupuestos).
- Creación contextualizada desde el detalle de una tratativa vía query param `?tratativa_id=X&cliente_id=Y`: el cliente se hereda automáticamente y al guardar la nota se vuelve al detalle de la tratativa.
- Importación masiva de notas desde archivos `.xlsx` usando la librería OpenSpout, detectando clientes vía "Código Tango" y asociándolas al vuelo.
- Exportación del listado de notas a `.xlsx`.

**QUÉ NO HACE:**
- No sincroniza las notas a Tango Connect (son de consumo puramente local y operativo).
- No impone obligatoriedad de asignación a un cliente (una nota puede ser "huérfana").

## Piezas principales
- **Controladores:** `CrmNotasController`.
- **Repositorios:** `CrmNotaRepository` (gestiona persistencia y búsquedas con joins al cliente).
- **Modelos:** `CrmNota`.
- **Vistas:** `views/index.php`, `views/form.php`, `views/show.php`, `views/import.php`.
- **Rutas/Pantallas:** `/mi-empresa/crm/notas`.
- **Tablas/Persistencia:** `crm_notas`, `crm_notas_tags_diccionario`.
- **Migraciones relevantes:**
    - `database/migrations/2026_04_15_add_tratativa_id_to_crm_notas.php` — agrega `tratativa_id INT NULL` a `crm_notas` con índice `(empresa_id, tratativa_id)`. FK blanda (sin constraint duro).

## Seguridad Base (Política de Implementación)
- **Aislamiento Multiempresa**: OBLIGATORIO. El controlador exige `Context::getEmpresaId()` en todas sus acciones (`getEmpresaIdOrDie()`). Las búsquedas de Tags y Clientes en los endpoints AJAX también filtran por empresa.
- **Permisos / Guards**: Protegido por `AuthService::requireLogin()`. 
- **Mutación**: Todo cambio de estado (`store`, `update`, `eliminar`, `restore`, `copy`, `processImport`) está protegido bajo restricciones estrictas de método (POST) y verifica pertenencia (`findByIdAndEmpresa`).
- **Validación Server-Side**: Los campos obligatorios (`titulo`, `contenido`) son validados. Excepciones capturadas para renderizar errores sin romper el flujo (`try/catch` con rehidratación de `old` inputs).
- **Escape Seguro (XSS)**: Las notas al ser campos de texto libre ingresados por operadores o importados vía Excel DEBEN ser renderizadas aplicando escaping (ej. `htmlspecialchars`) en listados.
- **Acceso Local**: Solo visible y modificable bajo el token de empresa activa.

## Dependencias directas
- Librería `OpenSpout\Reader\XLSX\Reader` y `OpenSpout\Writer\XLSX\Writer` para importación y exportación de Excel.
- `App\Modules\CrmClientes\CrmClienteRepository` para la vinculación en el modal y la asociación por "Código Tango" en la importación masiva.
- `App\Modules\CrmTratativas\TratativaRepository` — se usa en `create/store/update` para validar que `tratativa_id` pertenezca a la empresa (`existsActiveForEmpresa`) y en `index` para precargar el filtro por tratativa. También alimenta el endpoint `tratativaSuggestions` via `findSuggestions()`.

## Dependencias indirectas / impacto lateral
- Un cambio radical en cómo se persiste `CrmClientes` puede impactar en las consultas Join del listado de notas (`findAllWithClientName`).

## Reglas operativas del módulo
- **Vínculo con Tratativa**: `tratativa_id` es opcional (FK blanda). Si viene por query param `?tratativa_id=X`, el controller valida que exista y pertenezca a la empresa; si no pasa la validación se ignora silenciosamente (la nota se crea sin vínculo). Al guardar una nota que tiene `tratativa_id`, el redirect vuelve a `/mi-empresa/crm/tratativas/{id}` en lugar del listado por defecto.
- **Herencia de cliente desde Tratativa**: cuando se crea una nota desde el detalle de una tratativa (query param), el `cliente_id` se hereda automáticamente del cliente de la tratativa. El usuario puede sobrescribirlo desde el form. Si se pasa también `?cliente_id=Y` por URL, ese valor tiene prioridad sobre el heredado.
- Durante la importación de Excel, si el "Código Tango" no matchea ningún cliente en la base local de la empresa, la nota se crea igualmente pero de forma huérfana (sin `cliente_id`).
- La exportación vuelca toda la visualización de la grilla (incluyendo resolución del nombre del cliente) aplicando los filtros vigentes del Datatable.
- **Persistencia de filtros de listado**: el input de búsqueda F3 (`search`), el campo de búsqueda (`field`), la cantidad por página (`limit`), el filtro de estado de negocio (`estado`), el filtro de categoría (`categoria_id`, donde aplique) y los filtros Motor BD (`f[campo][op|val]`) se persisten automáticamente en `localStorage` scopeados por `pathname + empresa_id` via `public/js/rxn-filter-persistence.js` (cargado inline desde `admin_layout.php`). Al volver al listado, los filtros se restauran y se reinicia en la primera página. `status` (activos/papelera), `sort`, `dir` y `area` quedan fuera por ser navegación u orden. Para limpiarlos: `?reset_filters=1` (lo dispara `rxn-advanced-filters.js` al borrar BD) o `window.rxnFilterPersistence.clear()`. Los filtros "locales" (selección por columna) siguen viviendo en `sessionStorage` via `rxn-advanced-filters.js` con key `rxn_lf::`.

## Tipo de cambios permitidos
- Agregar adjuntos o imágenes a las notas.
- Mejorar el analizador de etiquetas para normalizar tags de forma dinámica en lugar de strings separados por comas.
- Crear nuevos formatos de importación (ej. CSV nativo extendido).

## Tipo de cambios sensibles
- Modificar las cabeceras requeridas en `processImport` puede romper la funcionalidad operativa de carga masiva de los clientes.
- Eliminar los métodos AJAX JSON (`tagsSuggestions`, `clientSuggestions`) rompería el front-end del formulario dinámico.

## Riesgos conocidos
- **Inyección de memoria en importación**: Aunque usa OpenSpout (que es stream-based), importar Excels colosales podría llegar a saturar el worker.
- **Integridad Referencial**: El guardado de las tags se hace en string texto plano (`tags`), no tiene tabla pivot normalizada. Facilita la carga pero dificulta búsquedas estructurales complejas.

## Checklist post-cambio
- [ ] Formularios de Creación/Edición guardan y asocian el cliente correctamente.
- [ ] Soft delete masivo y singular responden únicamente a los IDs de la empresa en sesión.
- [ ] Descarga de la matriz de ejemplo y exportación no arrojan error fatal de dependencias de clases.
- [ ] Endpoints JSON de sugerencias devuelven array válido de autocompletado en ms.
