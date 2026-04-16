# MODULE_CONTEXT — CrmTratativas

## Nivel de criticidad
MEDIO-ALTO. Es un módulo agregador del CRM: no sostiene transacciones con Tango Connect por sí mismo, pero agrupa bajo un único "caso comercial" a piezas críticas (PDS y Presupuestos). Si se rompe, PDS y Presupuestos siguen funcionando de forma independiente, pero se pierde la trazabilidad comercial de las oportunidades con los clientes.

## Propósito
Actuar como **agregador de oportunidades comerciales**. Una Tratativa representa una negociación con un cliente que puede incluir uno o varios Pedidos de Servicio (PDS) y uno o varios Presupuestos. Permite al equipo comercial ver todo el historial de interacción con un cliente bajo un mismo "caso", con estado, probabilidad y valor estimado.

Inspirado en el concepto de "oportunidad" / "deal" de los CRMs tradicionales (Salesforce, HubSpot, Zoho), pero adaptado al lenguaje propio del proyecto.

## Alcance
**QUÉ HACE:**
- ABM completo de tratativas con búsqueda, paginación, filtros por estado y filtros avanzados (Motor BD).
- Vista detalle con sub-grillas de PDS, Presupuestos y Notas asociadas.
- Creación de PDS, Presupuestos o Notas ya vinculadas a una tratativa (vía query param `?tratativa_id=X&cliente_id=Y`, reutilizando los forms existentes de `CrmPedidosServicio`, `CrmPresupuestos` y `CrmNotas`).
- Endpoint `suggestions()` para buscador global F3 / spotlight.
- Endpoint `clientSuggestions()` para el selector de cliente dentro del form.
- Papelera con soft-delete, restauración y borrado permanente.

**QUÉ NO HACE:**
- NO sincroniza con Tango Connect. Cada PDS y Presupuesto asociado sigue su propio flujo de envío a Tango de forma independiente.
- NO calcula totales agregados (suma de presupuestos vinculados). El `valor_estimado` es un campo manual cargado por el operador.
- NO gestiona etapas de pipeline visual (kanban). El `estado` es un ENUM simple (nueva, en_curso, ganada, perdida, pausada).
- NO envía notificaciones ni alertas por vencimiento de `fecha_cierre_estimado`.

## Piezas principales
- **Controlador:** `TratativaController` (extends `App\Core\Controller`).
- **Repositorio:** `TratativaRepository`.
- **Vistas:** `views/index.php` (listado), `views/form.php` (alta/edición), `views/detalle.php` (vista maestra con sub-grillas de PDS y Presupuestos).
- **Rutas/Pantallas:** `/mi-empresa/crm/tratativas`.
- **Tablas/Persistencia:** `crm_tratativas`.
- **Migraciones:**
    - `database/migrations/2026_04_11_create_crm_tratativas.php`
    - `database/migrations/2026_04_11_add_tratativa_id_to_pds_presupuestos.php` (agrega `tratativa_id INT NULL` a `crm_pedidos_servicio` y `crm_presupuestos` con índice).
    - `database/migrations/2026_04_15_add_tratativa_id_to_crm_notas.php` (agrega `tratativa_id INT NULL` a `crm_notas` con índice; mismo patrón que PDS/Presupuestos).

## Seguridad Base (Política de Implementación)
- **Aislamiento Multiempresa**: OBLIGATORIO Y ESTRICTO. Todo el ciclo de vida filtra por `Context::getEmpresaId()` (controller y repository). Todos los métodos del repo reciben `empresaId` explícito.
- **Permisos / Guards**: `AuthService::requireLogin()` al inicio de cada acción. A nivel router se usa `requireCrmAccess()`.
- **Mutación**: todas las acciones destructivas (eliminar, restore, forceDelete, eliminar/restore/forceDelete masivos, store, update) responden exclusivamente a peticiones HTTP POST.
- **Validación Server-Side**: método formal `validateRequest()` que levanta `ValidationException` con array de errores, vuelve a renderizar el form con los datos ya escapados.
- **Escape Seguro (XSS)**: todas las salidas dinámicas usan `htmlspecialchars()`. Las descripciones y textos libres escapan en vistas.
- **No SQL Injection**: prepared statements en todas las queries (PDO). Placeholders con `:name` y `bindValue`.
- **forceDelete seguro**: al borrar permanentemente una tratativa, primero se desvinculan los PDS y Presupuestos asociados (`tratativa_id = NULL`) para no romper referencias históricas.

