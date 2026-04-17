# Release 1.12.5 — Sync Catálogos centralizado en RxnSync + form bifurcado CRM/Tiendas

**Fecha y tema**: 2026-04-16 22:30 — Arquitectura RxnSync + EmpresaConfig + CrmPresupuestos. Iteración para alinear el flujo de sincronización con el criterio operativo de cada área.

## Qué se hizo

### Fase 1 — Mover CommercialCatalogSyncService a RxnSync

- `app/modules/RxnSync/Services/CommercialCatalogSyncService.php` **NUEVO**: copia del servicio con namespace `App\Modules\RxnSync\Services`. Misma lógica: `ClienteTangoLookupService->getRelacionCatalogs()` + `TangoApiClient->getMaestroDepositos()` + `CommercialCatalogRepository->upsertMany()` para los 5 tipos (condicion_venta, lista_precio, vendedor, transporte, deposito).
- `app/modules/CrmPresupuestos/CommercialCatalogSyncService.php` **ELIMINADO**.
- `app/modules/CrmPresupuestos/PresupuestoController.php`: `use` cambiado a `App\Modules\RxnSync\Services\CommercialCatalogSyncService`. El auto-trigger defensivo en `loadCatalogData()` sigue funcionando.

**Nota**: `CommercialCatalogRepository` QUEDA en `CrmPresupuestos` porque el form lo consume directo (`findAllByType`, `findOption`, `findFirstByType`). No tiene sentido moverlo.

### Fase 2 — Endpoint y ruta nuevos en RxnSync

- `app/modules/RxnSync/RxnSyncController.php`: nuevo método público `syncCatalogos()` (AJAX POST). Invoca el service y devuelve JSON con stats consolidados por tipo.
- `app/config/routes.php`:
  - Nueva: `POST /mi-empresa/crm/rxn-sync/sync-catalogos` con guard `requireCrm`.
  - Eliminada: `POST /mi-empresa/crm/presupuestos/catalogos/sincronizar`.

### Fase 3 — Limpiar Presupuestos

- `app/modules/CrmPresupuestos/PresupuestoController.php`:
  - Método público `syncCatalogs()` (screen-based con redirect+Flash) **eliminado** completamente.
  - `syncCatalogosPath` del array de view context ahora apunta a `/mi-empresa/crm/rxn-sync`.
- `app/modules/CrmPresupuestos/views/index.php`:
  - Botón "Sync Catalogos" (btn-outline-warning) transformado en "Ir a RxnSync" (btn-outline-info), link GET al listado de la consola.

### Fase 4 — Refactor Sync Stock para CRM

- `app/modules/Tango/Services/TangoSyncService.php::syncStockWithConfig()`:
  - En CRM, `deposito_codigo` es **opcional** — si viene null/vacío ya no tira excepción.
  - El loop sigue poblando `crm_articulo_stocks` para todos los depósitos via `upsertArticuloStock()`.
  - El branch de `stock_actual` plano sigue existiendo pero solo ejecuta si el depósito del item coincide con `config->deposito_codigo` (que en CRM está vacío → nunca matchea → correcto).
  - En Tiendas sigue exigiéndolo (es depósito de referencia para `stock_actual` plano).

### Fase 5 — Precondiciones CRM en RxnSync

- `app/modules/RxnSync/RxnSyncController.php::index()`:
  - En CRM: `listasReady = countByType(empresaId, 'lista_precio') > 0` y `depositoReady = countByType(empresaId, 'deposito') > 0`.
  - Agregado flag `catalogos_ready = !empty(tango_connect_token)` para habilitar el botón "Sync Catálogos" apenas hay credenciales.
  - `syncCircuit` array expone `area`, `catalogos_ready`, `sync_catalogos_path`.

### Fase 6 — UI EmpresaConfig

- `app/modules/EmpresaConfig/views/index.php`:
  - Los 3 selectores `lista_precio_1`, `lista_precio_2`, `deposito_codigo` se **ocultan en área CRM** con `<?php if (!(isset($area) && $area === 'crm')): ?>`.
  - En su lugar se renderiza un `<div class="alert alert-info">` informativo explicando que los catálogos se sincronizan desde RxnSync.
  - El JS de `populateTangoSelects` ya tenía guards `if (sL1 && sL2 && ...)` así que no rompe cuando los elementos no existen.

### Fase 7 — UI RxnSync

- `app/modules/RxnSync/views/index.php`:
  - Circuito visual **condicional por área**:
    - Tiendas: 3 badges (Artículos → Precios → Stock).
    - CRM: 4 badges (Artículos → Catálogos → Precios → Stock).
  - Botón "Sync Catálogos" (btn-outline-warning) visible solo en CRM, entre "Configuración" y "Sync Precios".
  - Handler JS dispara POST AJAX al endpoint, muestra progress, alert de éxito/error, y recarga la página para reflejar nuevas precondiciones.

### Fase 8 — Documentación

- `app/modules/RxnSync/MODULE_CONTEXT.md`: nueva sección **"Bifurcación CRM vs Tiendas"** explicando circuito, campos usados, precondiciones calculadas por área. Sección "Servicios" documenta el service movido. Acciones principales documentadas con cross-refs de releases.
- `app/modules/CrmPresupuestos/MODULE_CONTEXT.md`: actualizado "QUÉ HACE" para aclarar que **consume** catálogos (no los sincroniza). `CommercialCatalogSyncService` removido de "Piezas principales → Servicios" — apuntado a RxnSync. `CommercialCatalogRepository` se mantiene. Documentado auto-trigger defensivo en `loadCatalogData()`.
- `app/modules/EmpresaConfig/MODULE_CONTEXT.md`: **reescrito completamente**. Nueva sección "Bifurcación CRM vs Tiendas" con tabla completa de campos (Tiendas-only, CRM-only, compartidos, B2C branding). Documenta endpoints AJAX de release 1.12.2 + 1.12.3. Documenta reglas operativas de JS scope, shape de textarea Clasificaciones, principio diagnóstico persistente > DevTools.

