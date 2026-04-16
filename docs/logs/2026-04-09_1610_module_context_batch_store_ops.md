# Control de Cambio Documental: MODULE_CONTEXT Batch (Store & Operación Comercial)
**Fecha:** 2026-04-09 16:10

## Resumen de la Intervención
Se generaron y consolidaron los `MODULE_CONTEXT.md` del bloque orientado a catálogo, clientes B2C, pedidos web y Store público. La tanda se completó por fallback operativo de `clau-direct` luego de una falla externa de permisos en `gemi-direct`, y quedó auditada por Lumi contra el código real.

## Módulos Cubiertos
1. **Categorias:** catálogo taxonómico del Store y dependencia de `Articulos`.
2. **Clientes:** módulo stub; la operación real está absorbida hoy por `ClientesWeb`.
3. **ClientesWeb:** backoffice de clientes B2C + auth pública del Store + lookup Tango comercial.
4. **Pedidos:** pedidos web y persistencia transaccional de checkout.
5. **Productos:** módulo stub; la operación real está absorbida hoy por `Articulos`.
6. **Store:** vitrina pública, carrito, checkout y "Mis Pedidos" por slug de empresa.

## Controles de Seguridad Revisados y Documentados
- **Aislamiento Multiempresa:** documentado vía `Context::getEmpresaId()` en backoffice y vía `PublicStoreContext` / `ClienteWebContext` en el Store público.
- **Permisos y Guards:** `Categorias`, `ClientesWeb` y `Pedidos` usan sesión autenticada de backoffice; `Store` es público por diseño y sólo protege las secciones privadas del comprador con contexto de cliente web.
- **No mutación por GET:** asentado en CRUDs y checkout; se dejó explícita la excepción real de `logout` por GET en el Store.
- **Validación server-side:** documentadas las validaciones observables de stock, datos de checkout, deduplicación de cliente y resolución de tienda activa.
- **Escape/XSS:** se dejó asentado dónde el escape es esperable y dónde quedó pendiente revisión fina de vistas.
- **CSRF:** se documentó como deuda activa en los flujos públicos/backoffice donde no apareció validación explícita en el código inspeccionado.

## Hallazgos Relevantes
- `Clientes` y `Productos` son placeholders estructurales. No deben confundirse con los módulos vivos `ClientesWeb` y `Articulos`.
- `Store` expone superficie pública real por slug (`/{slug}`), con carrito y checkout sin evidencia de CSRF explícito.
- `ClienteAuthController::logout()` opera por GET y cierra sesión, lo que quedó asentado como excepción sensible.
- `ClientesWeb` concentra más responsabilidad de la que su nombre sugiere: además del ABM B2C, absorbe login/registro/reset del Store y lookup comercial hacia Tango.

## Estado
Documentación creada y auditada sin tocar lógica ejecutiva.
