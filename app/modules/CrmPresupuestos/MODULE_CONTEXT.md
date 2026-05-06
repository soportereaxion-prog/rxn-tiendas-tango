# MODULE_CONTEXT — CrmPresupuestos

## Nivel de criticidad
MUY ALTO. Es un módulo transaccional Core del CRM enfocado en la cotización comercial. Emite propuestas, asocia catálogos comerciales con clientes y envía documentos formalizados a Tango Connect (Facturación/Ventas).

## Propósito
Sustentar el motor de cotizaciones del CRM, permitiendo armar presupuestos, seleccionar artículos desde un catálogo comercial sincronizado, aplicar bonificaciones, generar pre-impresiones PDF y enviarlos al ERP Tango para convertirlos en facturas o pedidos de venta en firme.

## Alcance
**QUÉ HACE:**
- Gestiona el ABM de Presupuestos (con Maestro-Detalle para los renglones/artículos).
- **Consume** el catálogo comercial (condiciones de venta, listas de precio, vendedores, transportes, depósitos) desde `CommercialCatalogRepository`. La sincronización es responsabilidad de **RxnSync** (ver `App\Modules\RxnSync\Services\CommercialCatalogSyncService`, release 1.12.5).
- Envía presupuestos formalizados al ERP Tango (`PresupuestoTangoService`).
- Renderiza contexto de impresión con plantillas (`CrmPresupuestoPrintContextBuilder`).
- Búsqueda visual en grilla, paginación, filtros de estado.
- Auto-trigger defensivo del Sync Catálogos: si al abrir un form se detecta catálogo vacío, `loadCatalogData()` dispara el sync inline (usando el service de RxnSync) para no bloquear al operador.

**QUÉ NO HACE:**
- No permite cobrar ni aplicar medios de pago. Es exclusivamente preventa / cotización.
- No administra clientes directamente; consume el catálogo proveído por `CrmClientes`.

## Piezas principales
- **Controladores:** `PresupuestoController`.
- **Servicios:** `PresupuestoTangoService`, `CrmPresupuestoPrintContextBuilder`. (El `CommercialCatalogSyncService` vive ahora en `App\Modules\RxnSync\Services` — release 1.12.5.)
- **Repositorios:** `PresupuestoRepository`, `CommercialCatalogRepository` (queda en este módulo porque el form lo consume directo — findAllByType, findOption, findFirstByType).
- **Vistas:** `views/index.php`, `views/form.php`.
- **Rutas/Pantallas:** `/mi-empresa/crm/presupuestos`. El botón "Sync Catálogos" del listado se removió; ahora redirige a la consola RxnSync con un link "Ir a RxnSync".
- **Tablas/Persistencia:** `crm_presupuestos`, `crm_presupuestos_renglones`, `crm_catalogo_comercial_items` (tabla poblada por RxnSync con condiciones/listas/vendedores/transportes/depósitos).

## Seguridad Base (Política de Implementación)
- **Aislamiento Multiempresa**: OBLIGATORIO Y ESTRICTO. La consulta de cabecera de presupuesto, renglones y catálogos usan `empresa_id` inyectado forzosamente desde `Context::getEmpresaId()`.
- **Permisos / Guards**: Validados por `AuthService::requireLogin()`. Los usuarios sólo pueden operar sobre cotizaciones de su organización.
- **Mutación**: Todo evento de creación, actualización y borrado suave ocurre bajo verbos transaccionales apropiados (POST).
- **Validación Server-Side**: Los datos del form que representan el maestro-detalle (cabecera + N renglones de artículos con cantidades, precios y bonificaciones) deben ser validados estructuradamente antes de guardar o enviar a Tango para no emitir comprobantes inválidos.
- **Escape Seguro (XSS)**: Textos de observaciones, comentarios para el cliente y descripciones custom en renglones deben ir escapados al renderizar la vista y el builder del PDF.
- **Acceso Local**: Sujeto al token de la empresa en la sesión.
- **Estándar de seguridad transversal**: Cualquier endpoint nuevo de este módulo debe respetar `docs/seguridad/convenciones.md` — en particular CSRF en POST forms y AJAX, sanitización de `getMessage` en catches, y las reglas de auditoría de eliminación permanente listadas abajo.

## Auditoría de eliminación permanente (1.46.4)

