# 2026-04-15 — Release 1.9.0 — RXN Live: resize, wrap, colores Excel y fix de filtros en export

## Qué se hizo

Tres mejoras al módulo RXN Live pedidas explícitamente por Charly + un bugfix crítico que salió a la luz durante la auditoría.

### 1. Resize de columnas persistente

- Cada `<th>` del `renderPlana()` renderiza ahora un `span.rxn-col-resizer` absoluto en `top:0 right:0` de 6px de ancho con `cursor: col-resize`.
- `onmousedown` dispara `startColResize(col)` que captura X inicial + width actual y engancha listeners globales de `mousemove` / `mouseup`.
- Durante el drag, el width se aplica directo al DOM del `<th>` (sin re-render para evitar flicker).
- Al soltar: `renderPlana()` (para que los `<td>` tomen el nuevo `max-width`) + `saveVolatileState()`.
- Rango defensivo `[40, 800]` px para evitar widths absurdos por clicks accidentales.
- El click en el resizer llama `event.stopPropagation()` para no disparar el sort del header.
- Persistencia doble:
  - **sessionStorage** (`rxn_live_volatile_<dataset>`) → vuelve al recargar aunque no guardes vista.
  - **BD** (`rxn_live_vistas.config` JSON) → queda asociado a la vista al "Guardar".
- Hidratación en `applyViewConfig()` y en el bloque de volatile state filtra valores fuera de rango.

### 2. Switch global "Ajustar" (wrap vs truncar)

- Nuevo botón en la barra de acciones (al lado de "Columnas") con icono `bi-text-wrap` y label "Ajustar".
- Estado `btn-outline-secondary` cuando está en modo truncar (default), `btn-info` cuando está en modo wrap.
- Modo **truncar** (default):
  - `white-space: nowrap; overflow: hidden; text-overflow: ellipsis;`
  - Tooltip nativo `title="${val}"` en cada `<td>` para ver el valor completo al hover.
- Modo **ajustar**:
  - `white-space: normal; word-break: break-word; vertical-align: top;`
  - La celda crece en alto, como el Excel de referencia que pasó Charly.
- Persiste en las mismas dos capas que `colWidths` (sessionStorage + BD).
- Decisión: **toggle global para toda la tabla**, no por columna. Por columna sería lío al pepe y no aporta al caso de uso real.

### 3. Colores XLSX fijos tipo "Tabla azul" de Excel

- `buildXlsxThemeStyles(string $theme)` renombrado a `buildXlsxStyles()` sin parámetro.
- Paleta fija:
  - Header: fondo `#4472C4` + texto `#FFFFFF` bold
  - Body: fondo `#FFFFFF` + texto `#000000`
  - Bordes: `#8EA9DB` (azul claro tipo tabla Excel)
- Se eliminó la detección dark/light del JS (`isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark' || ...`) y la inyección del input hidden `theme` al submit del export.
- El selector `.dynamic-export-input:not([name="theme"])` en `updateExportForm()` pasó a ser `.dynamic-export-input` (sin exclusión).

### 4. BUGFIX crítico — filtros no se enviaban al export

**Síntoma** (reportado por Charly): "Cuando aplicamos un filtro y exportamos a excel solo los valores aplicados exporta todo, es decir exporta el informe completo."

**Causa raíz**:
- Los `flatFilters` (texto "Filtrar..." por columna) y `flatDiscreteFilters` (dropdown de valores únicos) vivían solo en variables JS client-side.
- `updateExportForm()` **nunca los empujaba al form de export**, solo mandaba `hidden_cols`, `ordered_cols`, `sort_col`, `sort_asc` y `theme`.
- Peor: el controller ni siquiera sabía qué hacer con `flat_filters` (solo tenía soporte para `discrete_filters` pero el JS tampoco los mandaba).
- Los filtros URL (ej: `?f[col][op]=contiene&...`) sí se exportaban porque PHP los renderizaba como hidden inputs al armar el form.

**Fix**:

**Frontend** (`dataset.php::updateExportForm`):
```js
addHidden('flat_filters', JSON.stringify(clean));
addHidden('discrete_filters', JSON.stringify(clean));
addHidden('global_date_format', globalDateFormat);
```

**Backend** (`RxnLiveController::exportar`):
- Lee `flat_filters` y `global_date_format` de POST.
- Helper privado `$formatVisual($raw, $col)` que toma el valor raw + pivot_metadata + globalDateFormat y replica el formateo que hace `formatRxnDate()` en JS (usa `DateTime::format()` directo — los tokens coinciden para los formatos soportados: `Y-m-d`, `d/m/Y`, `d-m-Y`, `d/m/y`, `d-m-y`).
- Aplica `discrete_filters` pasando primero los valores por `formatVisual()` — antes comparaba con raw y fallaba si el user filtraba "05/03/2026" sobre una columna cuyo raw era "2026-03-05".
- Aplica `flat_filters` en memoria después de `discrete_filters`. Soporta wildcard `%` estilo LIKE (se convierte a regex `.*` con `preg_quote` + `str_replace`).

