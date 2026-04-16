# 2026-04-09 14:25 - RXN Sync: circuito Artículos -> Precios/Stock y fixes del tab Artículos

## Cambios realizados

- `app/modules/RxnSync/RxnSyncController.php`
  - La consola ahora calcula un estado de circuito usando `EmpresaConfigRepository` + cantidad de artículos vinculados.
- `app/modules/RxnSync/views/index.php`
  - Se agregó un bloque compacto de estado operativo que expresa el flujo `Artículos -> Precios -> Stock`.
  - `Sync Precios` y `Sync Stock` quedan visualmente condicionados por artículos vinculados y por los selectores de Configuración (`lista_precio_1`, `lista_precio_2`, `deposito_codigo`).
  - Se endureció el JS del parent para que el botón `i` del tab funcione vía event delegation.
  - Se aisló mejor la selección masiva al tab activo.
- `app/modules/RxnSync/views/tabs/articulos.php`
  - El checkbox `select all` pasó a usar un id específico del tab.
- `app/modules/RxnSync/views/tabs/clientes.php`
  - El checkbox `select all` pasó a usar un id específico del tab.
- `app/modules/RxnSync/MODULE_CONTEXT.md`
  - Se documentó que el módulo ahora consume `EmpresaConfig` como precondición visual del circuito de sync.

## Criterio funcional aplicado

El circuito correcto del dominio quedó expresado así:

1. **Artículos**: primero debe existir vínculo local-remoto.
2. **Precios**: sólo tiene sentido si ya existe el artículo y si está configurada al menos una lista de precios.
3. **Stock**: sólo tiene sentido si ya existe el artículo y si está configurado el depósito.

No se trató como una mejora cosmética sino como una restricción operativa real del sistema.

## Seguridad base revisada

- Multiempresa: el estado del circuito se calcula por `Context::getEmpresaId()` y configuración por área.
- Permisos backend: sin ampliar superficie; se reutilizan rutas ya existentes protegidas.
- Admin sistema vs tenant: sin cambios de alcance.
- No mutación por GET: las acciones de sync siguen en sus rutas existentes; el bloque nuevo es informativo/navegacional.
- Validación server-side: sin debilitar controles; el cambio es de guía operativa y binding JS.
- Escape/XSS: los payloads siguen escapando `<` y `>` antes de renderizar JSON en modal.
- Impacto sobre acceso local del sistema: nulo.
- CSRF: no se incorporó token nuevo; no se abrió superficie adicional distinta al patrón actual.

## Nota de delegación

Se delegó primero la implementación a `Gemi` con el criterio funcional del rey ya explicitado. La corrida volvió a completar sin respuesta textual utilizable, por lo que Lumi aplicó el fallback mínimo local para materializar el circuito y no dejar el módulo a mitad de camino.
