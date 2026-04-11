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

## Dependencias indirectas / impacto lateral
- El endpoint `suggestions()` es un proveedor de datos para selectores relacionados. Cambiar su contrato de salida `['id', 'label', 'value', 'caption']` rompe modales o workflows satélites.

## Reglas operativas del módulo
- La persistencia del PDS y la transmisión a Tango están disociadas visualmente por botones (`Guardar` vs `Guardar y Enviar a Tango`), donde ambas caen al `store/update` del controller enviando distintos flags (`$_POST['action'] === 'tango'`).
- Las imágenes de diagnóstico cruzan el formulario como strings Base64 ocultos en el DOM.
- **Persistencia de filtros de listado**: el input de búsqueda F3 (`search`), el campo de búsqueda (`field`), la cantidad por página (`limit`), el filtro de estado de negocio (`estado`), el filtro de categoría (`categoria_id`, donde aplique) y los filtros Motor BD (`f[campo][op|val]`) se persisten automáticamente en `localStorage` scopeados por `pathname + empresa_id` via `public/js/rxn-filter-persistence.js` (cargado inline desde `admin_layout.php`). Al volver al listado, los filtros se restauran y se reinicia en la primera página. `status` (activos/papelera), `sort`, `dir` y `area` quedan fuera por ser navegación u orden. Para limpiarlos: `?reset_filters=1` (lo dispara `rxn-advanced-filters.js` al borrar BD) o `window.rxnFilterPersistence.clear()`. Los filtros "locales" (selección por columna) siguen viviendo en `sessionStorage` via `rxn-advanced-filters.js` con key `rxn_lf::`.
- **Creación desde Llamadas (fecha_inicio pre-cargada)**: cuando se entra al form con `?inicio=...&fin=...&diagnostico=...&cliente_id=...` (flujo "Generar PDS" desde `CrmLlamadas`), `defaultFormState()` respeta esos valores y el script inline de `views/form.php` NO emite el `tick()` que reemplaza `fecha_inicio` por `new Date()` — condicionado por `!isset($_GET['inicio'])`. Cualquier nuevo origen que pretenda precargar fechas debe pasar por `?inicio=` para heredar este comportamiento.

## Tipo de cambios permitidos
- Incorporar nuevos campos técnicos (ej. Número de serie del equipo a reparar, Marca, Modelo) ampliando el `validateRequest()` y la vista `form.php`.
- Sumar flujos de impresión en PDF desde el grid (apoyándose en `PrintContextBuilder`).

## Tipo de cambios sensibles
- Alterar el generador de JSON de `PedidoServicioTangoService`: un error de tipado u omisión de nodo generará un rechazo masivo por la validación de Axoft en Connect.
- Modificar la forma de gestionar adjuntos Base64 en PHP puede derivar en un consumo excesivo de memoria o bloqueos al procesar PDS muy cargados.

## Riesgos conocidos
- **Sincronización desfasada**: Si la orden se guarda localmente pero Tango devuelve HTTP 500, el registro queda "en espera". Carece de background-workers de reintentos; depende del operador darle "Enviar a Tango" de nuevo.
- **Payloads Pesados**: Guardar imágenes directamente codificadas en Base64 enviadas en POST form-data exige el límite del `post_max_size` de PHP.

## Checklist post-cambio
- [ ] ABM completo salva y edita registros.
- [ ] El envío a Tango devuelve Flash success (ok) o danger y no interrumpe fatalmente el controller.
- [ ] Listado renderiza y la paginación funciona.
- [ ] `suggestions()` endpoint sigue devolviendo el formato de llaves exacto.