### Por qué

- **Punto 1 (resize)**: las columnas de texto largo (ej: `diagnostico`) estiran la columna al ancho del contenido (`text-nowrap` en cada `<td>`) y se comen el espacio visual de las columnas vecinas. No había forma de ajustar.
- **Punto 2 (wrap)**: complementario al punto 1 — a veces querés que la columna quede angosta Y ver todo el texto (wrap). Otras veces preferís ver solo el resumen (truncate + tooltip). Switch global cubre ambos casos.
- **Punto 3 (colores)**: Charly pasó un Excel de referencia con la paleta clásica de "Tabla azul" de Excel (imagen 2 del pedido). La paleta theme-aware previa no matcheaba ningún Excel conocido y dependía del tema del navegador del user al momento del export — inconsistente.
- **Punto 4 (filtros en export)**: bug puro de cableado que hacía que la UX prometiera una cosa ("estás exportando los datos filtrados") y entregara otra ("exporté todo"). Esto rompe la confianza en el feature — si el user no revisa, exporta datos que no quería mostrar.

### Impacto

- **Ruptura de API pública**: ninguna. `/rxn_live/exportar` sigue aceptando los mismos parámetros POST + 3 nuevos opcionales. Forms viejos (si hubiera) siguen funcionando igual.
- **BD**: sin migración. `rxn_live_vistas.config` es JSON — `colWidths` y `wrapText` se agregan al JSON de las vistas que guarde el user de ahora en más. Las vistas existentes siguen funcionando (los campos son opcionales en `applyViewConfig`).
- **Performance**: los filtros aplicados en memoria en el backend agregan O(n·m) donde n=filas exportadas (≤10k por el cap hardcoded) y m=cantidad de filtros activos. Muy barato comparado con la query a la vista SQL.

### Decisiones tomadas

1. **Wrap toggle global vs por columna**: global. Decisión explícita de Charly — "por ahí en un futuro lo aplicamos por columna pero es mucho lío al pepe".
2. **Persistencia doble (session + BD)**: Charly dijo textual "la idea de toda la APP es persistir el back" — así que al guardar vista queda en BD, y mientras tanto el session storage cubre la experiencia inmediata.
3. **Colores independientes del tema**: explícito de Charly — "Definamos esos colores sin importar el tema y que no tenga en cuenta el tema".
4. **Formateo de fechas en filtros backend**: replicar el formato visual que ve el user. Alternativa descartada: comparar sobre raw + obligar al user a filtrar en formato ISO (rompe UX).
5. **Wildcards `%` en flat_filters**: mantener paridad con el comportamiento JS previo (regex `.*`).

### Validación

- Resize: arrastrar borde derecho de una columna → width cambia en vivo → soltar → recargar la página → width se mantiene.
- Wrap: tocar "Ajustar" → las celdas largas se wrappean y crecen en alto. Tocar de nuevo → vuelve a truncar y aparecen tooltips al hover.
- Colores: exportar un XLSX desde un dataset cualquiera → abrir en Excel → headers azul medio con texto blanco, bordes azul claro.
- Filtros en export:
  - Tipear "MULTI" en el input "Cliente" → ver la tabla filtrada a 1 fila → exportar → verificar que el CSV/XLSX tiene 1 fila.
  - Abrir dropdown discreto en "Clasificación" → dejar solo "ASETGO" marcado → exportar → verificar que solo salen las filas con esa clasificación.
  - Cambiar "Formato de Fechas" a `DD/MM/YYYY` → tipear "03/2026" en "Fecha" → exportar → verificar que filtra correctamente.

### Pendiente

- Nada bloqueante. Potencial futuro: autosize de columna (double-click en el resizer ajusta al contenido más largo visible).

### Archivos tocados

- `app/modules/RxnLive/RxnLiveController.php` — nuevo `formatVisual` helper, lectura de `flat_filters` y `global_date_format`, aplicación de filtros en memoria, renombrado y reescritura de `buildXlsxStyles`.
- `app/modules/RxnLive/views/dataset.php` — botón "Ajustar", variables `colWidths`/`wrapText`, resize handlers, truncate+tooltip / wrap rendering, `updateExportForm` reescrita, limpieza de la lógica theme en el submit listener.
- `app/modules/RxnLive/MODULE_CONTEXT.md` — sección "Qué hace" extendida con las 3 features nuevas.
- `app/config/version.php` — bump a 1.9.0 build 20260415.6 con history entry completa.
