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
