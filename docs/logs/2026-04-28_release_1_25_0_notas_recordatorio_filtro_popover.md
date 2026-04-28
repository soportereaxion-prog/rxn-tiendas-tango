# Release 1.25.0 — Notas con recordatorio en Agenda + fix transversal del filtro por columna

**Fecha**: 2026-04-28
**Build**: 20260428.1
**Pedido**: Charly — iteración #36

## Qué se hizo

### Fase 1 — Diagnóstico transversal del popover de filtro por columna

Bug reproducido por Charly en CrmPedidosServicio: con un único registro filtrado, al
abrir el embudo de cualquier columna el popover "Filtrar columna" quedaba clipeado
o empujado hacia abajo del listado, no flotando por encima.

**Root cause** (CSS quirk clásico):
- `rxn-advanced-filters.js` appendaba el popover al `<th>` con `position: absolute; top: 100%`.
- El `<th>` vive dentro de `.rxn-table-responsive` (en `public/css/rxn-theming.css`) que
  tiene `overflow-x: auto`.
- La spec CSS fuerza que cuando un eje de overflow está en `auto`/`scroll`/`hidden`, el
  otro eje se comporta como `auto` (no puede ser `visible`). El popover queda atrapado
  dentro del overflow container y se clipea o estira el área de scroll vertical.

**Fix aplicado**: detach del popover a `<body>` con `position: fixed`. Ver "Decisiones tomadas".

### Fase 2 — Notas con recordatorio en la Agenda

Charly necesita que las notas del CRM (que usa como TODO list bajo tratativas como
"Pendientes general") aparezcan en el calendario y le avisen a la hora indicada.

Implementación end-to-end:
1. Migración: `crm_notas` suma `fecha_recordatorio DATETIME NULL`,
   `recordatorio_disparado_at DATETIME NULL`, `created_by INT NULL` y un índice
   compuesto para el late firer.
2. Modelo + repository: `CrmNota` y `CrmNotaRepository` aceptan los campos nuevos.
   `save()` resetea `recordatorio_disparado_at` cuando cambia la fecha y dispara el
   proyector de agenda al final.
3. Form: input `datetime-local` "Recordatorio (opcional)" en `views/form.php`,
   cubierto por el wrapper RxnDateTime global (es-AR 24hs).
4. Controller: `parseRecordatorioInput()` tolera Flatpickr (`Y-m-d H:i:s`) y nativo
   (`Y-m-d\TH:i:s`). `created_by` se setea con `$_SESSION['user_id']` al crear.
5. Proyector de agenda: `onNotaSaved()` proyecta evento de 30min con color rosa
   `#d63384` solo si la nota tiene `fecha_recordatorio`. Si se le saca la fecha,
   el evento previo se soft-deletea. `onNotaDeleted()` limpia idempotente.
6. Vista de Agenda: nuevo checkbox "Notas" (badge rosa). El rescan masivo suma
   branch para notas (filtra `fecha_recordatorio IS NOT NULL`).
7. Late firing: `NotificationController::feed()` ahora llama `fireDueReminders()`
   antes de devolver el feed. Consulta notas con recordatorio vencido + sin
   disparar para el usuario actual, dispara via `NotificationService::notify()`
   con dedupe key estable `crm_notas.recordatorio.{id}`, y marca
   `recordatorio_disparado_at = NOW()`. Errores silenciados — el feed no debe
   romperse bajo ningún escenario.

## Por qué

- **Filtro popover**: el bug era transversal a TODO el sistema (más de 12 listados
  usan `rxn-filter-col`). Resolverlo en un solo archivo (`rxn-advanced-filters.js`)
  arregla la UX en toda la suite. Charly lo marcó como bloqueante.
- **Notas con recordatorio**: el sistema de notificaciones de release 1.23.0 tenía
  toda la infra (campanita + dropdown + endpoint feed.json) pero no había forma
  de disparar notifs a hora futura. Las notas del CRM eran el primer caso de uso
  real con recordatorio diferido. Late firing client-driven (sin cron) alcanza
  para el patrón de uso de Charly y evita complejidad operativa de configurar un
  scheduled task en Plesk.

## Decisiones tomadas

### Popover de filtro: position:fixed con coordenadas calculadas, NO Floating UI

Opciones evaluadas:
- **A**: detach a body + position:fixed con `getBoundingClientRect()`. Vanilla JS,
  un solo archivo, sin dependencias. **Elegida**.
- **B**: borrar `overflow-x: auto` del `.rxn-table-responsive`. Rompe el scroll
  horizontal en mobile y en tablas anchas. Descartada.
- **C**: toggle de overflow en open/close. Rompe el scroll horizontal mientras
  el popover está abierto y genera reflows. Descartada.
