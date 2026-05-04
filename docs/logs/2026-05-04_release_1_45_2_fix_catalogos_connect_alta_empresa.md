# Release 1.45.2 — Fix carga de catálogos Connect en alta de empresa nueva

**Fecha**: 2026-05-04
**Build**: 20260504.1
**Iteración**: 47

## Tema

Bug de UX en `Configuración general` (CRM y Tiendas) cuando se da de alta una empresa nueva: el selector "ID de Empresa (Connect)" quedaba vacío y el banner amarillo de "Diagnóstico Connect" se encendía marcando catálogos VACÍO al pedo.

## Root cause

`EmpresaConfigController::buildTangoClientFromPost()` cae a `Company: -1` cuando `tango_connect_company_id` está vacío (alta nueva). Esto es CORRECTO solo para process=1418 (maestro Empresas) — el resto de los catálogos dependen de un Company real:

| Process | Catálogo | Soporta Company=-1 |
|---|---|---|
| 1418 | Empresas | ✅ Sí (es el caso de uso) |
| 984 | Listas de precio | ❌ No → items=0 |
| 2941 | Depósitos | ❌ No → items=0 |
| 20020 | Perfiles de pedido | ❌ No → items=0 |

Sin embargo `loadTangoMetadata()` disparaba los 4 fetches en paralelo en la primera carga. Los 3 dependientes respondían HTTP 200 con `items=0` que la lógica del frontend interpretaba como `outcome != 'ok'` y encendía el banner amarillo. Además la concurrencia paralela contra Connect dejaba colgada la respuesta de empresas — por eso el dropdown nunca se llenaba.

## Qué se hizo

Fix en tres capas (estrategia "cliente educado + servidor honesto + UI tolerante"):

### A — Frontend: skip de catálogos hijos sin company
- `loadTangoMetadata()` lee `tango_connect_company_id` del DOM antes de armar la lista de tasks.
- Si no hay company resuelto → solo dispara `tango-empresas`.
- El listener `change` del select de empresa (que ya existía desde 1.12.x) re-dispara `loadTangoMetadata` cuando el usuario elige empresa, así que la segunda corrida resuelve listas/depósitos/perfiles con companyId real.
- Hint del progreso refleja el estado: "Empresas cargadas. Seleccioná la empresa Connect para resolver listas, depósitos y perfiles."

### B — Backend: short-circuit estructural
- `hasResolvedCompanyId()` — chequea POST + config; vacío o `-1` → false.
- `pendingCompanyDiagnostic()` — diagnostic estructural con `outcome: 'pending_company'`.
- `getTangoListas` / `getTangoDepositos` / `getTangoPerfiles` → si no hay company, devuelven `success:true, data:[]` con el diagnostic pending. Nunca más viajan a Connect con Company=-1 para process que no lo soportan.

### C — UI tolerante: filtro del banner
- `renderTangoDiagnosticPanel` filtra `outcome === 'pending_company'` para que NO encienda el banner amarillo. Es estado intermedio esperado durante el alta, no anomalía.

### Yapa — botón colgado
El botón "Validar y cargar metadata" quedaba pegado en "Conectado — cargando catálogos..." aunque las promises hubieran terminado, porque el `finally` no restauraba el texto si la clase era `btn-success`. Ahora pasa a `✅ Conectado` cuando termina.

## Archivos tocados

- `app/modules/EmpresaConfig/EmpresaConfigController.php` — helpers `hasResolvedCompanyId` + `pendingCompanyDiagnostic`, short-circuit en 3 endpoints atómicos.
- `app/modules/EmpresaConfig/views/index.php` — `loadTangoMetadata` con tasks dinámicas según companyId, filtro de `pending_company` en el banner, `finally` que restaura texto del botón.
- `app/config/version.php` — bump a 1.45.2 / 20260504.1.

## Por qué

Charly intentaba dar de alta una empresa nueva en Configuración general y reportó:
- "El selector de empresas nunca se llena".
- Banner amarillo encendido marcando Depósitos y Listas como VACÍO.
- "Diagnóstico crudo" sí mostraba la respuesta correcta de process=1418 con 55 empresas.

La pista clave era que el diagnóstico crudo (que solo llama a 1418 con Company=-1) andaba perfecto, mientras los catálogos paralelos fallaban. Eso descartó credenciales/perfil y apuntó al orden de operaciones.

## Impacto

- Alta de empresa nueva en CRM y Tiendas: flujo limpio de 2 pasos (validar → elegir empresa → catálogos llenos).
- Banner amarillo solo aparece para anomalías reales (shape distinto, error de credenciales, HTTP error).
- Dropdown de empresas se llena al primer click de Validar.
- Cero requests inútiles a Connect.

## Validación

- Probado por Charly antes del OTA en local: select se pobla con 55 empresas, sin banner falso positivo.
- Tras elegir empresa en el dropdown, los 3 catálogos hijos (Listas/Depósitos/Perfiles) cargan automáticamente.

## Pendiente

Nada de esta release. Charly dijo "se viene un poco pesadito" — sesión sigue abierta.

## Decisiones tomadas

- **Outcome semántico nuevo: `pending_company`** vs reusar `empty` o `error`. Conviene distinguir "el endpoint no se llamó porque falta contexto" de "el endpoint corrió y vino vacío" — sirve para futuras métricas y debugging.
- **Short-circuit en backend además del skip en frontend**: defensa en profundidad. Si alguien llama directo al endpoint (curl, future RxnSync, etc.) sin company, el backend igual responde claro en lugar de molestar a Connect con Company=-1.
- **No tocar `getTangoEmpresas`**: ese endpoint SÍ soporta Company=-1 — es su caso de uso.

## Env vars nuevas

Ninguna.
