# MODULE_CONTEXT — CrmPedidosServicio

## Nivel de criticidad
MUY ALTO. Es una pieza transaccional fundamental (PDS). Permite iniciar, administrar y sincronizar servicios de reparación/asistencia técnica asociados a Tango Connect. Su rotura detiene el circuito de facturación de servicios técnicos de la empresa.

## Propósito
Gestionar un ABM avanzado de "Pedidos de Servicio", permitiendo al equipo del CRM dar de alta servicios, vincularlos con clientes locales, determinar catálogos comerciales/artículos, adjuntar diagnósticos técnicos y enviarlos remotamente hacia Tango Connect como órdenes validadas.

## Alcance
**QUÉ HACE:**
- ABM transaccional completo, con búsqueda, paginación, filtros avanzados y vista de listado.
- Exposición de Endpoint de sugerencias (`suggestions()`) muy rápido basado en Spotlight, para encontrar PDS existentes por múltiples campos (número, cliente, artículo, estado).
- Carga de capturas/imágenes de diagnóstico en formato Base64 (`syncAdjuntos`).
- Despacho explícito de la orden hacia Tango vía `PedidoServicioTangoService` al guardar la orden (`action=tango`).
- Eliminación en etapas: papelera (Soft-Delete), restauración y borrado definitivo (forceDelete).

**QUÉ NO HACE:**
- No realiza cálculos de impuestos ni stock localmente; toda esa lógica delega en Tango Connect al momento de enviar.
- No auto-factura; su misión termina al lograr un alta exitosa del pedido en el ERP remoto.

## Piezas principales
- **Controladores:** `PedidoServicioController`.
- **Servicios:** `PedidoServicioTangoService` (encapsula el armado del payload JSON y comunicación HTTP), `ClasificacionCatalogService`, `PedidoServicioPrintContextBuilder`.
- **Repositorios:** `PedidoServicioRepository`.
- **Vistas:** `views/index.php`, `views/form.php`.
- **Rutas/Pantallas:** `/mi-empresa/crm/pedidos-servicio`.
- **Tablas/Persistencia:** `crm_pedidos_servicio`.

## Seguridad Base (Política de Implementación)
- **Aislamiento Multiempresa**: OBLIGATORIO Y ESTRICTO. Todo el ciclo de vida del PDS filtra obligatoriamente por `Context::getEmpresaId()` (ej. `$empresaId = (int) Context::getEmpresaId()`).
- **Permisos / Guards**: Intervención de `AuthService::requireLogin()`. 
- **Mutación**: Todo lo destructivo (eliminar, restore, guardado, actualización y envío a Tango) responde excluyentemente a peticiones HTTP POST (o endpoints restringidos en su enrutador).
- **Validación Server-Side**: La clase usa un método formal `validateRequest()` que levanta `ValidationException` mapeando arrays de errores sobre un formulario pre-hidratado `buildFormStateFromPost()`, impidiendo guardar basura en la tabla.
- **Escape Seguro (XSS)**: Las descripciones de fallas, diagnósticos y textos libres del operador deben escapar su salida en las plantillas y el `printContextBuilder`.
- **Acceso Local**: Las transacciones ocurren enteramente acopladas a la empresa logueada y su API Key de Tango.

## Dependencias directas
- Módulo `CrmClientes` para el selector de cliente en el form.
- Subsistema de UI `Flash` messages y `OperationalAreaService`.
- Clase global `App\Modules\Tango\TangoService` o librerías HTTP para la interacción de capa red.
- **Módulo `Drafts`** (release 1.28.0) — el `<form>` del PDS está marcado con `data-rxn-draft="pds:<id-o-new>"` para autoguardado server-side. Si la sesión muere o el browser se cierra a medio cargar, al volver al PDS el banner del JS `rxn-draft-autosave.js` ofrece retomar. Ver `app/modules/Drafts/MODULE_CONTEXT.md`.
- **RxnGeoTracking**: Al crear exitosamente un PDS (no en ediciones, no en el envío a Tango posterior), `PedidoServicioController::store()` invoca `GeoTrackingService::registrar('pds.created', $pdsId, 'pds')` para asentar el evento de auditoría geolocalizada. Fire-and-forget: una falla del servicio no rompe el alta del PDS. El `evento_id` queda en `$_SESSION['rxn_geo_pending_event_id']` para auto-reporte posterior via meta tag en `admin_layout`. Ver `app/modules/RxnGeoTracking/MODULE_CONTEXT.md`.

