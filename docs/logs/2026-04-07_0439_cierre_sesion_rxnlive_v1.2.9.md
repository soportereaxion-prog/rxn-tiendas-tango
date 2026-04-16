# 2026-04-07 04:39: Cierre de Sesión — RXN Live Estabilización Completa (v1.2.9)

## Qué se hizo
Sesión de debugging profundo del módulo RXN Live. Se resolvieron **5 bugs** acumulados
que impedían el funcionamiento del módulo en su totalidad y parcialmente.

## Bugs Resueltos

### 🔴 CRÍTICO — Template Literal JS sin cerrar (buildDiscreteDropdown)
- **Síntoma:** Pantalla en blanco total en todos los datasets, 7 errores en consola,
  sessionStorage vacío, sin importar Ctrl+F5 o modo incógnito.
- **Causa:** Un `\`` (backslash-backtick) dentro del template literal de `buildDiscreteDropdown()`
  es una secuencia de escape válida de JS que produce un backtick literal SIN cerrar el template.
  El parser leía el resto del archivo como string, dejando DOMContentLoaded inaccesible.
- **Fix:** Backtick plano `\`` sin backslash + `</div>` de cierre del wrapper faltante.
- **Archivo:** `app/modules/RxnLive/views/dataset.php` línea ~697

### 🟡 Z-index — Dropdown de filtros tapado por fila TOTAL sticky
- **Síntoma:** El panel de filtros quedaba tapado por la fila TOTAL al abrirse.
- **Fix:** `dropdown-menu` → `z-index: 1050`; `tfoot` → `z-index: 1`.
- **Archivo:** `dataset.php` líneas 857, 907

### 🟠 TypeError silencioso — flatFilters/flatDiscreteFilters nulos
- **Síntoma:** TypeError interrumpía renderPlana() al hidratar desde sessionStorage.
- **Fix:** Verificación `typeof === 'object'` antes de asignar.

### 🟣 Recorte de dropdown — min-height insuficiente en contenedores
- **Síntoma:** Con pocos registros el contenedor se achicaba y cortaba el panel de filtros.
- **Fix:** `min-height: 520px` en `#plana` y `#pivotResultContainer`.

### 🔵 Sort incorrecto — Fechas no ordenaban
- **Síntoma:** Al clickear el header de la columna Fecha, el orden no cambiaba.
- **Causa:** `parseFloat("2026-03-29")` devuelve `2026` — todas las fechas del mismo año
  comparaban igual. El comparador las trataba como idénticas.
- **Fix:** Detección de tipo date/datetime via `pivotMetadata`, comparación léxica sobre
  string ISO original (siempre YYYY-MM-DD desde la BD, ordena cronológicamente correcto).
  Mejoras adicionales: `isFinite()` para numéricos, `localeCompare('es')` para texto, 
  nulls siempre al fondo.

## Archivos Modificados
- `app/modules/RxnLive/views/dataset.php` — fixes JS, z-index, altura, sort
- `app/config/version.php` — bumpeado a v1.2.9 (build 20260407.3)

## Sin Migraciones
Todos los cambios son exclusivamente frontend (JS/HTML/CSS). La base de datos no fue tocada.

## OTA
El Rey ejecuta el OTA manualmente.