## Dependencias directas
- `App\Modules\CrmClientes\CrmClienteRepository` — para el selector de cliente en el form y la validación de existencia al guardar.
- Módulo `CrmPedidosServicio` — vía query param `?tratativa_id=X` desde la vista detalle (hook quirúrgico en PedidoServicioController que persiste la vinculación).
- Módulo `CrmPresupuestos` — vía query param `?tratativa_id=X` desde la vista detalle (idem).
- Módulo `CrmNotas` — vía query param `?tratativa_id=X&cliente_id=Y` desde la vista detalle. `CrmNotaRepository::findByTratativaId()` alimenta la sub-tabla de notas, y `TratativaRepository::findNotasByTratativaId()` es una fachada delgada sobre ese método.
- `App\Shared\Services\OperationalAreaService` para resolver rutas de dashboard y ayuda.
- **RxnGeoTracking**: Al crear exitosamente una tratativa (no en ediciones, no al vincular/desvincular PDS/Presupuestos/Notas), `TratativaController::store()` invoca `GeoTrackingService::registrar('tratativa.created', $tratativaId, 'tratativa')` para asentar el evento de auditoría geolocalizada. Fire-and-forget: el alta de la tratativa sigue funcionando aunque el servicio falle. El `evento_id` queda en `$_SESSION['rxn_geo_pending_event_id']` para auto-reporte posterior via meta tag en `admin_layout`. Ver `app/modules/RxnGeoTracking/MODULE_CONTEXT.md`.

## Dependencias indirectas / impacto lateral
- El endpoint `suggestions()` es proveedor de datos para el buscador global F3 (spotlight). Cambiar su contrato de salida `['id', 'label', 'value', 'caption']` rompe cualquier consumidor del buscador unificado.
- Los listados `findPdsByTratativaId()` y `findPresupuestosByTratativaId()` dependen de que las tablas `crm_pedidos_servicio` y `crm_presupuestos` tengan la columna `tratativa_id` (creada por la migración `2026_04_11_add_tratativa_id_to_pds_presupuestos.php`). Si la migración no corre, la vista detalle arroja error SQL.

## Reglas operativas del módulo
- **Creación de PDS/Presupuestos desde una tratativa**: se usa el patrón de query param `?tratativa_id=X[&cliente_id=Y]`, idéntico al flujo "Generar PDS desde Llamadas" (`CrmLlamadas → CrmPedidosServicio`). Los controllers destino leen el query string, persisten la vinculación en el registro y, al terminar el store/update, redirigen de vuelta al detalle de la tratativa (`/mi-empresa/crm/tratativas/{id}`) en lugar del listado default.
- **Persistencia de filtros de listado**: heredada del componente `public/js/rxn-filter-persistence.js` cargado desde `admin_layout.php`. Los filtros (`search`, `field`, `limit`, `estado`, filtros Motor BD `f[]`) se persisten en `localStorage` scopeados por `pathname + empresa_id`. Para limpiarlos: `?reset_filters=1` o `window.rxnFilterPersistence.clear()`.
- **Vinculación opcional con cliente**: una tratativa puede existir sin cliente asignado (ej. una oportunidad inicial sin identificar al prospecto todavía). Si se asigna un `cliente_id`, debe existir en `crm_clientes` y pertenecer a la misma empresa.
- **Motivo de cierre obligatorio**: si el estado es `ganada` o `perdida`, el campo `motivo_cierre` es obligatorio (validado server-side).
- **Probabilidad**: entero 0-100, capado en el buildPayload del repositorio para evitar valores fuera de rango.
- **Geo-tracking en creación**: El evento `tratativa.created` se registra **únicamente en el INSERT exitoso** de la tratativa, no cuando se vinculan PDS/Presupuestos/Notas (esos ya registran sus propios eventos al crearse), no en cambios de estado, no en ediciones. El objetivo es capturar dónde estaba el comercial cuando abrió la oportunidad. La llamada ocurre **después** del commit del alta; una falla del servicio de geo no impide persistir la tratativa.

