# MODULE_CONTEXT — RxnSync

## Nivel de criticidad
ALTO

Este módulo impacta directamente en:
- integraciones externas (Tango Connect)
- consistencia de datos CRM
- sincronización bidireccional

Cualquier cambio debe considerarse sensible.

## Propósito
El módulo **RxnSync** es el motor centralizado de sincronización bidireccional entre las entidades locales del entorno CRM y la API de Axioma/Nexo (Tango Connect). Su función es consolidar la vinculación de registros, permitir la auditoría de estado y ejecutar acciones de *Push* (enviar a Tango) y *Pull* (traer de Tango) para mantener la coherencia de los catálogos.

## Alcance
- **Sí hace**: Sincronización individual y masiva de `Clientes CRM` y `Artículos CRM`. Realiza la vinculación blanda ("Match Suave") basada en SKU o código, y conserva el historial de transacciones mediante un pivot.
- **No hace**: No sincroniza pedidos transaccionales ni configuraciones maestras. No realiza sincronización desatendida/automática (es *on demand* operada por el usuario). No sincroniza hacia el entorno de *Tiendas B2C/B2B*.

## Piezas principales
- **Controlador**: `app/modules/RxnSync/RxnSyncController.php` (Acciones ajax principales: `auditarArticulos`, `auditarClientes`, `pushToTango`, `pullSingle`, masivos, y visualización del payload).
- **Servicio**: `app/modules/RxnSync/RxnSyncService.php` (Contiene la lógica de negocio, mapeos a DTOs de Tango y conexión real a `TangoService`).
- **Vistas**: 
  - `app/modules/RxnSync/views/index.php` (Consola centralizada)
  - `app/modules/RxnSync/views/tabs/articulos.php` (Panel de artículos)
  - `app/modules/RxnSync/views/tabs/clientes.php` (Panel de clientes)
- **Persistencia involucrada**:
  - Locales: `crm_articulos`, `crm_clientes`
  - Pivot y Logs: `rxn_sync_status` (estado actual de sincronización), `rxn_sync_log` (historial de eventos).
- **Endpoints externos**: Usa internamente los procesos Connect `87` (Artículos) y `2117` (Clientes).

## Dependencias directas
- **TangoService / TangoApiClient**: Módulo base para instanciar la conexión remota y manejar las requests.
- **EmpresaConfigRepository**: fuente de precondiciones operativas para el circuito visual de sync (listas de precios y depósito).
- **Context / Database / View**: Core del framework.
- **docs/whitelist_definition.md**: Define los largos permitidos y qué campos mapean la entidad base.

## Dependencias indirectas / impacto lateral
- **Artículos CRM** y **Clientes CRM** dependen fuertemente de este módulo para sus flujos de sincronización individual en sus ABM correspondientes.
- La consola `RXN Sync` ahora también expone el estado de preparación del circuito de `Precios` y `Stock`, consumiendo configuración general para guiar al operador.
- La metadata poblada en `rxn_sync_status` impacta la visual en los listados del CRM si estos consumen dicha tabla para mostrar el "estado de sincronización".
- Un fallo en cómo "pisa" información (`pull`) puede truncar el trabajo hecho en el CRM en los ABM locales.

## Integraciones involucradas
- **Tango Connect**:
  - `Process 87` para GetById, Listado y Update de artículos.
  - `Process 2117` para GetById, Listado y Update de clientes.
- **Criterio Local-First vs Remoto**: La aplicación asume siempre los datos en las tablas `crm_*` para velocidad y sólo consulta Connect bajo demanda. Es un requerimiento preservar fallbacks si Remote no tiene datos.
- **Whitelist y Shadow Copy**: Para realizar un Push (ej: de un cliente), primero se hace un GetById (Pull indirecto) y sobre ese payload gigante (Shadow Copy) se sobreescriben sólo los campos permitidos y limitados en longitud de `whitelist_definition.md`. El objeto completo se devuelve a la API preservando nodos sistémicos (cuentas contables, percepciones, multi-direcciones).

## Reglas operativas del módulo
- **Shadow Copy Estricta**: Nunca enviar un JSON construido desde cero al endpoint de Update (PUT). Siempre es Leer primero -> Sobrescribir DTO local -> Mandar (Hydration update).
- **Match Suave**: Si un registro local no tiene `tango_id` en el pivot, el módulo intenta buscarlo por `codigo_externo` (artículo) o `codigo_tango` (cliente).
- **Time Limits Largos**: Los procesos masivos usan un override `set_time_limit(180)` y `120` debido a la latencia que presenta la API de Connect.

