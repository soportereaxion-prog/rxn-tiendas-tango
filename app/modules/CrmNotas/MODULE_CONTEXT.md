# MODULE_CONTEXT — CrmNotas

## Nivel de criticidad
MEDIO. Provee el registro de observaciones, anotaciones y bitácoras asociadas (o no) a clientes comerciales del CRM. Es clave para el seguimiento de la relación con el cliente (CRM Tracking).

## Propósito
Gestionar un sistema de notas internas, permitiendo a los operadores asentar actividades, sugerencias y registros, asociándolos de forma cruzada con la base de clientes y clasificándolos mediante etiquetas (Tags).

## Alcance
**QUÉ HACE:**
- ABM completo de Notas (Listado, Creación, Edición, Soft-Delete, Restauración).
- **Listado en layout split master-detail** (estilo GroupOffice/Explorer): columna izquierda con la lista de notas + columna derecha con el detalle en vivo. Permite recorrer notas con j/k sin salir del listado.
- Búsqueda en vivo (debounce 250ms) que recarga sólo la columna izquierda sin tocar el panel derecho.
- Paginación AJAX sobre la columna izquierda (25 notas por página).
- Filtros avanzados, ordenamiento y filtros por estado (activos/papelera).
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
- **Controladores:** `CrmNotasController` (incluye los endpoints AJAX `panel($id)` y `listPartial()` del split view).
- **Repositorios:** `CrmNotaRepository` (gestiona persistencia y búsquedas con joins al cliente).
- **Modelos:** `CrmNota`.
- **Vistas:** `views/index.php` (split master-detail), `views/form.php`, `views/show.php` (legacy full page, se mantiene por compat), `views/import.php`, `views/partials/detail_panel.php` (HTML del panel derecho), `views/partials/list_items.php` (HTML de la columna izquierda).
- **JS de página:** `public/js/crm-notas-split.js` (controlador del split: fetch panel, navegación j/k, búsqueda en vivo, paginación AJAX).
- **Rutas/Pantallas:** `/mi-empresa/crm/notas`. Endpoints AJAX internos: `GET /mi-empresa/crm/notas/lista` (partial lista) y `GET /mi-empresa/crm/notas/panel/{id}` (partial detalle). Registradas en `app/config/routes.php` **antes** de `/{id}` para evitar que el catch-all se las coma.
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
- **Layout split (master-detail)**: el listado de notas usa un layout de dos columnas. La columna izquierda (col-lg-4) lista hasta 25 notas con checkbox para bulk, y la derecha (col-lg-8) muestra el detalle de la nota seleccionada. El detalle se carga vía `GET /panel/{id}` (HTML parcial, sin `admin_layout`) y se inyecta con `innerHTML` en el contenedor `[data-notas-panel]`. La URL se sincroniza con `history.replaceState` agregando `?n={id}` para que F5 / link compartido preserve la nota activa. El controller `index()` resuelve la nota activa inicial en este orden: (1) `?n={id}` si es válido para la empresa, (2) primer item del listado, (3) null → placeholder.
- **Edición en full page (decisión MVP)**: el botón "Editar" del panel derecho navega a `/notas/{id}/editar` (reusando el form existente con `admin_layout`). NO se edita inline en el panel para evitar duplicar autocompletes (cliente, tratativa, tags). Se prioriza minimizar deuda sobre la fluidez de edición — se pierde el split al editar, pero los beneficios de revisión siguen intactos. Si en el futuro se quiere edición inline, hacer una fase 2 extrayendo el form a un partial.
- **Redirects post-mutación**:
    - `store()` y `update()` redirigen a `/notas/{id}/editar` (quedarse en el form, patrón unificado con PDS/Presupuestos). El Volver del form es el que saca al usuario — no el Guardar.
    - `copy()` sigue con `indexPath?n={id}` para volver al split parado en la copia.
- **Volver contextual + Escape = Volver** (patrón transversal PDS/Presupuestos/Notas/Tratativas):
    - El botón Volver se calcula con `$notaBackHref` / `$notaBackTitle` antes del `ob_start()`: si la nota tiene (o hereda por `?tratativa_id=`) una tratativa vinculada → detalle de la tratativa; si no → listado de notas.
    - El `<a>` del Volver lleva el atributo `data-rxn-back`. El script global `public/js/rxn-escape-back.js` escucha Escape en todo el admin_layout: si hay un `[data-rxn-back]` en la vista y el foco no está en un input/textarea ni hay modal abierto, navega a ese href.
    - Esto reemplaza el `history.back()` nativo por una navegación explícita hacia el destino que la vista declara — evita volver a un listado filtrado viejo o a un paso intermedio que el usuario ya olvidó.
