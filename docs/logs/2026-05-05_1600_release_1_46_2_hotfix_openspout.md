# Hotfix 1.46.2 — Restauración de exportación XLSX (OpenSpout)

**Fecha**: 2026-05-05 18:00
**Tipo**: Hotfix urgente — bug de runtime que ocultaba bug de migración v3 → v4
**Severidad**: Alta (feature visible al usuario rota: "Excel" en RxnLive + matriz/import en CrmNotas)

---

## El cuento corto

Lo que parecía un bug simple ("falta OpenSpout") destapó un combo de cinco bugs encadenados que se ocultaban entre sí. Solo se podían ver en orden, porque cada uno cortaba antes de que el siguiente tuviera chance de ejecutarse.

---

## Los 5 bugs en orden de descubrimiento

### Bug 1 — `vendor/openspout/` físicamente ausente (Fatal `Class not found`)

- **Síntoma visible**: pantalla blanca con `"La exportación requiere que OpenSpout esté instalado."`.
- **Root cause**: `openspout/openspout` JAMÁS había sido declarada en `composer.json` desde su introducción al proyecto. Algún `composer require` o `composer update` posterior reconcilió el lock y se la llevó puesta. Mismo antipatrón exacto del incidente de `dompdf` en 1.27.0.
- **Por qué no sabemos el commit responsable**: la lib nunca llegó a estar en `composer.lock`, así que `git log` por el path no encuentra nada. La única pista vino de cotejar `vendor/openspout/` ausente vs código vivo referenciándola.
- **Fix**: `composer require openspout/openspout:^4.30` → instaló v4.32.0. `composer.json` ahora la declara formalmente — nunca más se borra al reconciliar.

### Bug 2 — `BorderName`, `BorderWidth`, `BorderStyle` no existen en OpenSpout v4

- **Síntoma visible**: al reinstalar, el browser descargaba un archivo con el header del HTML de Xdebug, no un XLSX. Excel veía `<br />` y rechazaba el archivo como corrupto.
- **Cómo lo detecté**: descargué el archivo desde curl autenticado y leí los primeros 200 bytes en raw. Apareció el Fatal Error real: `Uncaught Error: Class "OpenSpout\Common\Entity\Style\BorderName" not found`.
- **Root cause**: `RxnLiveController::buildXlsxStyles()` estaba escrito contra OpenSpout v3, donde las clases `BorderName`, `BorderWidth`, `BorderStyle` eran clases sueltas. En v4 esas clases desaparecieron — todas las constantes ahora viven en `\OpenSpout\Common\Entity\Style\Border` (`Border::TOP`, `Border::WIDTH_THIN`, `Border::STYLE_SOLID`).
- **Fix**: reemplazado uno por uno. Estilos azules de tabla intactos.

### Bug 3 — Setters `with*()` renombrados a `set*()` en v4

- **Síntoma**: nuevo Fatal — `Call to undefined method OpenSpout\Common\Entity\Style\Style::withFontBold()`.
- **Root cause**: v3 tenía API inmutable estilo `$style->withFontBold(true)->withFontColor(...)`. v4 los renombró a `set*()` (la API actual). El cambio aplicaba a `withFontBold`, `withFontColor`, `withBackgroundColor`, `withBorder`.
- **Fix**: renombrados a `setFontBold()`, `setFontColor($c)`, `setBackgroundColor($c)`, `setBorder($b)`. Detalle: `setFontBold()` no toma argumento (toggle implícito a true), distinto de `withFontBold(true)`.

### Bug 4 — `Row::fromValuesWithStyle()` (singular) desaparecido

- **Síntoma**: nuevo Fatal — `Call to undefined method OpenSpout\Common\Entity\Row::fromValuesWithStyle()`.
- **Root cause**: v3 tenía `fromValuesWithStyle($vals, $style)` (singular). v4 lo unificó en `fromValues($vals, $style)` (mismo segundo argumento). Existe `fromValuesWithStyles` (plural) pero es API distinta para cell-styles.
- **Fix**: reemplazado por `Row::fromValues($vals, $style)` en las 3 llamadas (header, data rows, totals row).

### Bug 5 — `if (ob_get_length()) ob_end_clean()` no vacía buffers anidados

- **Síntoma**: aún con todo el código corregido, el XLSX podría salir corrupto en algunas configuraciones de Open Server por bytes residuales en buffers apilados.
- **Root cause**: Open Server arranca con varios output buffers anidados (output_handler, gzip, etc). El `if (ob_get_length()) ob_end_clean()` solo limpia el buffer ACTIVO de nivel 1. Los inferiores escupen bytes al output **antes** del binario.
- **Fix**: `while (ob_get_level() > 0) ob_end_clean();` vacía toda la pila. Aplicado preventivamente también a `CrmNotasController::export()` y `downloadTemplate()` (mismo patrón).

