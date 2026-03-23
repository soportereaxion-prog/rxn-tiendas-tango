# Artículos — Sincronización Dual de Listas de Precios

## Contexto
El módulo transaccional de Artículos requería nutrir de sus propios Precios a los productos sincronizados desde Connect, manteniendo la premisa arquitectónica de no disgregarlos en nuevos módulos dependientes (Como un Módulo Precios satélite).

## Problema
El maestro de artículos (process=87) viene separado ontológicamente de la grilla de listas de precios (process=20091) en Axoft. Se debían empalmar por métricas referenciales (SKU / NroLista).

## Decisión
- **Configuración Dual**: Se adoptó que en EmpresaConfig.php se declararan explícitamente `lista_precio_1` y `lista_precio_2`.
- **Integridad Física**: Almacenar L1 y L2 bajo la misma tupla del catálogo `articulos` (precio_lista_1, precio_lista_2) para lectura atómica en CRUDs.
- **Sincronización Pura**: El Sync de precios jamás generará entidades `Articulo` vírgenes ni huérfanas, exclusivamente orquesta Update Queries contra Skus de Catálogo (`COD_STA11` matches `codigo_externo`) que concuerden con las Listas referidas.

## Archivos afectados
- `app/config/routes.php`
- `app/Modules/Tango/Controllers/TangoSyncController.php`
- `app/Modules/Tango/Services/TangoSyncService.php`
- `app/Modules/Tango/TangoService.php` y `TangoApiClient.php`
- `app/Modules/Articulos/ArticuloRepository.php` y `ArticuloController.php`
- `app/modules/Articulos/views/index.php` y `form.php`
- `app/Modules/EmpresaConfig/*`

## Implementación
1. **DB Alter**: Se inyectaron `lista_precio_1, 2` en configs y `precio_lista_1, 2` en articulos.
2. **Settings**: Inputs UX para setear en el frontend los `NRO_DE_LIS` apuntados.
3. **Connect API**: Prober legitimado arrojó el uso de `process=20091`.
4. **Endpoint Macheo**: `/mi-empresa/sync/precios` transfiere los Items al service, donde un bucle filtra solo listas objetivo y forja el UPSERT (via Update puro sin Duplicados) delegando feedback visual a FlashToasts y redirección UX al index.

## Impacto
El catálogo de productos en Backoffice sube jerárquicamente. Expone la Sincronización por Separado pudiendo mutar métricas variables (precios) o estables (catalogo). Redirige ágilmente al panel frontal.

## Riesgos
Si el endpoint arroja 80,000 prices paginados, el Timeout de PHP limitaría el consumo dado que la ejecución es procedural y local. Eventualmente el Batch requerirá transmutar Tareas Asincrónicas (Cron).

## Validación
- Credenciales operativas provistas en vuelo por la UI.
- Mapeado contra Payload Estricto. Funciona el update manual e informacional de la grilla.