- **Persistencia del último seleccionado (localStorage)**: `public/js/crm-notas-split.js` guarda cada `activeNotaId` en `localStorage` bajo la key `rxn_crm_notas_active::{empresaId}::{status}` (scope por empresa Y por tab activos/papelera). Al cargar el listado sin `?n=` explícito, el JS compara el valor del storage con la nota que eligió el server (primera del listado); si difieren, intenta cargar la del storage via `GET /panel/{id}`. Si el endpoint devuelve 404 (nota borrada o fuera de scope), limpia el storage y vuelve a cargar la que había elegido el server. Eso mantiene el comportamiento del resto del sistema donde los listados "recuerdan dónde estabas parado".
- **Búsqueda en vivo**: el input de búsqueda de la columna izquierda dispara un fetch debounced (250ms) a `GET /notas/lista` que devuelve sólo el HTML de los items. No toca el panel derecho salvo que la nota activa haya quedado fuera de los resultados, en cuyo caso el JS carga el primer item del nuevo listado (o el placeholder de "sin resultados"). Los filtros avanzados BD siguen funcionando por el ciclo tradicional (recarga completa) — sólo la búsqueda de texto y la paginación son AJAX.
- **Hotkeys (registradas en `RxnShortcuts` vía `public/js/crm-notas-split.js`)**:
    - `↓` / `j` → siguiente nota en la lista.
    - `↑` / `k` → nota anterior.
    - `Enter` (con foco fuera de inputs) → editar la nota activa.
    - Todas con `scope: 'no-input'` y `when: () => document.querySelector('.notas-split')` para no pelear con otros módulos.
    - Las flechas son el canal canónico (coherencia con el resto del sistema); `j`/`k` quedan como alias estilo vim para usuarios que los prefieren.
- **Enter y ArrowDown desde el input de búsqueda**:
    - `Enter` en el search dispara la búsqueda inmediata (flushea el debounce pendiente), activa la primera nota del resultado y mueve el foco del input al row del primer item. Desde ahí las flechas ya navegan la lista vía `RxnShortcuts`.
    - `ArrowDown` en el search (sin Enter) también baja al primer item — patrón combobox/omnibox. No dispara refresh; sólo mueve el foco.
    - Ambos casos hacen `searchInput.blur()` antes de `row.focus()` para que las hotkeys de `scope: 'no-input'` tomen control.
- **Re-inyección de HTML**: el `detail_panel.php` incluye forms con `rxn-confirm-form` (copiar, eliminar/papelera/restore). Estos funcionan sin re-bindeo porque `rxn-confirm-modal.js` usa delegación de eventos en `document`. Idem los botones de paginación y los checkboxes bulk (delegación via listener en `listContainer`).
- **Vínculo con Tratativa**: `tratativa_id` es opcional (FK blanda). Si viene por query param `?tratativa_id=X`, el controller valida que exista y pertenezca a la empresa; si no pasa la validación se ignora silenciosamente (la nota se crea sin vínculo). El redirect al guardar NO depende del vínculo (siempre se queda en la nota); el retorno a la tratativa se hace vía el botón Volver (ver regla anterior).
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
- **Orden de rutas en router**: `/notas/lista` y `/notas/panel/{id}` deben declararse **antes** de `/notas/{id}` en `app/config/routes.php`. Si se mueven, el catch-all `/{id}` se las come y los partials rompen silenciosamente (404 en fetch). El split view deja de funcionar sin error visible en UI, sólo panel vacío.
- **Dependencia `openspout/openspout` debe estar declarada en `composer.json`**: la matriz de ejemplo (`exportarMatrizEjemplo`) y la importación (`processImport`) usan OpenSpout. Si la lib aparece en `vendor/` pero no en `composer.json`, el primer `composer require`/`update` posterior la barre silenciosamente y el módulo rompe con `"La descarga del Excel requiere que OpenSpout esté instalado."` / `"La exportación requiere que OpenSpout esté instalado."`. Antes de cualquier toque a `composer.json`, validar que siga declarada (`composer show openspout/openspout`). Misma regla que `dompdf` (incidente 1.27.0) y RxnLive (hotfix 1.46.2 — ver `app/modules/RxnLive/MODULE_CONTEXT.md` para el detalle del incidente).

## Checklist post-cambio
- [ ] Formularios de Creación/Edición guardan y asocian el cliente correctamente.
- [ ] Soft delete masivo y singular responden únicamente a los IDs de la empresa en sesión.
- [ ] Descarga de la matriz de ejemplo y exportación no arrojan error fatal de dependencias de clases.
- [ ] Endpoints JSON de sugerencias devuelven array válido de autocompletado en ms.
- [ ] El split view carga el detalle al clickear un item de la lista sin recargar la página.
- [ ] Las hotkeys j/k navegan la lista y Enter abre el form de edición.
- [ ] La búsqueda en vivo filtra la lista sin romper la paginación.
- [ ] Los endpoints `/panel/{id}` y `/lista` aparecen en `routes.php` ANTES de `/notas/{id}` (sino el catch-all se los come).
- [ ] Al crear una nota, el redirect vuelve al split parado en la nota recién creada (salvo si tiene `tratativa_id`).
- [ ] Al editar una nota, el redirect vuelve al split parado en la nota editada.
- [ ] Salir del listado y volver sin `?n=` restaura la última nota vista (localStorage per empresa+tab).
- [ ] Si la última nota guardada fue borrada, el cliente detecta el 404 del panel, limpia el storage y cae a la primera del listado sin loopear.