Desde la 1.46.4 todo `forceDelete` (hard-delete) sobre `crm_presupuestos` queda registrado automáticamente en `crm_presupuestos_audit_deletes` vía trigger SQL `BEFORE DELETE`. Mismo patrón que CrmPedidosServicio (1.46.3). Red de seguridad triple:

1. **Trigger SQL** (`tr_crm_presupuestos_audit_before_delete`): captura cualquier `DELETE FROM crm_presupuestos`, incluyendo deletes hechos desde phpMyAdmin/HeidiSQL/SQL manual.
2. **`PresupuestoRepository::forceDeleteByIds`**: setea `@audit_user_id` y `@audit_user_name` (MySQL session vars) antes del `DELETE` para que el trigger las lea como atribución. Si vienen NULL (delete sin contexto de sesión), el audit registra NULL → "delete no atribuible" sin perder el snapshot.
3. **Snapshot completo en `before_json` (LONGTEXT)**: el trigger emite `JSON_OBJECT(...)` con 34 campos del row borrado. Cualquier campo del presupuesto queda capturado.

**Vista expuesta**: `RXN_LIVE_VW_PRESUPUESTOS_DELETES` registrada en `RxnLiveService::$datasets` como dataset `presupuestos_eliminados`. Agrega flag calculado `estaba_en_tango = "Sí — quedó huérfano en Tango"` cuando `nro_comprobante_tango` no está vacío — análogo al flag de PDS para `tango_nro_pedido`.

**Si se modifica `forceDeleteByIds`**: mantener el bloque que setea `@audit_user_id` y `@audit_user_name` antes del `$stmt->execute()`. Sin eso, los registros de audit van a quedar sin atribución.

**Si se modifica el schema de `crm_presupuestos`** (agregar columnas): el `before_json` del trigger las captura solo si se actualiza la lista de `OLD.<col>` en `JSON_OBJECT(...)` dentro del trigger. Ver `database/migrations/2026_05_06_01_create_crm_presupuestos_audit_deletes.php` como referencia. Los campos columnados explícitos (numero, nro_comprobante_tango, total, etc) NO se actualizan automáticamente — agregar la columna al `INSERT INTO ... VALUES (OLD....)` también si querés que aparezca en RxnLive como columna propia.

**Mitigación pendiente**: confirm UX en el botón de hard-delete cuando `nro_comprobante_tango != NULL` (mismo pendiente que PDS).

## Dependencias directas
- `App\Modules\CrmClientes\CrmClienteRepository` para cargar el receptor de la cotización.
- Lógica transaccional de Tango vía `App\Modules\Tango\TangoService`.
- **RxnGeoTracking**: Al completar exitosamente la creación de un presupuesto (no en ediciones), `PresupuestoController::store()` invoca `GeoTrackingService::registrar('presupuesto.created', $presupuestoId, 'presupuesto')` para asentar el evento de auditoría geolocalizada. La llamada es **fire-and-forget**: si el servicio falla, el presupuesto ya está guardado y se retorna normalmente al usuario. El `evento_id` retornado queda en `$_SESSION['rxn_geo_pending_event_id']` para que el próximo render del `admin_layout` lo inyecte como meta tag y el JS del browser reporte posición precisa. Ver `app/modules/RxnGeoTracking/MODULE_CONTEXT.md`.

## Dependencias indirectas / impacto lateral
- El funcionamiento del módulo depende enormemente de que la sincronización del `CommercialCatalogSyncService` (hoy en `App\Modules\RxnSync\Services`) esté sana. Si Tango modifica las clases o alias de sus artículos, y no bajan al CRM, no se podrán armar presupuestos válidos.
- El operador dispara la sincronización desde `RxnSync` (botón "Sync Catálogos"). Si nunca se corrió, los selectores del form van a estar vacíos y se activará el auto-trigger defensivo en `loadCatalogData()`.

