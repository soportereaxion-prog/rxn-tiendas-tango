# 2026-04-08 0248 — Fix: RxnSync JS, rutas Push, Selectores Config

## Qué se hizo

### 1. Root Cause: `<script>` en innerHTML

El bug principal que bloqueaba búsqueda, filtro, sort y botones Push/Pull en RxnSync era que
`loadTabContent()` usaba `container.innerHTML = html`. Los browsers **nunca ejecutan** `<script>`
inyectados vía `innerHTML`. Todo el JS en los archivos `tabs/articulos.php` y `tabs/clientes.php`
era letra muerta.

**Solución**: Se movió **toda la lógica JS** al `index.php` de RxnSync usando:
- `initTabControls(btn)` que se llama como callback post-fetch
- Event delegation en `#syncTabsContent` para Push/Pull individuales

Los tabs PHP quedaron como HTML puro (solo estructura + tbody).

### 2. Ruta faltante: Push Tiendas

`/mi-empresa/articulos/{id}/push-tango` no tenía ruta registrada.
Solo existía el equivalente CRM. Añadida.

### 3. Fix: populateTangoSelects

La función recibía actualizaciones parciales `{ listas_precios: items }` pero la condición original
`if (sL1 && sL2 && sDepo)` era un bloque único que también escribía el depósito con HTML vacío si
`data.depositos` no venía. Ahora cada catálogo se actualiza de forma independiente.

Además, luego de poblar el `<select>` llama `_syncSearch()` para actualizar el input visible del
autocomplete custom (`applyLocalSearchPattern`).

### 4. Nuevas features RxnSync

- Paginación client-side: 25/50/100/250 por página, con navegador numérico
- Barra de progreso slim (4px) durante cargas y auditorías
- Handler `btn-pull-tango` agregado vía event delegation
- `select-all` funcional post-carga con rebind de checkboxes
- Sort persistente por tab (no se pierde al filtrar)

## Impacto

- ✅ Búsqueda en tiempo real funcionando
- ✅ Filtro por estado operativo
- ✅ Ordenamiento de columnas funcional
- ✅ Push/Pull individuales desde RxnSync operativos
- ✅ Push/Pull desde CRUD Artículos Tiendas operativo (ruta faltante añadida)
- ✅ Selectores de Listas y Depósito en Config cargan correctamente post-CORS fix

## Decisiones

- Los `<script>` inyectados via innerHTML nunca ejecutan — arquitectura del JS consolidada en el parent view
- `populateTangoSelects` ahora es idempotente por catálogo (no rompe si llega parcial)
- Paginación es client-side para evitar roundtrips al servidor en cada página
