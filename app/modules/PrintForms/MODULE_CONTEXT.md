# MODULE_CONTEXT — PrintForms

## Nivel de criticidad
MEDIO-ALTO

Este módulo impacta directamente en:
- Generación de documentos imprimibles para el circuito documental CRM (Presupuestos, PDS).
- Almacenamiento y versionado de plantillas por empresa.
- Assets (imágenes de fondo) vinculados a versiones de formularios.

Cambios en la estructura de canvas, registros o el renderer pueden alterar la salida impresa de múltiples módulos consumidores sin errores evidentes en runtime.

---

## Propósito

Gestionar la definición, edición visual (editor canvas WYSIWYG) y renderización de formularios de impresión por empresa. Cada "tipo de documento" (Presupuesto CRM, PDS CRM, Cuerpo Email Presupuesto) tiene una plantilla editable que el operador puede personalizar con objetos posicionados (textos, variables, imágenes, tablas repetidoras, líneas, rectángulos) sobre una grilla configurable de tamaño A4.

El módulo persiste definiciones versionadas e inyecta datos de contexto al momento de imprimir/renderizar.

---

## Alcance

### Qué hace
- Listado de documentos registrados por área (actualmente solo `crm`).
- Editor visual de canvas con objetos posicionables (text, variable, image, line, rect, table_repeater).
- Versionado incremental automático de cada guardado (con notas opcionales).
- Subida y almacenamiento de imágenes de fondo por versión (PNG/JPG/WEBP, max 8 MB).
- Renderización de documento a HTML posicional para vista previa, impresión y cuerpo de email.
- Registro de definiciones con `document_key` único por empresa.
- Resolución de plantilla activa por `document_key` o por `definition_id`.
- Interpolación de variables de contexto (`{{ variable.path }}`) y repeaters (`items[]`).

### Qué NO hace
- No gestiona la impresión real (window.print / PDF). Eso lo resuelve el navegador o el módulo consumidor.
- No administra permisos granulares sobre quién puede editar cada formulario.
- No expone los formularios fuera del entorno CRM autenticado.
- No permite revertir una versión (solo avanzar creando nuevas); la versión activa siempre es la última guardada.
- No tiene editor para áreas distintas de CRM (Tiendas no tiene formularios de impresión registrados).

---

## Piezas principales

### Controlador
- `PrintFormController.php` — 239 líneas
  - `index()`: listado de documentos con definición y versión activa por empresa.
  - `edit(string $documentKey)`: carga el editor canvas para un tipo de documento.
  - `update(string $documentKey)`: persiste una nueva versión con page_config, objects, fonts y background.
  - `storeBackgroundAsset()`: valida y almacena imagen de fondo en disco + tabla assets.
  - `buildUiContext()`: paths base para CRM.

### Registro
- `PrintFormRegistry.php` — ~1060 líneas
  - Catálogo estático de todos los tipos de documento disponibles (`allDocuments()`).
  - Define `default_page_config`, `default_objects`, `variables`, `repeaters` y `sample_context` por tipo.
  - Tipos registrados: `crm_presupuesto`, `crm_pds`, `crm_presupuesto_email`.
  - `availableFonts()`: fuentes disponibles en el editor.

### Renderer
- `PrintFormRenderer.php` — 371 líneas
  - `buildDocument()`: recibe page_config, objects y context; retorna estructura HTML posicional lista para render.
  - Tipos de objeto soportados: `text`, `text_multiline`, `variable`, `image`, `line`, `rect`, `table_repeater`.
  - Interpolación de `{{ variable.path }}` en contenido de texto.
  - Resolución de variables por dot-notation (`empresa.nombre`, `cliente.documento`, etc.).
  - Detección heurística de imágenes en objetos TEXT (por contenido que termina en `.png`, `.jpg`, etc.).
  - Soporte de fondo de página con opacidad y color configurable.

### Repositorio
- `PrintFormRepository.php` — 338 líneas
  - **Bootstrap on-the-fly**: `ensureSchema()` ejecuta `CREATE TABLE IF NOT EXISTS` para las 3 tablas del módulo en cada instanciación.
  - `saveVersion()`: transaccional — crea definition si no existe, luego inserta versión y actualiza `version_activa_id`.
  - `resolveTemplateForDocument()` / `resolveTemplateByDefinitionId()`: resolución completa de plantilla activa con decode JSON y fallback a defaults.
  - `findVersionsByDefinitionId()`: historial de versiones (últimas 10).
  - `createAsset()` / `findAssetById()`: gestión de assets (fondos) con aislamiento por empresa.