- **D**: importar Floating UI / Popper.js. Demasiada dependencia para un único
  popover en toda la app. Descartada.

Detalle del fix:
- Render primero invisible para medir (`left/top = -9999px`), después
  posicionado en el siguiente frame con `requestAnimationFrame`.
- Clamp horizontal contra `clientWidth - 8px` (margen).
- Si no entra abajo, se abre arriba (auto-flip vertical).
- Listeners de `scroll` (capture) y `resize` cierran el popover automáticamente.

### Recordatorios: late firing en feed.json, no cron

El sistema actual de notificaciones es 100% pull-based (campanita pulea al cargar
página + al abrir dropdown). Sumar un cron implicaba:
- Configurar scheduled task en Plesk (carga operativa).
- Lidiar con ambientes donde el cron no se configure (Charly clientes en distintos
  hostings).
- Logs separados, debug más complejo.

Late firing ataca el caso real: Charly abre la app durante el día y usa la
campanita, así que los recordatorios disparan apenas el cliente entra. Si más
adelante hace falta disparo preciso para usuarios que no abren la app a tiempo,
sumamos un cron sin tocar el resto del flujo (el código del firer es reutilizable
desde un endpoint `/cron/notifications`).

### Color rosa para notas en agenda (#d63384)

Bootstrap 5 `pink-500`. No colisiona con ningún otro origen del calendario
(PDS azul, Presupuestos verde, Tratativas amarillo, Llamadas violeta, Manuales
gris, Horas teal). Coherente con el branding general.

## Validación

- [x] `php -l` sobre todos los archivos PHP modificados — sin errores.
- [x] Migración aplicada en local.
- [x] Seed customer_notes 1.25.0 aplicado.
- [ ] Test manual del filtro en CrmPedidosServicio (1 fila + muchas filas).
- [ ] Test manual de creación de nota con recordatorio + aparición en Agenda.
- [ ] Test manual del late firer abriendo el feed manualmente con una nota vencida.

## Pendiente

- Subir el ZIP a Plesk como release 1.25.0 (build 20260428.2).
- **Próxima sesión**: feature de Web Push (popups del navegador). Charly NO
  tiene cron en Plesk → vamos con n8n como scheduler externo. Plan persistido
  en Engram (`notifications/web-push-roadmap`).

## Hotfix dentro del release — bug ENUM origen_tipo (build 20260428.2)

Charly validó visualmente: el filtro funcionó, el recordatorio disparó la
notificación, pero la nota NO aparecía en el calendario. Diagnóstico:

- La columna `crm_agenda_eventos.origen_tipo` es un ENUM cerrado declarado en
  el CREATE TABLE original (`'manual','pds','presupuesto',...`).
- Sumar `'nota'` a `AgendaRepository::ORIGENES` (constante PHP) no alcanza —
  MySQL rechaza el INSERT porque el valor está fuera del ENUM real de DB.
- El `try/catch (\Throwable) {}` defensivo del proyector tragaba el
  PDOException silenciosamente, así que no había rastro visible del fallo.

Fix: `database/migrations/2026_04_28_02_alter_agenda_eventos_origen_tipo_nota.php`
extiende el ENUM con MODIFY COLUMN. Idempotente — chequea
`INFORMATION_SCHEMA.COLUMNS.COLUMN_TYPE` antes de alterar.

**Aprendizaje persistido en Engram**: cualquier valor nuevo agregado a
`AgendaRepository::ORIGENES` requiere SIEMPRE migración ALTER MODIFY del ENUM.

## Archivos relevantes

- `public/js/rxn-advanced-filters.js` — fix transversal del popover.
- `database/migrations/2026_04_28_00_alter_crm_notas_add_recordatorio.php` — schema.
- `database/migrations/2026_04_28_01_seed_customer_notes_release_1_25_0.php` — seed.
- `app/modules/CrmNotas/CrmNota.php` — modelo.
- `app/modules/CrmNotas/CrmNotaRepository.php` — save/delete/restore enganchan proyector.
- `app/modules/CrmNotas/CrmNotasController.php` — store/update + parser.
- `app/modules/CrmNotas/views/form.php` — input datetime-local recordatorio.
- `app/modules/CrmAgenda/AgendaProyectorService.php` — onNotaSaved/onNotaDeleted.
- `app/modules/CrmAgenda/AgendaRepository.php` — origen "nota" + color.
- `app/modules/CrmAgenda/AgendaController.php` — rescan + filtros.
- `app/modules/CrmAgenda/views/index.php` — checkbox "Notas".
- `app/modules/Notifications/NotificationController.php` — late firing en feed().
- `app/config/version.php` — bump a 1.25.0.