## Reglas operativas del módulo
- La estructura de guardado suele ser atómica. Guardar la cabecera e iterar y salvar los renglones (artículos).
- Un presupuesto enviado a Tango con éxito usualmente bloquea sus ediciones locales de cantidades/precios para no divergir de lo declarado en AFIP/Ventas (lógica de estado).
- **Geo-tracking en creación**: El evento `presupuesto.created` se registra **únicamente en el INSERT exitoso** (primera vez que el presupuesto existe en DB), no en ediciones, no en envíos a Tango, no en re-impresiones. Esto es deliberado: el objetivo es auditar dónde estaba el vendedor cuando originó la cotización, no cada interacción posterior. El tracking ocurre **después** del commit exitoso en `crm_presupuestos` y los renglones, de modo que una falla en el tracking nunca deja un presupuesto a medio guardar.
- **Persistencia de filtros de listado**: el input de búsqueda F3 (`search`), el campo de búsqueda (`field`), la cantidad por página (`limit`), el filtro de estado de negocio (`estado`), el filtro de categoría (`categoria_id`, donde aplique) y los filtros Motor BD (`f[campo][op|val]`) se persisten automáticamente en `localStorage` scopeados por `pathname + empresa_id` via `public/js/rxn-filter-persistence.js` (cargado inline desde `admin_layout.php`). Al volver al listado, los filtros se restauran y se reinicia en la primera página. `status` (activos/papelera), `sort`, `dir` y `area` quedan fuera por ser navegación u orden. Para limpiarlos: `?reset_filters=1` (lo dispara `rxn-advanced-filters.js` al borrar BD) o `window.rxnFilterPersistence.clear()`. Los filtros "locales" (selección por columna) siguen viviendo en `sessionStorage` via `rxn-advanced-filters.js` con key `rxn_lf::`.
- **Guardar se queda en el Presupuesto; Volver es contextual a la Tratativa**: `PresupuestoController::resolveReturnPath` siempre retorna a `/editar` — guardar NO saca al usuario del form (coherente con PDS v1.19.0). El Volver del header declara `$presupuestoBackHref` / `$presupuestoBackTitle` antes del `ob_start()`: si el presupuesto tiene `tratativa_id` → detalle de la tratativa; si no → listado. El `<a>` lleva `data-rxn-back` para que Escape también navegue al mismo destino (ver `public/js/rxn-escape-back.js`).

## Cabecera comercial — campos extendidos (release 1.29.0)

A partir de la release 1.29.0 la cabecera del presupuesto suma 8 columnas que viajan a Tango y/o al PDF del PrintForm:

- **`cotizacion`** (DECIMAL(15,4), NOT NULL DEFAULT 1) — Cotización del dólar al momento del presupuesto. Viaja al payload de Tango como `COTIZACION` (nombre exacto confirmado vía GET de pedido a Tango Connect, ver `TangoOrderMapper`). En la UI vive en col-1, entre Estado y Depósito. Validación server-side: `>= 0`. Si el operador deja vacío, se persiste como `1`.
- **`proximo_contacto`** (DATETIME NULL) — Fecha+hora+segundos de próximo contacto del vendedor con el cliente. Manual; en una iteración futura habrá un parámetro de empresa para autosettear. **NO se proyecta automáticamente en CrmAgenda** (decisión explícita 2026-04-29; revisar si Charly lo pide). Sólo se persiste y se expone al PrintForm.
- **`vigencia`** (DATETIME NULL) — Fecha+hora+segundos hasta la cual el presupuesto es válido. Manual; en una iteración futura habrá un parámetro de empresa para autosettear (ej: "fecha + 30 días").
- **`leyenda_1` ... `leyenda_5`** (VARCHAR(60) NULL c/u) — 5 leyendas comerciales libres. Viajan a Tango como `LEYENDA_1`, `LEYENDA_2`, ..., `LEYENDA_5` (nombres exactos confirmados vía GET de pedido a Tango Connect). Server-side se truncan defensivamente a 60 caracteres aunque el `<input>` declare `maxlength=60` y `size=40` (ancho visual ~40 caracteres).

**Reglas operativas de los campos nuevos:**

