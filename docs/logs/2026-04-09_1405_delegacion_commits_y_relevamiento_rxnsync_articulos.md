# 2026-04-09 14:05 - Delegación de commits a Gemi y relevamiento RXN Sync Artículos

## Cambios documentales

- `AGENTS.md`
  - Se explicitó que los commits y acciones de versionado solicitadas por el rey también deben delegarse prioritariamente a `Gemi`.
- `C:\Users\charl\.config\opencode\agents\gemi.md`
  - Se explicitó que, si Lumi delega un commit/versionado, `Gemi` debe ejecutarlo como vía preferente.
- `C:\Users\charl\.config\opencode\agents\lumi.md`
  - Se explicitó que Lumi también debe delegar commits/versionado a `Gemi` y usar fallback propio sólo si falla.

## Relevamiento de bugs en RXN Sync → Artículos

- `Select all` del tab Artículos:
  - Root cause probable: ambos tabs (`clientes.php` y `articulos.php`) comparten el mismo `id="rxnsync-select-all"`.
  - `RxnSync/views/index.php` usa `document.getElementById('rxnsync-select-all')`, por lo que puede terminar rebindeando el checkbox del tab oculto y no el del tab activo.
- Botón `i` del tab Artículos:
  - Root cause evidente: la vista padre `RxnSync/views/index.php` tiene event delegation para `.btn-push-tango` y `.btn-pull-tango`, pero no tiene handler para `.btn-payload-info`.
  - El HTML del botón existe en `views/tabs/articulos.php`, pero no hay lógica activa que lo atienda desde el parent JS.

## Lectura arquitectónica breve

El módulo `RXN Sync` está mejor parado que antes: ya consolidó la lógica JS en la vista padre, tiene filtros/paginación/sorts, trazabilidad visual y feedback enriquecido. Pero sigue frágil en dos puntos clásicos de front embebido:

- dependencia a `id`s globales reutilizados entre tabs parciales;
- crecimiento del event delegation por agregados puntuales, donde un botón nuevo puede quedar visible pero sin cerebro asociado.

## Nota de delegación

Se pidió a `Gemi` un diagnóstico del tab Artículos y una opinión sobre el módulo. La delegación volvió a completar sin respuesta textual útil, por lo que Lumi realizó el relevamiento local para no dejar ciego al rey y deja asentado el patrón de fallback.