## Dependencias indirectas / impacto lateral
- El endpoint `suggestions()` es un proveedor de datos para selectores relacionados. Cambiar su contrato de salida `['id', 'label', 'value', 'caption']` rompe modales o workflows satélites.

## Reglas operativas del módulo
- La persistencia del PDS y la transmisión a Tango están disociadas visualmente por botones (`Guardar` vs `Guardar y Enviar a Tango`), donde ambas caen al `store/update` del controller enviando distintos flags (`$_POST['action'] === 'tango'`).
- Las imágenes de diagnóstico cruzan el formulario como strings Base64 ocultos en el DOM.
- **Geo-tracking en creación**: El evento `pds.created` se registra **únicamente en el alta exitosa** del PDS (primer INSERT en `crm_pedidos_servicio`), no en updates, no en el envío a Tango, no en la carga de adjuntos. El objetivo es auditar dónde estaba el técnico/operador al originar el pedido de servicio. La llamada ocurre **después** del commit del alta y después del `syncAdjuntos`, pero antes del branch de envío a Tango — porque el PDS ya existe en DB aunque el envío a Tango falle.
- **Persistencia de filtros de listado**: el input de búsqueda F3 (`search`), el campo de búsqueda (`field`), la cantidad por página (`limit`), el filtro de estado de negocio (`estado`), el filtro de categoría (`categoria_id`, donde aplique) y los filtros Motor BD (`f[campo][op|val]`) se persisten automáticamente en `localStorage` scopeados por `pathname + empresa_id` via `public/js/rxn-filter-persistence.js` (cargado inline desde `admin_layout.php`). Al volver al listado, los filtros se restauran y se reinicia en la primera página. `status` (activos/papelera), `sort`, `dir` y `area` quedan fuera por ser navegación u orden. Para limpiarlos: `?reset_filters=1` (lo dispara `rxn-advanced-filters.js` al borrar BD) o `window.rxnFilterPersistence.clear()`. Los filtros "locales" (selección por columna) siguen viviendo en `sessionStorage` via `rxn-advanced-filters.js` con key `rxn_lf::`.
- **Creación desde Llamadas (fecha_inicio pre-cargada)**: cuando se entra al form con `?inicio=...&fin=...&diagnostico=...&cliente_id=...` (flujo "Generar PDS" desde `CrmLlamadas`), `defaultFormState()` respeta esos valores y el script inline de `views/form.php` NO emite el `tick()` que reemplaza `fecha_inicio` por `new Date()` — condicionado por `!isset($_GET['inicio'])`. Cualquier nuevo origen que pretenda precargar fechas debe pasar por `?inicio=` para heredar este comportamiento.
- **Guardar se queda en el PDS; Volver es contextual a la Tratativa** (v1.19.0): `PedidoServicioController::resolveReturnPath` siempre retorna a `/editar` — guardar NO saca al usuario del form. El Volver del header declara `$pdsBackHref` / `$pdsBackTitle` calculados antes del `ob_start()`: si el PDS tiene `tratativa_id` → detalle de la tratativa; si no → listado. El `<a>` lleva `data-rxn-back` para que Escape también navegue al mismo destino (ver `public/js/rxn-escape-back.js`).

## Tipo de cambios permitidos
- Incorporar nuevos campos técnicos (ej. Número de serie del equipo a reparar, Marca, Modelo) ampliando el `validateRequest()` y la vista `form.php`.
- Sumar flujos de impresión en PDF desde el grid (apoyándose en `PrintContextBuilder`).

## Tipo de cambios sensibles
- Alterar el generador de JSON de `PedidoServicioTangoService`: un error de tipado u omisión de nodo generará un rechazo masivo por la validación de Axoft en Connect.
- Modificar la forma de gestionar adjuntos Base64 en PHP puede derivar en un consumo excesivo de memoria o bloqueos al procesar PDS muy cargados.