- **Formato de fechas**: `proximo_contacto`, `vigencia` y la `fecha` del presupuesto van **siempre con segundos** (`Y-m-d H:i:s` en DB, `Y-m-d\TH:i:s` en el `<input type="datetime-local">`). El wrapper `rxn-datetime.js` se encarga de mostrarlas en formato es-AR `d/m/Y H:i:s` con Flatpickr. **No usar `Y-m-d\TH:i` sin segundos** — eso fue truncado al subir a release 1.29.0 en `formatDateTimeForInput()` y en el `copy()` del controller.
- **Cotización en payload Tango**: el `TangoOrderMapper` lee `cotizacion` desde el array `$pedidoCabecera` y lo emite como `COTIZACION` (float). Si la cabecera no tiene cotización, no se inyecta el campo (Tango usa default). Para Presupuestos, `PresupuestoTangoService::send()` siempre inyecta el valor (default 1). Para PDS no se inyecta hoy (PDS no tiene cotización; ver `PedidoServicioTangoService`).
- **Leyendas en payload Tango**: el mapper itera 1..5 y emite `LEYENDA_1` a `LEYENDA_5` SOLO si la leyenda no está vacía. Si está vacía, NO se inyecta el campo (lo deja en NULL del lado de Tango).
- **PrintForm / Canvas**: `CrmPresupuestoPrintContextBuilder::build()` expone los 8 campos bajo el árbol `presupuesto.*` (ej: `presupuesto.cotizacion`, `presupuesto.leyenda_1`, etc). Los Canvas pueden referenciarlos directamente desde el PrintForms designer.
- **Campos compartidos con PDS y Pedidos**: el `TangoOrderMapper` es compartido. `cotizacion` y `leyenda_1..5` son OPCIONALES en el array `$pedidoCabecera`, así que PDS y Pedidos web no se afectan. Si se quisiera sumarlos a esos módulos, basta con inyectarlos al `$cabecera` que arman sus propios services.

### Descripción de renglón → DESCRIPCION_ARTICULO (release 1.29.x)

Cada renglón del presupuesto tiene **dos** columnas relacionadas con la descripción del artículo:

- **`articulo_descripcion_original`** (VARCHAR(255) NULL) — Nombre del artículo al momento de seleccionarlo desde el picker. **NUNCA se pisa después** — se guarda una sola vez al elegir el artículo.
- **`articulo_descripcion_snapshot`** (VARCHAR(255)) — Descripción "actual" del renglón. El operador puede editarla en el textarea de la columna Descripción del grid; cada save persiste lo que tipeó.

**Detección de "modificada"**: si `snapshot ≠ original`, la descripción fue editada por el operador. La UI lo refleja con borde naranja en el textarea + badge "Editada". El JS lo evalúa en vivo (`input` listener) y al render inicial.

**Payload Tango** (`TangoOrderMapper::map`):
- La descripción del operador se parte en chunks de **50 caracteres** vía `TangoOrderMapper::chunkDescripcion()`. Algoritmo:
  1. Split por saltos de línea manuales del textarea (`\n`, `\r\n`).
  2. Cada línea, si excede 50 chars, se subdivide con `wordwrap` (cut=true para palabras solitarias > 50).
  3. Trim de cada chunk + descarte de vacíos.
- **Primer chunk** → `DESCRIPCION_ARTICULO` (campo principal, 50 chars máx en Tango).
- **Resto de chunks** → `DESCRIPCION_ADICIONAL_DTO[]` como array, cada item con shape:
  ```json
  { "DESCRIPCION": "<chunk>", "DESCRIPCION_ADICIONAL": null }
  ```
- **NO se usa `DESCRIPCION_ADICIONAL_ARTICULO` (DESC_ADIC)** del renglón. Tango lo limita a **20 caracteres** (confirmado el 2026-04-29 con error de Tango: `"El campo 'DESC_ADIC' debe ser menor o igual a 20 caracteres"`). Es inútil para descripciones reales — preferimos el array DTO que sí soporta texto largo.

**UI**:
- Textarea de 3 filas (en lugar de 2) para que se note que admite multilínea.
- Badge "Editada" (naranja) cuando snapshot ≠ original.
- Label en vivo bajo el textarea: `"· N líneas a Tango (1 principal + N-1 adicionales)"`. Se calcula client-side con un mini-replicador del algoritmo PHP.

**Decisión editorial**: el operador puede escribir multilínea libremente; el sistema le dice cuántas líneas van a viajar antes de guardar. Si quiere control fino del corte, escribe una línea por concepto. Si escribe un párrafo largo, el sistema lo parte por palabras.

**Backfill de items históricos**: la migración `2026_04_29_03_alter_crm_presupuesto_items_add_descripcion_original.php` setea `articulo_descripcion_original = articulo_descripcion_snapshot` para items existentes — quedan marcados como "no modificados" (snapshot == original). Esto es conservador: no genera alarmas falsas en presupuestos viejos.

### Comentarios + Observaciones → OBSERVACIONES Tango (release 1.30.0)