### Vistas
- `views/index.php` — Dashboard de formularios CRM.
- `views/editor.php` — Editor canvas WYSIWYG.
- `views/document_render.php` — Template de render HTML posicional para impresión/preview.
- `views/email_render.php` — Template de render adaptado para cuerpo de email.

---

## Rutas / Pantallas

| Método | URI | Acción |
|--------|-----|--------|
| GET | `/mi-empresa/crm/formularios-impresion` | `index` |
| GET | `/mi-empresa/crm/formularios-impresion/{documentKey}` | `edit` |
| POST | `/mi-empresa/crm/formularios-impresion/{documentKey}` | `update` |

---

## Tablas / Persistencia

| Tabla | Rol |
|-------|-----|
| `print_form_definitions` | Definición por empresa + document_key, con referencia a versión activa |
| `print_form_versions` | Versiones de canvas (page_config, objects, fonts JSON + background_asset_id) |
| `print_form_assets` | Assets binarios referenciados (imágenes de fondo) con ruta en disco |

> Las 3 tablas se crean on-the-fly vía `ensureSchema()` en cada instanciación de `PrintFormRepository`. Comparten el patrón de otros módulos del proyecto (ej: `ArticuloRepository::forCrm()`).

---

## Dependencias directas

| Dependencia | Tipo | Motivo |
|-------------|------|--------|
| `App\Core\Context::getEmpresaId()` | Core | Aislamiento multiempresa |
| `App\Core\View` | Core | Render de vistas |
| `App\Core\Flash` | Core | Mensajes flash |
| `App\Core\Database` | Core | Conexión PDO |
| `App\Modules\Auth\AuthService` | Auth | `requireLogin()` en todos los endpoints |
| `App\Modules\EmpresaConfig\EmpresaConfigRepository` | EmpresaConfig | Datos de empresa real para sample_context (nombre, header/footer URLs) |
| `App\Shared\Services\OperationalAreaService` | Shared | Resolución de paths de dashboard y help |

---

## Dependencias indirectas / Impacto lateral

Estos módulos **consumen** este módulo para generar documentos:

| Módulo | Cómo consume |
|--------|-------------|
| `CrmPresupuestos` | Instancia `PrintFormRepository::resolveTemplateByDefinitionId()` + `PrintFormRenderer::buildDocument()` para generar HTML de presupuesto imprimible y cuerpo de email |
| `CrmPedidosServicio` | Potencialmente consume el renderer para PDS imprimible (template `crm_pds` registrado) |

> Cambios en la estructura de `buildDocument()`, el contrato de `resolveTemplateByDefinitionId()` o las keys del `sample_context` pueden romper silenciosamente la impresión en módulos consumidores.

---

## Seguridad

### Aislamiento multiempresa
Todas las queries filtran por `empresa_id` obtenido de `Context::getEmpresaId()`. No existe lectura cruzada entre empresas.

### Permisos / Guards
Todos los endpoints requieren `AuthService::requireLogin()`. **No hay guard de admin** — cualquier usuario autenticado del tenant puede editar formularios de impresión. No existe diferenciación Admin Sistema vs Admin Tenant para este módulo.

### Mutación por método
- Las mutaciones (guardar versión, subir fondo) se ejecutan por **POST**.
- No existen endpoints GET que muten estado.

### Validación server-side
- El background se valida por MIME type real (`finfo`), extensión permitida (PNG/JPG/WEBP) y tamaño máximo (8 MB).
- Los JSON de canvas (`page_config_json`, `objects_json`, `fonts_json`) se decodifican y validan como array; si fallan, se lanza `RuntimeException`.

### Escape / XSS
- El renderer interpola variables de contexto como strings planos. **No aplica `htmlspecialchars` en la interpolación**. La vista de render (`document_render.php`, `email_render.php`) es responsable del escape final.
- Los strings del editor (contenido de objetos text) se almacenan tal cual. Si un operador inyecta HTML malicioso en un objeto text, este se renderizará sin sanitizar en el documento HTML.

### CSRF
- El formulario del editor no incluye token CSRF explícito. Deuda de seguridad activa.

