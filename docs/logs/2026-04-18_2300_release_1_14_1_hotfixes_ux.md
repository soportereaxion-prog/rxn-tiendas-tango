# 2026-04-18 — Release 1.14.1: Hotfixes UX sobre 1.14.0

## Fecha y tema

**Release**: 1.14.1 / build `20260418.3`
**Fecha**: 2026-04-18 (iteración nocturna)
**Scope**: 5 fixes visibles detectados por Charly en prueba local de 1.14.0, + reescritura consensuada del CLAUDE.md del proyecto.

## Qué se hizo

### Fix 1 — Hotkeys ALT+P / ALT+E no funcionaban en forms

**Síntoma**: en `Shift+?` aparecían los shortcuts globales pero NO los del módulo (Pedido de Servicio / Presupuesto). Apretar ALT+P o ALT+E no hacía nada.

**Causa raíz**: el `<script>` inline que llama a `RxnShortcuts.register({...})` está dentro de `$content` en los form.php, que `admin_layout.php` imprime al principio del `<body>`. `rxn-shortcuts.js` se carga al final del body. El inline corría **antes** de que `window.RxnShortcuts` existiera → el guard `if (!window.RxnShortcuts) return;` cortaba silencioso.

**Fix**: envolver el register en `document.addEventListener('DOMContentLoaded', ...)` en ambos forms. `DOMContentLoaded` se dispara después de que todos los scripts síncronos del body hayan cargado — garantía de que `rxn-shortcuts.js` ya está disponible. Agregué `console.warn` para detectar futuros casos.

### Fix 2 — Escape en overlay Shift+? disparaba acciones encadenadas

**Síntoma**: al cerrar el overlay con Esc, también se cerraban modales Bootstrap abiertos debajo o se ejecutaba `rxn-back` (volver al listado).

**Causa raíz**: el dispatcher de `rxn-shortcuts.js` estaba registrado en fase **bubble**. `preventDefault()` no alcanzaba para evitar que handlers Bootstrap (registrados internamente en bubble al abrir un modal) reaccionaran al mismo keydown.

**Fix**:
- Dispatcher registrado en **fase capture** (`document.addEventListener('keydown', dispatch, true)`).
- `stopImmediatePropagation()` cuando el overlay está abierto (tanto para Esc que lo cierra como para cualquier otra tecla).
- Agregado `?v=<?= time() ?>` a `rxn-shortcuts.js` en admin_layout para forzar cache-bust — sospecha de que el incógnito estaba sirviendo la versión vieja.

### Fix 3 — Modal Shift+? no respetaba el tema oscuro

**Síntoma**: panel del modal blanco en tema oscuro, contraste feo.

**Causa raíz**: el CSS usaba `[data-bs-theme="dark"]` (convención Bootstrap 5.3) pero el proyecto usa `html[data-theme="dark"]` (sistema propio definido en `public/css/rxn-theming.css`).

**Fix**: selectores actualizados en `rxn-shortcuts.css`. El panel ahora usa las variables del sistema:
- `background: var(--card-bg, var(--bs-body-bg, #ffffff))`.
- `color: var(--text-color, var(--bs-body-color, #212529))`.
- `border-color: var(--border-color, ...)`.
- `kbd` usa `var(--surface-color, ...)` + `var(--text-color, ...)`.

Así el overlay respeta automáticamente cualquier tema que el usuario tenga activo.

### Fix 4 — Contador de envíos en 0 aunque la DB tuviera N>0

**Síntoma**: Charly enviaba correos, el toast decía "Email enviado correctamente", pero el badge del form seguía en 0.

**Debug**: corrí query directa a la DB → el PDS #1070 tenía `correos_enviados_count = 3`. Backend perfecto. El problema estaba en la capa de vista.

**Causa raíz**: `hydrateFormState()` de los dos controllers (PDS y Presupuestos) arma el array para la vista con **whitelist explícito**. Los campos `correos_*` no estaban en ese map → la vista siempre recibía 0.

**Fix**:
- Agregar los 4 campos (`correos_enviados_count`, `correos_ultimo_envio_at`, `correos_ultimo_error`, `correos_ultimo_error_at`) al return de `hydrateFormState` en ambos controllers.
- Partial nuevo `correo_envio_dot.php`: dot flotante estilo WhatsApp (`position-absolute`, `top-0`, `start-100`, `translate-middle`) para superponer en la esquina del botón Enviar.
- Botón Enviar en ambos forms ahora tiene `position-relative` y contiene el include del dot.

Regla defensiva nueva: antes de asumir bug en la DB o en el controller, **correr query directa**. Es 30 segundos vs 30 minutos iterando en la UI. Patrón guardado en Engram con `topic_key: bugfix/hydrate-form-state-whitelist`.

### Fix 5 — Salir de PDS/Presupuesto nuevo sin confirm

**Síntoma**: al crear un PDS o Presupuesto nuevo, apretar Esc (con foco fuera de inputs) o click en "Volver al listado" navegaba al listado sin preguntar, perdiendo los cambios.

**Causa raíz**: los form.js **tienen** un interceptor propio del Esc con `rxnConfirm` "¿Querés salir del proceso sin guardar?". Pero estaba registrado en capture en `DOMContentLoaded`, mientras que el `rxn-back` del registry (que dispara navegación directa) está en mi dispatcher capture registrado al cargar `rxn-shortcuts.js` — que carga **antes**. En capture, los listeners corren en orden de registro → mi dispatcher gana, navega, el interceptor del form nunca se ejecuta.