La cabecera tiene 2 campos `TEXT NULL`: `comentarios` y `observaciones`. Se editan en el form como 2 textareas paralelos (col-6 c/u, inspirados en el Tango legacy donde aparecen como paneles "Comentarios" y "Observaciones" lado a lado). En el payload Tango viajan **concatenados como un único string** en el campo `OBSERVACIONES`.

**Reglas operativas:**

- **Sanitización defensiva**: `PresupuestoTangoService::buildObservaciones()` colapsa CRLF/LF/whitespace a un único espacio antes de armar el string. Tango Connect rechaza ciertos caracteres de control en algunos perfiles, así que es más seguro mandar texto plano de una sola línea.
- **Separador entre bloques: `" | "`** (pipe con espacios). NO usar `\n\n` ni separadores con caracteres especiales.
- **Truncado a 950 chars** al final del armado (límite confirmado de Tango Connect en este campo). El form muestra un contador en vivo `N / 950 chars a Tango` y un banner de warning cuando se supera.
- **Reinyección post-array_filter en el mapper**: `TangoOrderMapper::map()` calcula el valor de `OBSERVACIONES` aparte y lo re-asigna al payload **después** del `array_filter` que limpia nulls. Esto blinda el campo crítico contra regresiones (release 1.30.0).
- **PrintForms / Canvas**: ambos campos quedan expuestos en `CrmPresupuestoPrintContextBuilder` como `presupuesto.comentarios` y `presupuesto.observaciones`.

### Trampa Tango: perfil de pedido bloquea OBSERVACIONES (2026-04-30)

**Síntoma**: pedido se crea OK en Tango (`succeeded: true`, `nro_comprobante` asignado) pero el campo `OBSERVACIONES` aparece como `null` en el GET, aunque el payload local lo lleva con contenido válido.

**Causa**: el `ID_PERFIL_PEDIDO` configurado en Tango (ej: `1 - INGRESA TODOS LOS DATOS`) puede tener marcado el campo OBSERVACIONES como **no editable desde la API**. En ese caso Tango responde el primer envío con:

```
"messages": ["El perfil utilizado (X - <NOMBRE>) no permite editar el campo OBSERVACIONES."]
"succeeded": false
```

Y el `shouldRetryWithoutObservaciones()` del service detecta el rechazo, hace `unset($payload['OBSERVACIONES'])` y reintenta. El segundo envío sale OK pero **sin el campo**, así que el operador ve "envío exitoso" mientras los datos se perdieron silenciosamente.

**Solución**: NO es de código. El operador del cliente tiene que ir a Tango → Ventas → Pedidos → Perfiles → editar el perfil usado → habilitar la edición del campo OBSERVACIONES.

**Reglas defensivas que dejamos en el código**:

- El retry sigue siendo útil como red de seguridad para casos donde el campo es inválido por otro motivo (longitud, caracteres, etc).
- Si vuelve a aparecer un caso "el campo no llega aunque debería", agregar logging temporal del primer response cuando se dispara el retry para identificar la causa raíz.

**Trampa típica**: si el operador edita la descripción y guarda, al re-abrir el presupuesto el textarea muestra lo editado, NO el original. El badge "Editada" + borde naranja le recuerdan que esa descripción difiere del catálogo Tango. Para "resetear" la descripción al original, hay que hacerlo a mano (no hay botón "restaurar" — pendiente como mejora futura si Charly lo pide).

## Tipo de cambios permitidos
- Agregar columnas de cálculo, subtotales o lógica de impuestos (IVA, IIBB percibidos) visualmente en el DOM y en los resúmenes PDF.
- Optimizar la carga asíncrona de artículos en el grid modal del formulario de cotización.

## Tipo de cambios sensibles
- Tocar el normalizador numérico (Cálculo de alícuotas, redondeos a 2 decimales y sumatorias totales). Si hay un desvío de 1 centavo entre el cálculo del CRM y lo que recibe Tango, el conector suele rechazar el lote transaccional.
- Modificar el flujo de Sync de Catálogo Comercial.

## Riesgos conocidos
- **Inconsistencia de Precios:** Como el catálogo comercial se actualiza periódicamente de forma asíncrona ("Caché local"), existe la posibilidad operativa de que un presupuesto se emita con una Lista de Precios de las 10 AM, y se envíe a Tango a las 11 AM luego de que el ERP haya subido precios internamente, generando un mismatch o rechazo.