## Seguridad

### Aislamiento multiempresa
Todas las queries y operaciones de sync filtran por `empresa_id` del contexto de sesión. No existe lectura cruzada.

### Permisos / Guards
`RxnSyncController` usa `AuthService::requireLogin()` en todos los endpoints. No hay guard de admin — cualquier usuario autenticado del tenant puede ejecutar auditorías y push/pull individuales.

### Admin Sistema vs Tenant
No hay diferenciación. El módulo opera con las credenciales Tango configuradas para el tenant activo.

### Mutación por método
- Push, pull, auditorías masivas y todas las mutaciones de datos operan por **POST** (AJAX).
- El endpoint `getPayload()` opera por **GET** y es de solo lectura (muestra el payload que se enviaría a Tango sin ejecutarlo). No muta estado.

### Validación server-side
- Los payloads recibidos de Tango Connect se validan defensivamente.
- La whitelist de campos para Push protege los datos sistémicos del ERP (`whitelist_definition.md`).
- Los largos de campos se truncan con `mb_substr` antes de enviar a Connect.

### Escape / XSS
Los datos se almacenan en BD y se renderizan en los módulos consumidores. RxnSync no renderiza datos directamente al usuario salvo en la consola de auditoría (donde se aplica escape en las vistas).

### CSRF
No hay validación de token CSRF en los endpoints AJAX de auditoría, push o pull. Deuda de seguridad activa.

### Acceso local
Sin impacto directo. No se almacenan archivos en disco.

---

## No romper
- **Mecanismo de Shadow Copy**: La protección de campos read-only de Tango Connect (se puede desestabilizar la información fiscal de un cliente en el ERP).
- **Mapeos parciales por mb_substr**: Los campos inyectados hacia Tango tienen largos estrictos mapeados en `RxnSyncService::pushToTangoByLocalId` que no deben borrarse ni extenderse.
- **Aislamiento por `empresa_id`**: Nunca hacer queries u offsets sin filtrar por el contexto multitenant (`empresa_id`).

## Riesgos conocidos
- *Límite de Paginación en Match Suave*: El Match Suave (`resolveTangoIdBySku`) ya no quedó atado sólo a la primera página: recorre `pageIndex` desde `0` hasta `10` con `pageSize = 500`, por lo que el alcance efectivo sigue acotado a 11 páginas como máximo. Catálogos muy grandes podrían seguir sin resolverse si el registro buscado cae fuera de ese techo duro.
- *Auditorías Masivas Limitadas a Primera Página*: `auditarArticulos()` y `auditarClientes()` todavía consumen una sola página remota (`pageSize = 500` sin iterar `pageIndex`). En tenants con más de 500 artículos o clientes en Tango, la auditoría masiva puede seguir marcando falsos pendientes aunque el Pull individual luego sí consiga resolverlos.
- *Timeouts en Masivos*: Un request masivo de muchos registros en un backend remoto (ej: hospedaje normal) podría llegar a dropear el request antes de agotar los arreglos a Tango si hay mala calidad de red, a pesar del `set_time_limit()`.

## Checklist post-cambio
- [ ] Ejecutar prueba de "Push individual" a entidad existente para confirmar que el array JSON enviado contiene la metadata completa subyacente y no explota.
- [ ] Validar que la ejecución asíncrona (AJAX) devuelva JSON correcto y no Warnings PHP escondidos por causa de librerías cambiadas de `TangoApiClient`.
- [ ] Probar "Auditoría de Artículos/Clientes" para re-validar el Pivot suave con un entorno que no tenga los ID cargados.

## Documentación relacionada
- `docs/whitelist_definition.md`
- `docs/architecture.md` (Patrón Local-First)
- `docs/estado/current.md` (Registros recientes de evolución multitenant CRM)

## Tipo de cambios permitidos

- Ajustes de UI (bajo riesgo)
- Ajustes de logging o auditoría
- Correcciones puntuales de mapeo (validadas)

## Tipo de cambios sensibles

- Modificación de payload hacia Tango
- Cambios en lógica de Shadow Copy
- Cambios en Match Suave
- Cambios en paginación o lookup remoto