## Tipo de cambios permitidos
- Agregar columnas adicionales al encabezado de la tratativa (ej. fuente de origen, canal, etiquetas).
- Sumar filtros al listado (por rango de valor estimado, por responsable, por fecha).
- Agregar kanban visual en una fase futura (vista alternativa al listado tabular).
- ~~Conectar tratativas a CrmNotas para registrar historial de interacciones~~ ✅ Implementado (2026-04-15). Ver sección "Piezas principales" y "Dependencias directas".
- Proyectar eventos de tratativa en la futura `CrmAgenda` (fase 2 del plan del rey).

## Tipo de cambios sensibles
- Modificar la lógica de `forceDeleteByIds`: si se quita el paso de desvinculación previa de PDS/Presupuestos, se puede dejar FKs colgantes o, peor, romper los listados de PDS/Presupuestos que hacen JOIN contra `crm_tratativas`.
- Cambiar el ENUM de `estado` sin migración de datos compensatoria.
- Alterar el contrato del endpoint `suggestions()` (romperia el spotlight global).
- Modificar los query params aceptados por `CrmPedidosServicio` y `CrmPresupuestos` sin coordinar con esos módulos.

## Riesgos conocidos
- **Integridad blanda entre tablas**: la relación `crm_tratativas ↔ crm_pedidos_servicio/crm_presupuestos` es una FK soft (sin constraint duro en MySQL). Si alguien hace un `DELETE` manual sobre `crm_tratativas` salteando el repositorio, quedan registros huérfanos con `tratativa_id` apuntando a nada. Mitigación: SIEMPRE borrar vía `forceDeleteByIds` del repo.
- **Numero secuencial no estricto**: `numero` se calcula como `MAX(numero) + 1` con reintentos en caso de colisión por concurrencia. En escenarios de muy alta concurrencia puede reintentar hasta 3 veces. Si se necesita garantía absoluta, habría que migrar a una secuencia por empresa en tabla dedicada.

## Checklist post-cambio
- [ ] ABM completo: crear, editar, ver detalle, eliminar (papelera), restaurar, force-delete.
- [ ] Listado respeta `empresa_id`, muestra filtros F3 y contadores de PDS/Presupuestos asociados.
- [ ] Vista detalle muestra PDS, Presupuestos y Notas vinculados (vacías cuando no hay).
- [ ] Botones "Nuevo PDS", "Nuevo Presupuesto" y "Nueva Nota" redirigen con `?tratativa_id=X&cliente_id=Y` y vuelven correctamente al detalle al guardar.
- [ ] Endpoint `suggestions()` devuelve formato `['id','label','value','caption']`.
- [ ] Endpoint `clientSuggestions()` funciona con términos de búsqueda ≥ 2 caracteres.
- [ ] Validación server-side: título obligatorio, motivo de cierre obligatorio en ganada/perdida, probabilidad 0-100.
- [ ] forceDelete desvincula los PDS/Presupuestos/Notas antes de borrar la tratativa definitivamente.
- [ ] Crear una tratativa nueva genera una fila en `rxn_geo_eventos` con `event_type='tratativa.created'` y `entidad_id` igual al ID de la tratativa creada. Editar una tratativa existente NO genera evento nuevo. Una falla del servicio de geo no impide el alta.