### Acceso local
- Los assets (fondos) se almacenan en `public/uploads/print-forms/{empresaId}/backgrounds/`. El path es predecible; cualquier persona con acceso al servidor web puede acceder a los archivos por URL directa.

---

## No romper

1. **Contrato de `resolveTemplateByDefinitionId()`**: retorna `[document, definition, active_version, page_config, objects, fonts, background_url]`. CrmPresupuestos depende de esta firma.
2. **Contrato de `PrintFormRenderer::buildDocument()`**: retorna `[page, objects]`. La estructura interna de cada objeto renderizado es consumida por `document_render.php` y `email_render.php`.
3. **Versionado incremental**: nunca se modifica una versión existente. Siempre se crea una nueva. El `version_activa_id` apunta a la última.
4. **Keys de variables en `PrintFormRegistry`**: las rutas de variables (`empresa.nombre`, `presupuesto.numero`, `items[]`, etc.) son consumidas tanto por el editor como por el renderer. Cambiar una key sin actualizar los templates guardados en BD rompe la interpolación.
5. **Bootstrap on-the-fly**: `ensureSchema()` ejecuta DDL en cada instanciación. Eliminarlo sin migración previa rompe módulos que usen PrintForms en ambientes nuevos.

---

## Riesgos conocidos

1. **DDL por request**: `ensureSchema()` ejecuta 3 `CREATE TABLE IF NOT EXISTS` en cada instanciación de `PrintFormRepository`. Mismo patrón que ArticuloRepository para CRM, con el mismo costo de performance.
2. **Sin sanitización XSS en renderer**: los contenidos de objetos text y las variables interpoladas no se escapan. Un operador (o datos de empresa) con HTML malicioso puede inyectar scripts en el documento renderizado.
3. **Sin CSRF en editor**: el POST de `update()` no valida token CSRF. Un ataque CSRF podría sobrescribir la plantilla activa de un documento.
4. **Assets accesibles por URL**: las imágenes de fondo se almacenan en `public/uploads/` sin protección de acceso. Cualquier URL conocida es accesible sin autenticación.
5. **Heurística de imagen frágil**: la detección de si un objeto TEXT es realmente una imagen se basa en extensiones de archivo y substrings (`header_url`, `footer_url`, `http`). Contenido legítimo que matchee esos patrones se renderizará como `<img>` incorrectamente.
6. **Sin límite de versiones**: no hay purga automática de versiones antiguas. Con uso intensivo, la tabla `print_form_versions` puede crecer indefinidamente por empresa.

---

## Checklist post-cambio

- [ ] El listado de formularios de impresión carga en `/mi-empresa/crm/formularios-impresion`.
- [ ] El editor canvas abre correctamente para `crm_presupuesto`.
- [ ] Guardar una versión crea un registro nuevo en `print_form_versions` con version incremental.
- [ ] La vista previa renderiza variables de contexto correctamente (`empresa.nombre`, items repeater).
- [ ] La subida de fondo acepta PNG/JPG/WEBP y rechaza otros formatos.
- [ ] La impresión desde CrmPresupuestos genera el documento con la plantilla activa del tenant.
- [ ] Si se tocó el renderer: verificar que `document_render.php` y `email_render.php` siguen funcionando.

---

## Tipo de cambios permitidos

- Ajustes de UI en vistas del editor o listado.
- Agregar nuevos tipos de documento al `PrintFormRegistry` (nuevos `document_key`).
- Agregar nuevas variables o repeaters a tipos existentes.
- Ampliar fuentes disponibles en `availableFonts()`.

## Tipo de cambios sensibles

- Modificar la estructura de `buildDocument()` o su contrato de retorno.
- Cambiar la lógica de `resolveTemplateByDefinitionId()` o `resolveTemplateForDocument()`.
- Alterar el esquema de las tablas `print_form_*`.
- Cambiar la detección heurística de imágenes en el renderer.
- Modificar la interpolación de variables (`resolveValue`, `interpolateText`).

---

## Regla de mantenimiento

Este archivo debe actualizarse si cambian:
- Tipos de documento registrados en `PrintFormRegistry`.
- Esquema de las tablas `print_form_definitions`, `print_form_versions` o `print_form_assets`.
- Contrato del renderer (`buildDocument` o tipos de objeto soportados).
- Módulos que consumen `PrintFormRepository` o `PrintFormRenderer`.
- Lógica de interpolación de variables.
