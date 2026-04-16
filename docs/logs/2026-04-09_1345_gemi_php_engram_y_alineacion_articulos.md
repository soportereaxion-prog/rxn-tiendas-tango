# 2026-04-09 13:45 - Gemi con PHP/Engram explícitos y alineación de Artículos con Clientes

## Cambios realizados

- `C:\Users\charl\.config\opencode\agents\gemi.md`
  - Se explicitó que `Gemi` debe consultar Engram (`mem_context` / `mem_search`) cuando Lumi refiera contexto previo.
  - Se explicitó que Engram debe asumirse disponible para recuperar y persistir hallazgos.
  - Se registró la ruta PHP CLI de este entorno: `D:\RXNAPP\3.3\bin\php\php8.3.14\php.exe`.
- `app/modules/Articulos/views/form.php`
  - Se alineó el form con el patrón endurecido de `CrmClientes`.
  - `Push` ahora muestra `Payload enviado` + `Respuesta API` cuando aplica.
  - `Pull` usa parseo defensivo y refresca el campo `nombre` en caliente cuando vuelve `local_actualizado`.
  - El botón `i` usa fetch defensivo con `X-Requested-With`.
- `app/modules/Articulos/views/index.php`
  - Se alineó el CRUD con el mismo parseo defensivo y el mismo detalle enriquecido de Push/Pull.

## Contexto relevado de la iteración previa en Artículos

Se tomó como base `docs/logs/2026-04-08_1219_fix-form-info-embudo-rxnsync.md`, donde ya había quedado resuelto que el form de Artículos debía usar el endpoint correcto de Push (`/articulos/{id}/push-tango`) y que el botón `i` del form era parte del circuito correcto de auditoría.

La diferencia restante frente a `CrmClientes` era de robustez y feedback:

- Artículos seguía parseando `r.json()` en crudo.
- Artículos no mostraba `Respuesta API` en Push.
- Artículos no aprovechaba `local_actualizado` tras Pull para refrescar el formulario abierto.

## Seguridad base revisada

- Multiempresa: sin cambios de backend; se conserva el aislamiento existente por contexto y rutas del módulo.
- Permisos backend: sin ampliación de superficie; sólo se endureció el JS consumidor.
- Admin sistema vs tenant: sin cambios de alcance.
- No mutación por GET: Push/Pull siguen usando `POST`; `payload` sigue siendo lectura `GET`.
- Validación server-side: sin cambios funcionales de backend en esta iteración.
- Escape/XSS: los JSON embebidos continúan escapando `<` y `>` antes de renderizarse en modal HTML.
- Impacto sobre acceso local del sistema: nulo.
- CSRF: no se agregó token nuevo; no se introduce una deuda nueva, se mantiene el patrón vigente del stack.

## Nota de delegación

Se delegó primero la implementación a `Gemi` con contexto explícito de trazabilidad y archivos de referencia. La corrida completó sin devolver respuesta textual utilizable, por lo que Lumi aplicó el ajuste mínimo local permitido por la regla de fallback ya documentada en `AGENTS.md`.