## Riesgos conocidos
- **Sincronización desfasada**: Si la orden se guarda localmente pero Tango devuelve HTTP 500, el registro queda "en espera". Carece de background-workers de reintentos; depende del operador darle "Enviar a Tango" de nuevo.
- **Payloads Pesados**: Guardar imágenes directamente codificadas en Base64 enviadas en POST form-data exige el límite del `post_max_size` de PHP.
- **PDS huérfano en Tango al borrar desde papelera**: Si el operador hace hard-delete (forceDelete) de un PDS que ya tiene `tango_nro_pedido` asignado, el PDS desaparece de RXN pero queda vivo en Tango sin contraparte. Caso real: incidente del PDS X0065400007931 (2026-05-05). **Mitigación implementada en 1.46.3**: audit log de eliminación captura snapshot completo + atribución → ver dataset "PDS Eliminados (Auditoría)" en RxnLive con flag `estaba_en_tango = "Sí — quedó huérfano en Tango"` para detectar pendientes a anular en el ERP. **Mitigación pendiente**: confirm UX en el botón de hard-delete cuando `tango_nro_pedido != NULL`.

## Auditoría de eliminación permanente (1.46.3)

Desde la 1.46.3 todo `forceDelete` (hard-delete) sobre `crm_pedidos_servicio` queda registrado automáticamente en `crm_pedidos_servicio_audit_deletes` vía trigger SQL `BEFORE DELETE`. La red de seguridad es triple:

1. **Trigger SQL** (`tr_crm_pds_audit_before_delete`): captura cualquier `DELETE FROM crm_pedidos_servicio`, incluyendo deletes hechos desde phpMyAdmin/HeidiSQL/SQL manual. Aunque alguien evite el código de aplicación, el trigger igual loguea.
2. **`PedidoServicioRepository::forceDeleteByIds`**: setea `@audit_user_id` y `@audit_user_name` (MySQL session vars) antes del `DELETE` para que el trigger las lea como atribución. Si vienen NULL (delete sin contexto de sesión), el audit registra NULL y eso señaliza "delete no atribuible" sin perder el snapshot.
3. **Snapshot completo en `before_json` (LONGTEXT)**: el trigger emite `JSON_OBJECT(...)` con todas las columnas del row borrado. Cualquier campo del PDS queda capturado — incluso campos nuevos que se sumen al schema sin tocar el trigger.

**Vista expuesta**: `RXN_LIVE_VW_PDS_DELETES` registrada en `RxnLiveService::$datasets` como dataset `pds_eliminados`. Resuelve `tango_estado_label` legible y agrega flag calculado `estaba_en_tango` para detectar huérfanos en el ERP.

**Si se modifica `forceDeleteByIds`**: mantener el bloque que setea `@audit_user_id` y `@audit_user_name` antes del `$stmt->execute()`. Sin eso, los registros de audit van a quedar sin atribución.

**Si se modifica el schema de `crm_pedidos_servicio`** (agregar columnas): el `before_json` del trigger las captura solo si se actualiza la lista de `OLD.<col>` en `JSON_OBJECT(...)` dentro del trigger. La migración `2026_05_05_02_create_crm_pds_audit_deletes.php` muestra el patrón. Los campos columnados explícitos (numero, tango_nro_pedido, etc) NO se actualizan automáticamente — agregar la columna al `INSERT INTO ... VALUES (OLD....)` también si querés que aparezca en RxnLive como columna propia.

## Checklist post-cambio
- [ ] ABM completo salva y edita registros.
- [ ] El envío a Tango devuelve Flash success (ok) o danger y no interrumpe fatalmente el controller.
- [ ] Listado renderiza y la paginación funciona.
- [ ] `suggestions()` endpoint sigue devolviendo el formato de llaves exacto.
- [ ] Crear un PDS nuevo genera una fila en `rxn_geo_eventos` con `event_type='pds.created'` y `entidad_id` igual al ID del PDS creado. Editar un PDS existente NO genera evento nuevo. Una falla del servicio de geo no impide el alta del PDS.