## Por qué

Esta iteración cierra la saga que arrancó cuando Charly reportó que el selector de empresa Connect no traía el listado (release 1.12.2), siguió con z-index + Clasificaciones PDS (1.12.3), restauración del Import masivo (1.12.4) y ahora termina con el flujo correcto de sincronización por área.

El **problema operativo**: en un tenant CRM recién configurado, el operador apretaba "Sync Precios" y estaba disabled sin explicación clara. Para habilitarlo había que correr "Sync Catálogos"... pero ese botón vivía en el módulo Presupuestos (no en la consola de sync). Además los selectores `lista_precio_1/2` y `deposito_codigo` se mostraban en CRM pero no se usaban — confundía al operador que los configuraba pensando que era necesario.

El **principio arquitectónico**: cada módulo debe hacer una cosa. RxnSync = responsable de sincronización. Presupuestos = responsable de presentación y cotización. EmpresaConfig = responsable de configurar. Los catálogos comerciales son entidades de sincronización → RxnSync. Los selectores `lista_precio_1/2` son específicos de Tiendas (flujo de 2 listas planas) → solo Tiendas los ve.

## Impacto

- El operador CRM ahora tiene un flujo lineal claro: `Configurar Tango → Sync Artículos → Sync Catálogos → Sync Precios → Sync Stock`. Todo en una pantalla.
- Los selectores Tiendas-only desaparecen del CRM — cero confusión.
- El sync de precios y stock en CRM recorre TODAS las listas y depósitos automáticamente (ya estaba implementado — solo faltaba quitar la exigencia de `deposito_codigo` y exponer el botón Sync Catálogos).
- Presupuestos queda más limpio — solo presenta, no sincroniza. El botón "Ir a RxnSync" guía al operador si detecta que faltan catálogos.
- Backward compat con Tiendas 100% — el flujo de Tiendas no se tocó.
- Backward compat con DB 100% — no se borran columnas, solo se ocultan en UI y no se consumen.

## Decisiones tomadas

- **`CommercialCatalogRepository` queda en `CrmPresupuestos`** aunque el service se movió. El repo es consumido directamente por el form de Presupuestos (findAllByType + findOption + findFirstByType). Moverlo duplicaba imports en todos los callers sin beneficio real. El service es el que se comparte entre el botón de RxnSync y el auto-trigger de Presupuestos.
- **No se borran columnas `lista_precio_1/2` ni `deposito_codigo` de `empresa_config_crm`**. Quedan dormidas. Si algún día se decide que CRM también tiene un "depósito de referencia" configurable, están listas.
- **El botón Sync Catálogos se renderiza solo en CRM**. En Tiendas no tiene sentido — el catálogo comercial es una abstracción CRM (crm_catalogo_comercial_items). Tiendas opera con los 2 selectores planos.
- **El auto-trigger defensivo en `PresupuestoController::loadCatalogData()` queda intacto**. Si el operador abre un form de presupuesto y el catálogo está vacío, el sync se dispara inline. Mejora UX sin romper nada.
- **syncStockWithConfig no se refactorea más allá** (no se crea `syncStockCatalogoCrm` como hermano de `syncPreciosCatalogoCrm`). La lógica actual ya soporta los dos modos gracias al branch `$isCrm && $crmRepo`. Refactorear más sería cambio cosmético sin ganancia funcional.
- **Documentación pesada**: se invirtió tiempo en reescribir 3 MODULE_CONTEXT.md porque este tipo de bifurcación CRM/Tiendas es exactamente el tipo de conocimiento tribal que se pierde sin docs. Las próximas sesiones (propias o de otro agente) van a saber exactamente qué campo aplica a qué área y por qué.

## Validación

- Smoke test CRM: `/mi-empresa/crm/configuracion` → selectores lista_precio_1/2 y deposito_codigo NO aparecen. En su lugar aparece el banner alert-info con link a RxnSync.
- Smoke test CRM: `/mi-empresa/crm/rxn-sync` → circuito muestra 4 badges. Botón "Sync Catálogos" visible entre Configuración y Sync Precios. Al click, confirma y dispara POST al endpoint.
- Smoke test Tiendas: `/mi-empresa/configuracion` → selectores lista_precio_1/2 y deposito_codigo SÍ aparecen como antes. Sin regresiones.
- Smoke test Tiendas: `/mi-empresa/rxn-sync` → circuito muestra 3 badges (sin Catálogos). Botón Sync Catálogos NO aparece. Sin regresiones.
- Smoke test de ruta vieja: `/mi-empresa/crm/presupuestos/catalogos/sincronizar` → 404 (correcto, fue eliminada).
- Smoke test form Presupuestos: botón "Ir a RxnSync" redirige correctamente a la consola.
- Precondiciones: `precios_ready` y `stock_ready` en CRM se vuelven true al terminar Sync Catálogos (countByType > 0 para lista_precio y deposito).

## Pendiente

- No aplica — iteración cerrada.
- Tech debt registrado previo: `UsuarioController::fetchTangoProfile():137` sigue guardando JSON shape incorrecto al textarea clasificaciones_pds_raw. Documentado en EmpresaConfig/MODULE_CONTEXT.md.
- Tech debt registrado previo: el tab Clientes de RxnSync aparece en Tiendas aunque no aplique. Gate en runOnlyImportTab cubre el caso práctico.
