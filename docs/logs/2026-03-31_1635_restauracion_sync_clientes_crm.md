# Restauración de Sincronización de Clientes CRM

**Fecha:** 2026-03-31 16:35
**Módulo:** Tango / CrmClientes
**Responsable:** Agente

## Descripción
El usuario reportó que tras un `git reset` problemático, el botón y proceso de **"Client Sync"** dejó de funcionar en el módulo de Clientes CRM.

## Análisis y Ejecución
Se constató la ausencia absoluta de los métodos y mappers que extraían información del Process 2117 de la API hacia la tabla local de `crm_clientes`. Se procedió a regenerar las capas afectadas:

1. **`TangoApiClient`**: Implementado `getClientes()` para recuperar `process=2117`.
2. **`TangoService`**: Implementado `fetchClientes()` devolviendo `TangoResponseDTO`.
3. **`CrmClienteMapper`**: Creado desde cero `fromConnectJson()` para mapear los IDs, Código, Razón social, emails y metadatos complementarios (Condición venta, listas, vendedores).
4. **`TangoSyncService`**: Implementado el orquestador `syncClientes()` que recupera el payload paginado, lo pasa por el mapper e invoca a `CrmClienteRepository->upsertFromTango()`.
5. **`TangoSyncController`**: Expuesto método `syncClientes()`.
6. **`routes.php`**: Rehabilitada la ruta `GET /mi-empresa/crm/sync/clientes`.

Con esto el circuito de botones UI ha recuperado el anclaje y está activo.