**Fix quirúrgico**:
- Nuevo data-attribute `data-rxn-form-intercept="1"` en el `<form>` principal de PDS y Presupuestos.
- `rxn-back` del registry ahora chequea `document.querySelector('[data-rxn-form-intercept]')` en su `when` y **se abstiene** si está presente. Delega al interceptor del form.
- Listados siguen funcionando como antes (no tienen la marca).
- **Bonus**: agregué también interceptor del click en el botón Volver (antes solo se interceptaba Esc). Ahora Esc y Volver muestran el mismo confirm.

Patrón guardado en Engram con `topic_key: pattern/form-exit-confirm` para cualquier form nuevo que quiera el mismo comportamiento.

### Fix 6 — Reescritura del CLAUDE.md del proyecto

Consensuada con Charly. 7 ajustes aplicados:

1. **Idioma unificado a español**: `## Reglas`, `## Personalidad`, `## Comportamiento`.
2. **Nueva sección `## Dinámica con Charly`** afuera de los marcadores `<!-- gentle-ai:persona -->` (para que no se pierda si alguna skill regenera ese bloque). Incluye el trato de reverencia afectuosa + ironía técnica permitida + aclaración "cálida en la forma, firme en el fondo".
3. **Reemplazada la regla de `cat/grep/find/sed`** por: "Usar tools dedicadas de Claude Code (Read/Grep/Glob/Edit/Write). Reservar Bash para shell real".
4. **"Never build after changes" endurecido**: Factory OTA cuenta como build. **SOLO** ejecutarlo al cierre de sesión cuando Charly lo pide explícitamente (menciona OTA/subir/Plesk). Si dice "listo por hoy" sin mencionar OTA → commit + session_summary, **nada más**.
5. **Ironía técnica permitida**: para marcar inconsistencias o decisiones dudosas, desde la ternura.
6. **Protocolo Engram deduplicado**: borrado el bloque largo del final. Queda solo el resumen CRÍTICO en reglas del workspace.
7. **Marcadores `<!-- gentle-ai:* -->` removidos**: todavía no usamos skills que los regeneren.

**Agregados extra**: nueva "Regla UI: hotkeys centralizadas en RxnShortcuts" con el orden de carga obligatorio (para que nadie vuelva a tropezar con el bug del Fix 1), y actualización de la regla datetime 24hs para mencionar el `altFormat` es-AR (d/m/Y H:i:S) del release 1.14.0.

## Por qué esta release existe

Release 1.14.0 se mergeó local. Charly la probó y encontró los 5 bugs en una pasada rápida por PDS y Presupuestos. Los fixes son todos quirúrgicos, sin cambios de arquitectura. La reescritura del CLAUDE.md fue mid-session para corregir mis malos hábitos de no consultar Engram y de disparar OTA por iniciativa propia.

## Impacto

- UX mucho más pulida del flujo de documentos: el usuario ve el count de envíos sin ambigüedad, el overlay Shift+? se ve lindo en ambos temas, las hotkeys del módulo aparecen en el modal correctamente, y nadie pierde un presupuesto nuevo por tocar Esc sin querer.
- Patrones nuevos documentados: `data-rxn-form-intercept` para exit-confirm, `RxnShortcuts.register` envuelto en DOMContentLoaded, `hydrateFormState` whitelist.
- Regla dura: nunca más buildear OTA al apuro.

## Decisiones tomadas

- **Dot estilo WhatsApp en vez de badge al lado**: Charly lo pidió explícito. Queda más compacto y se lee mejor que el sobre doble.
- **`data-rxn-form-intercept` opt-in**: los listados no tienen la marca, así `rxn-back` sigue funcionando ahí. Menos invasivo que remover `rxn-back` del registry.
- **Cache-buster en rxn-shortcuts.js**: en desarrollo es molesto pero necesario para no perder media hora debugeando un bug que no existe.

## Validación

- Hotkeys: probadas con `Shift+?` en form de PDS y Presupuesto → aparecen los grupos "Pedido de Servicio" y "Presupuesto" con ALT+P y ALT+E.
- Esc overlay: abrí overlay, apreté Esc → solo cierra el overlay.
- Tema oscuro: overlay con fondo oscuro y texto claro correcto.
- Contador: enviado un correo desde el form → redirect al form → badge verde con "1" en la esquina del botón Enviar.
- Exit confirm: form nuevo, cambios cargados, Esc y click en Volver → confirm "¿Querés salir del proceso sin guardar?".
- CLAUDE.md: revisado línea por línea, sin duplicaciones ni marcadores huérfanos.

## Pendiente

- Nada crítico para esta release. Al siguiente deploy:
  - Si aparece otro form que quiera exit-confirm (ej: Clientes, Llamadas, Notas): aplicar el patrón `data-rxn-form-intercept` documentado en Engram.
  - Considerar inicializar Bootstrap Tooltip globalmente en `admin_layout` para que los `data-bs-toggle="tooltip"` del partial del badge muestren mejor el texto al hacer hover (hoy funciona con el `title` nativo del browser como fallback).