---

## Mejora aprovechada: fechas en formato es-AR

Charly notó que las columnas de fecha en el export salían `2026-03-29` (formato MySQL/ISO crudo). Sumé un bloque de transformación pre-export, antes del split CSV/XLSX:

- Columnas con `type=date` (en `pivot_metadata`) → `d/m/Y` (29/03/2026).
- Columnas con `type=datetime` o `timestamp` → `d/m/Y H:i:s` (29/03/2026 14:30:00).
- Aplicado a CSV y XLSX por igual, sin duplicar lógica. Cualquier valor que no parsee como `DateTime` queda crudo (defensivo).

Alineado con la regla del proyecto sobre es-AR vigente desde 2026-04-16 (los inputs de fecha/hora ya están en `d/m/Y H:i:S` vía `rxn-datetime.js`).

---

## Por qué los bugs 2-4 quedaron tanto tiempo dormidos

Imposible que alguien hubiera probado un XLSX exportado contra el código v3 desde que se introdujo, porque la lib estaba ausente — el `class_exists` cortaba antes con el `die()` defensivo del Bug 1. Mientras la lib no se instalaba, el bug v3 → v4 era invisible. Y la lib nunca se instalaba porque tampoco estaba en `composer.json`.

Es un caso clásico de **bugs que se enmascaran mutuamente**: cada uno protegía al siguiente. Solo cuando reinstalé la lib quedaron al descubierto los siguientes en orden.

---

## Validación end-to-end

Validé desde curl autenticado contra `localhost:9021/rxn_live/exportar`:

```
HTTP=200 SIZE=4340
Magic=504b0304            ← PK\x03\x04 = ZIP/XLSX correcto
✅ XLSX VALIDO
Entradas: 12
styles.xml exists: YES (777 bytes)
```

Las 12 entradas internas son las esperadas de un XLSX real: `[Content_Types].xml`, `xl/workbook.xml`, `xl/styles.xml`, `xl/sharedStrings.xml`, `xl/worksheets/sheet1.xml`, etc. Los azules embebidos en `styles.xml`.

Smoke test del transform de fechas en CLI (independiente del HTTP):

```
2026-03-29        → 29/03/2026
2026-04-01 14:30:00 → 01/04/2026 14:30:00
```

---

## Decisiones tomadas

- **Versión `^4.30`** (no clavar versión exacta): OpenSpout v4 es estable y la API documentada en composer-show es la actual del proyecto. Se instaló v4.32.0 (último dentro del rango).
- **Migración v3 → v4 in-place, no rollback a v3**: la v3 ya no recibe security updates, y el código nuestro era cinco líneas de cambio. El downgrade habría sido falsa economía.
- **Flush de buffers preventivo en CrmNotas también**: mismo patrón aplicable, mismo módulo expuesto al output streaming. Costo de cambio mínimo, beneficio claro contra el mismo modo de falla.
- **Documentación local en módulos afectados**: el CLAUDE.md raíz tenía la regla post-dompdf, pero claramente no fue suficiente — hace falta que cada módulo con dep externa crítica tenga el cartel local. Patrón aplicable a futuro.

---

## Pendiente / próximos pasos

- A futuro: considerar un script `tools/audit_composer_vendor_drift.php` que cruce `composer.json` declarado vs `vendor/*` físicamente vs `composer show --installed`, y avise si hay drift. Hoy es chequeo manual y se nos cuela cada tanto.
- Revisar otros módulos con deps externas (PHPMailer, web-push, dompdf) — todos están declarados, pero un audit periódico no estaría mal.

---

## Relevant Files

- `composer.json` — `openspout/openspout:^4.30` declarado en `require`.
- `composer.lock` — registra v4.32.0.
- `vendor/openspout/openspout/` — reinstalado.
- `app/modules/RxnLive/RxnLiveController.php` — migración OpenSpout v3 → v4 (estilos + Row factory) + flush de buffers + transform de fechas pre-export.
- `app/modules/CrmNotas/CrmNotasController.php` — flush de buffers preventivo en export() y downloadTemplate().
- `app/config/version.php` — bump 1.46.1 → 1.46.2 / build 20260505.2.
- `database/migrations/2026_05_05_01_seed_customer_notes_release_1_46_2.php` — seed idempotente de la nota visible al cliente.
- `app/modules/RxnLive/MODULE_CONTEXT.md` — sección "Dependencia crítica: openspout/openspout" con narrativa de los 5 bugs encadenados.
- `app/modules/CrmNotas/MODULE_CONTEXT.md` — riesgo de dep faltante sumado a "Riesgos conocidos".
- `docs/logs/2026-05-05_1600_release_1_46_2_hotfix_openspout.md` — este archivo.