## Checklist post-cambio
- [ ] Listado de presupuestos se muestra respetando tenant (empresa).
- [ ] Edición y Guardado de renglones recalcula correctamente sumatorias y no deja renglones huérfanos.
- [ ] Generación PDF de impresión es exitosa y no omite el logo / cabecera de la empresa.
- [ ] Envío a Tango reporta status OK / ERROR correctamente interceptado.
- [ ] Crear un presupuesto nuevo genera una fila en `rxn_geo_eventos` con `event_type='presupuesto.created'` y `entidad_id` igual al ID del presupuesto creado. Editar un presupuesto existente NO genera evento nuevo. Una falla del servicio de geo no impide el guardado del presupuesto.

## Integración con PWA mobile (release 1.33.0+)

El módulo Presupuestos es el **primer caso de uso PWA** de la suite. La PWA mobile (`App\Modules\RxnPwa`) crea presupuestos offline y los sincroniza al server reusando este controller/repo. Ver `app/modules/RxnPwa/MODULE_CONTEXT.md` para el diseño completo.

### Columna `tmp_uuid_pwa` (release 1.33.0)

`crm_presupuestos.tmp_uuid_pwa VARCHAR(50) NULL` con `UNIQUE KEY uniq_crm_presupuestos_tmp_uuid_pwa`. Identifica un draft mobile origen. NULL para presupuestos creados desde el web (no choca con UNIQUE en MySQL).

**Idempotencia del sync mobile**: si el cliente PWA reintenta el mismo POST por red intermitente, el server detecta la fila existente por `tmp_uuid_pwa` y devuelve el id_server existente sin duplicar.

### REGLA DURA — `unset($data['tmp_uuid_pwa'])` en flujos derivativos

Cualquier flujo que cree un presupuesto basándose en otro existente DEBE remover el campo del payload antes del INSERT. Si no, choca contra el UNIQUE y falla con "Duplicate entry TMP-...".

Casos cubiertos:
- `PresupuestoController::copy()` — `unset($data['id'], $data['numero'], $data['tmp_uuid_pwa'])`.
- `PresupuestoRepository::createNewVersion()` — arma el payload manualmente sin incluir el campo.

Si en el futuro se agrega "duplicar última versión", import desde Excel, copy from PDS, o cualquier otra forma de derivar un presupuesto, **respetar la regla**.

**Bug histórico (release 1.35.1)**: `copy()` arrastraba `tmp_uuid_pwa` del original — todo intento de copiar un presupuesto creado desde la PWA fallaba con UNIQUE violation. Documentado para no repetir.

### REGLA DURA — `id_interno` ≠ `id` al armar el payload de Tango

Para que Tango acepte ID_GVA01/10/23/24, los `condicion_id_interno`, `lista_id_interno`, `vendedor_id_interno`, `transporte_id_interno` que viajan en el `presupuesto` deben venir de la columna `id_interno` de `crm_catalogo_comercial_items`, NO del PK auto-increment `id`.

`PresupuestoController::resolveCatalogSelection()` lo hace bien (línea ~826): `(int) $option['id_interno']`. **Si replicás este patrón en otro módulo (RxnPwaSyncService::resolveCatalogItem fue víctima en 1.35.0/1.35.2), respetar la columna correcta.** Tango rechaza con "No existe condición de venta para el ID_GVA01 ingresado: <PK_local>" si se confunde.

### Defaults comerciales del cliente — fuente canónica

Los IDs comerciales del cliente viven en `crm_clientes`:

| Campo | Fallback Tango | Mapea a (Tango) |
|-------|----------------|-----------------|
| `id_gva10_lista_precios`     | `id_gva10_tango` | ID_GVA10 (lista de precios) |
| `id_gva01_condicion_venta`   | `id_gva23_tango` | ID_GVA01 (condición de venta) |
| `id_gva23_vendedor`          | `id_gva01_tango` | ID_GVA23 (vendedor) |
| `id_gva24_transporte`        | `id_gva24_tango` | ID_GVA24 (transporte) |

`PresupuestoController::clientContext()` resuelve estos campos al seleccionar cliente en el form web. La PWA replica el mismo comportamiento client-side (`applyClienteDefaults` en `rxnpwa-form.js`).
