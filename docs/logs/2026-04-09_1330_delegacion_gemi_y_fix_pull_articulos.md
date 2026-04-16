# 2026-04-09 13:30 - Delegación a Gemi como preferencia y fix Pull de Artículos CRM

## Qué cambió

- `AGENTS.md`
  - Se documentó la preferencia operativa del rey: Lumi debe priorizar delegación a `Gemi` para implementaciones de código, quedando como orquestadora/verificadora principal.
  - Se explicitó el fallback: si `Gemi` no devuelve respuesta útil o la delegación falla, Lumi puede aplicar el ajuste mínimo local para destrabar.
- `app/modules/RxnSync/RxnSyncService.php`
  - `pullFromTangoByLocalId()` ya no depende exclusivamente de un `tango_id` preexistente en `rxn_sync_status`.
  - Si el pivot no tiene vínculo, ahora intenta resolver el registro por `codigo_externo`/`codigo_tango` usando el mismo match suave del Push.
  - `resolveTangoIdBySku()` dejó de mirar sólo la primera página y ahora pagina varias hojas de Connect para evitar falsos pendientes en catálogos grandes.

## Causa raíz del bug en Artículos

El Pull individual de artículos estaba atado a una precondición demasiado rígida: exigía que `rxn_sync_status` ya tuviera `tango_id` cargado. Si la auditoría previa quedaba en `pendiente` por no encontrar el SKU en la primera página de Tango, el Pull fallaba aunque el artículo sí existiera en páginas posteriores.

## Seguridad base revisada

- Multiempresa: se conserva el filtro por `empresa_id` en lookup local y pivot.
- Permisos backend: sin cambios, sigue protegido por controladores/rutas existentes.
- Admin sistema vs tenant: sin ampliar alcance; el cambio sigue dentro del contexto operativo CRM.
- No mutación por GET: el Pull sigue siendo `POST`.
- Validación server-side: se valida existencia local antes de buscar en Tango y se informa error claro si no hay match.
- Escape/XSS: sin cambios de superficie en salida HTML.
- Impacto sobre acceso local del sistema: nulo.
- CSRF: no se agregó token nuevo; queda como deuda transversal del stack, no introducida por este fix puntual.

## Nota de delegación

Se intentó primero una delegación a `Gemi`, pero la corrida no devolvió respuesta útil en texto. Por instrucción explícita del rey, Lumi aplicó el ajuste mínimo local para no bloquear la prueba y dejó esta preferencia asentada en el repositorio.
