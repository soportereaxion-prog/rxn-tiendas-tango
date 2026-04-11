# MODULE_CONTEXT — CrmLlamadas

## Nivel de criticidad
MEDIO. Provee un registro de llamadas (posiblemente vía Anura u otro proveedor de telefonía) asociadas al CRM, permitiendo vincular números de origen con clientes.

## Propósito
Registrar y listar el historial de llamadas de la empresa, y proveer una interfaz para vincular números telefónicos entrantes con clientes existentes en la base local del CRM.

## Alcance
**QUÉ HACE:**
- Muestra el historial de llamadas filtrado y paginado.
- Permite vincular de forma manual o automática (`vincularClienteApi`) una llamada a un cliente vía `cliente_id` y `numero_origen`.
- Permite desvincular un cliente de un número de teléfono.
- Soporta operaciones de eliminación suave (papelera), restauración y eliminación definitiva, tanto individuales como masivas.

**QUÉ NO HACE:**
- No gestiona el establecimiento de la llamada (es un registro/log a posteriori).
- No sincroniza clientes hacia Tango (se apoya en los clientes ya existentes cacheados en el sistema).

## Piezas principales
- **Controladores:** `CrmLlamadasController`, `WebhookController` (para ingesta de llamadas).
- **Repositorios:** `CrmLlamadaRepository` (CRUD, vinculación y desvinculación).
- **Modelos:** `CrmLlamada`.
- **Vistas:** `views/index.php`.
- **Rutas/Pantallas:** `/mi-empresa/crm/llamadas`.
- **Tablas/Persistencia:** `crm_llamadas`.

## Seguridad Base (Política de Implementación)
- **Aislamiento Multiempresa**: OBLIGATORIO. El controlador usa `Context::getEmpresaId()` obligatoriamente para inyectar filtros en todas las consultas y acciones (vía `getEmpresaIdOrDie`). 
- **Permisos / Guards**: Protegido mediante `AuthService::requireLogin()`. Validaciones de acceso implícitas para el tenant actual.
- **Mutación**: Todo borrado, vinculación y desvinculación se realiza obligatoriamente vía POST. No hay mutación a través de peticiones HTTP GET.
- **Validación Server-Side**: IDs casteados a enteros obligatoriamente (`(int) $id`). Validación estricta de variables inyectadas.
- **Escape Seguro (XSS)**: Salidas protegidas y limitadas en listados; todo feedback de denegación usa `htmlspecialchars` en el renderizado de errores.
- **Acceso Local**: Solo visible y modificable bajo la sesión actual de la empresa.
- **Token CSRF**: (A considerar si aplica globalmente en el CRM para POST forms).

## Dependencias directas
- `App\Modules\CrmClientes\CrmClienteRepository` (para la asociación cliente <-> teléfono).
- `Context`, `AuthService`, `OperationalAreaService`.

## Dependencias indirectas / impacto lateral
- Un cambio en la tabla de clientes o su repositorio podría afectar las consultas de cruce en llamadas.

## Reglas operativas del módulo
- El Soft-Delete utiliza el campo `status=papelera`. Las llamadas borradas no desaparecen físicamente a menos que se invoque `forceDelete`.
- Todo cambio de vinculación debe actualizar la asociación del número a nivel tenant.
- **Persistencia de filtros de listado**: el input de búsqueda F3 (`search`), el campo de búsqueda (`field`), la cantidad por página (`limit`), el filtro de estado de negocio (`estado`), el filtro de categoría (`categoria_id`, donde aplique) y los filtros Motor BD (`f[campo][op|val]`) se persisten automáticamente en `localStorage` scopeados por `pathname + empresa_id` via `public/js/rxn-filter-persistence.js` (cargado inline desde `admin_layout.php`). Al volver al listado, los filtros se restauran y se reinicia en la primera página. `status` (activos/papelera), `sort`, `dir` y `area` quedan fuera por ser navegación u orden. Para limpiarlos: `?reset_filters=1` (lo dispara `rxn-advanced-filters.js` al borrar BD) o `window.rxnFilterPersistence.clear()`. Los filtros "locales" (selección por columna) siguen viviendo en `sessionStorage` via `rxn-advanced-filters.js` con key `rxn_lf::`.
- **Flujo "Generar PDS"**: el botón (visible cuando `evento_link === 'HANGUP'`) redirige a `/mi-empresa/crm/pedidos-servicio/crear?diagnostico=...&inicio=...&fin=...&cliente_id=...`. `PedidoServicioController::defaultFormState()` lee `inicio`/`fin` del query string y el form suprime el tick() de auto-hora cuando `inicio` viene por URL, así queda registrada la ventana real de la llamada.

## Tipo de cambios permitidos
- Agregar visualizaciones adicionales de la llamada (duración, grabación si existiese).
- Mejorar el algoritmo de sugerencia de vinculación de cliente por teléfono.

## Tipo de cambios sensibles
- Alterar la lógica de Webhooks de llamadas entrantes (puede interrumpir el logeo del call center).
- Modificar el filtro `empresa_id` en las sentencias DELETE/RESTORE masivas.

## Riesgos conocidos
- Acumulación masiva de registros: Las llamadas pueden crecer exponencialmente; requiere paginación eficiente en base de datos.

## Checklist post-cambio
- [ ] Listado renderiza correctamente con su paginación y filtros.
- [ ] Botones de papelera/restaurar masivo afectan únicamente a la empresa en curso.
- [ ] El endpoint API de vinculación de cliente responde en formato JSON y valida `empresa_id`.
