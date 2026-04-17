# Release 1.12.3 — z-index de dropdowns + auto-llenado de Clasificaciones PDS

**Fecha y tema**: 2026-04-16 19:45 — Pulido operativo sobre la seccion Tango Connect de la configuracion. Dos bugs menores pero irritantes en el dia a dia.

## Qué se hizo

### Fix 1 — Z-index de sugerencias del selector de empresa

- `app/modules/EmpresaConfig/views/index.php`:
  - `applyLocalSearchPattern()`: el wrapper arranca con `z-index: 1` (antes `999`).
  - Al hacer `focus` sobre el input interno, el wrapper sube a `1060` (por encima del stacking default de Bootstrap forms).
  - Al `blur`, vuelve a `1`.
  - Se consolidaron dos listeners de `focus` sobre el mismo input en uno solo — antes habia uno preexistente (`renderSuggestions('')`) y uno que agregue en esta misma iteracion para el z-index. Ahora es un solo listener.

**Resultado**: el dropdown del selector activo SIEMPRE queda arriba, sin importar el orden del DOM. Antes, los wrappers de `Lista Precios 2` y `Deposito` (que vienen despues en el grid) tapaban al dropdown de `Empresa`.

### Fix 2 — Auto-llenado del textarea "Catálogo Local de Clasificaciones PDS"

- `app/modules/EmpresaConfig/EmpresaConfigController.php`:
  - Nuevo metodo `getTangoClasificaciones()` que invoca `TangoApiClient::getClasificacionesPds()` (process 326) y normaliza el shape a `[{codigo, descripcion}]`.
  - Usa el mismo `envelopeCatalogResponse()` que los otros 4 endpoints — tambien devuelve `diagnostic` para el banner si falla.

- `app/config/routes.php`:
  - Nuevas rutas POST `/mi-empresa/configuracion/tango-clasificaciones` y `/mi-empresa/crm/configuracion/tango-clasificaciones` con guards `requireTiendas` y `requireCrm`.

- `app/modules/EmpresaConfig/views/index.php`:
  - `loadTangoMetadata()` ahora dispara **5** fetches paralelos (antes 4). El contador del hint pasa de `X/4` a `X/5`.
  - Nueva funcion `populateClasificacionesPds(items)` que:
    - Transforma `[{codigo, descripcion}]` a lineas planas `CODIGO descripcion`.
    - Setea el textarea `#clasificaciones_pds_raw`.
    - **Solo sobrescribe si el textarea esta vacio** — para no pisar configs manuales del operador.
  - El `placeholder` del textarea dejo de ser JSON y pasa a mostrar lineas planas de ejemplo:
    ```
    ASETGO Asesoramiento Tango
    TRAT01 Tratamiento base
    ```
  - El hint explica el formato esperado y como forzar re-sync (limpiar textarea + reapretar Validar Conexion).
  - `rows=3` → `rows=4` para mejor visibilidad.

## Por qué

### Z-index

UX historico. El wrapper de busqueda local arrancaba con `z-index: 999` hardcodeado, y el dropdown de sugerencias (adentro del wrapper) tenia `z-index: 1050`. Pero el z-index del suggestions se evalua en el stacking context del wrapper padre, asi que si un wrapper VECINO (siguiente en el DOM) tambien tenia `999`, tapaba el suggestions del anterior. Nunca molesto porque es una pantalla de configuracion que se toca una vez por empresa — pero queda prolijo.

La solucion clasica de "subir el activo, bajar el resto" es mas robusta que malabares con stacking contexts.

### Clasificaciones PDS

Hay un mecanismo escondido de pre-cache que arranco en `docs/logs/2026-03-28_2045_clasificaciones_pds_desde_config_crm.md` y paso por el refactor de `docs/logs/2026-03-30_0858_refactor_catalogo_clasificaciones_pds.md`. El catalogo se esperaba poblar via `TangoApiClient::getClasificacionesPds()` (process 326) pero el unico disparador era `UsuarioController::fetchTangoProfile()` — que es la accion que un admin dispara al asignar perfil Tango a un usuario. Dos problemas:

1. **Invisible desde Config**: nadie sabe que hay que asignar perfil a un usuario para que el textarea se llene.
2. **Shape roto**: `UsuarioController:137` guarda `json_encode($items, JSON_UNESCAPED_UNICODE)` — o sea el raw crudo de Axoft (con keys `ID_GVA81`, `COD_GVA81`, `DESCRIP`, etc.) directamente al textarea. Pero `ClasificacionCatalogService::parseRaw()` espera lineas planas con regex `/^(\S+)\s+(.+)$/`. Resultado: aun cuando el mecanismo escondido se ejecutaba, el Service no encontraba entries y el selector del PDS quedaba vacio.

Fix minimalista: dar al operador una forma obvia de llenar el textarea desde Config (como cualquier otro catalogo Connect) y en el shape correcto. El flujo de UsuarioController queda intacto — es tech debt documentado para otra iteracion.

## Impacto

- **UI**: las sugerencias del selector de empresa ya no quedan tapadas por vecinos. Sin regresiones — el resto del tiempo los wrappers estan en `z-index: 1` que es lo mismo que el stacking default de Bootstrap.
- **Clasificaciones PDS**: al apretar "Validar Conexion" con un textarea vacio, las clasificaciones de Tango se vuelcan automaticamente en shape listo para `ClasificacionCatalogService`. El selector del PDS (y Presupuestos) puede usarlas de inmediato sin demoras de la API externa.
- **Respeto de configs manuales**: si el textarea ya tiene contenido (edicion manual o carga previa), NO se sobrescribe. El auto-llenado solo aplica cuando esta vacio.
- **Diagnostico unificado**: si process 326 falla o devuelve vacio, el banner de diagnostico (release 1.12.2) lo muestra igual que los otros catalogos. Un solo lugar para ver todo.

## Decisiones tomadas

- **No se toca `UsuarioController::fetchTangoProfile()`** aunque tiene el bug de shape. Razones: (a) es caller externo con su propia responsabilidad — asignar perfil a usuario; (b) cambiar el shape ahi podria romper otro consumer no explorado; (c) ahora desde Config hay un camino limpio para autollenar en shape correcto. Queda registrado como tech debt.
- **No se elimino el textarea manual**. Sigue siendo editable. El auto-llenado es un complemento, no un reemplazo. Algunos operadores podrian querer un catalogo curado en vez del completo de Tango.
- **El 5to fetch es paralelo, no secuencial**. Agrega ~2-3s al peor caso de latencia de Validar Conexion, pero como los 5 son paralelos el wall time no sube significativamente.
- **No hay boton explicito "Refrescar clasificaciones"**. La interfaz de "vaciar textarea + reapretar Validar" es menos limpia que un boton — pero un boton dedicado ensucia la UI y el 99% del tiempo no se usa. Si molesta en la practica se puede agregar.

## Validación

- Smoke test local del z-index: se apreta el input de `Empresa` con los tres otros selectores ya poblados y con valor. El dropdown aparece por encima. Al cambiar a `Lista Precios 2`, SU dropdown aparece por encima del resto. Sin regresiones.
- Smoke test del auto-llenado: textarea vacio + Validar Conexion → se llena con lineas planas `CODIGO descripcion`. Reapretar con textarea lleno → no sobrescribe. Vaciar + reapretar → se vuelve a llenar con el catalogo fresco.
- Smoke test de falla: endpoint `/tango-clasificaciones` con URL invalida → banner de diagnostico aparece con outcome `error` y el mensaje de la excepcion.

## Pendiente

- **Tech debt conocido**: `UsuarioController::fetchTangoProfile():137` sigue guardando JSON crudo al textarea `clasificaciones_pds_raw` — ese flujo se queda con shape incompatible. Si algun dia queremos que ese disparador tambien sirva, hay que rutear por el mismo normalizador que usa `populateClasificacionesPds` en el frontend (o un equivalente PHP del lado del Controller). No urgente — se puede disparar el flujo correcto desde Config.
