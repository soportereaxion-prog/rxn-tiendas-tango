# MODULE_CONTEXT — CrmHoras (turnero CRM)

## Nivel de criticidad
MEDIO. El turnero registra tiempo trabajado por operadores. Si se cae temporalmente, no bloquea operación crítica (PDS, Presupuestos, Tratativas siguen andando), pero los operadores pierden registro horario y eso afecta liquidación / reportes a futuro.

## Propósito
Permitir a los operadores del CRM registrar tiempo de trabajo (turnos) con:
- Inicio/cierre en vivo desde el celular (mobile-first).
- Carga diferida (post-facto) cuando no pudieron registrar en el momento.
- Geolocalización opcional al inicio y al cierre (banner si no hay consentimiento).
- Vínculo opcional a Tratativa, PDS o Cliente para trazabilidad.
- Reflejo automático en la Agenda CRM como evento de tipo `hora` (color teal).

## Alcance
**QUÉ HACE:**
- Turnero con botón único contextual (Iniciar verde / Cerrar rojo).
- Contador en vivo "Hoy llevás X:XX:XX" sumando turnos cerrados + abierto en curso.
- Validación: un solo turno abierto por usuario simultáneamente (decisión 6).
- Validación: no-solapamiento al cargar diferido (decisión 15).
- Inconsistencia de geo: si la carga diferida es >24hs después del started_at, se marca `inconsistencia_geo=1` para revisión admin.
- Cruce de medianoche: permitido (decisión 5).
- Hook a `AgendaProyectorService::onHoraSaved` al cerrar — proyecta el turno como evento en `crm_agenda_eventos`.
- Listado paginado con filtros por operador / rango de fechas (vista admin).

**QUÉ NO HACE (por ahora):**
- NO tiene chips de conceptos recientes (decisión 3 diferida).
- NO sugiere cliente por geo (decisión 4 diferida — requiere clientes georreferenciados).
- NO tiene captura de ruta GPS continua durante el turno (decisión 7 diferida).
- NO es PWA (decisión 9). Solo responsive.
- NO tiene push notifications (Fase 2).
- NO bloquea inicio fuera del horario laboral configurado — el horario es orientativo (decisión 5.9).
- NO hay edición de turnos por el operador (solo admin lo puede ajustar — Fase 6 audit log).
- NO hay export para liquidación (decisión 11 — diferido a RxnLive más adelante).

## Decisiones de diseño (sesión 2026-04-24)

| # | Decisión | Razón |
|---|----------|-------|
| 1 | 1 fila por turno con start/end | Más simple para totales y agenda. Pausas se manejan abriendo nuevo turno. |
| 2 | Concepto libre + vínculos opcionales (tratativa/pds/cliente) | Empezar simple. Categorías cerradas si aparece la necesidad. |
| 3 | Geo opcional con aviso, captura inicio + cierre | Obligatoria bloquearía al primer "no permitir" del browser. |
| 4 | Turno olvidado: aviso al login con 3 opciones (cerrar ahora / cerrar a HH:MM / anular) | Auto-cerrar mal corrompe data. |
| 5 | Cruce medianoche permitido | Operadores que terminan tarde existen. |
| 6 | Un solo turno abierto por usuario | Evita "olvidé que tenía uno abierto y abrí otro". |
| 7 | Mobile-first responsive (no PWA todavía) | PWA agranda scope. Hoy alcanza con responsive. |
| 8 | Operador edita solo turnos del día actual; resto solo admin | Defensa contra ajustes a último momento. |
| 9 | Operador ve solo los suyos; admin/super ve todos | Privacidad básica. |
| 6.1 | Vínculos tratativa + pds + cliente pueden coexistir | Trazabilidad rica para reportes futuros. |
| 6.4 | Desvincular = queda suelto (no se borra) | Mismo criterio que PDS/Presupuestos al borrar tratativa. |

## Piezas principales
- **Repository:** `App\Modules\CrmHoras\HoraRepository` — CRUD + queries por operador/tratativa.
- **Service:** `App\Modules\CrmHoras\HoraService` — orquesta iniciar/cerrar/cargarDiferido/anular. Dispara hook a `AgendaProyectorService`.
- **Controller:** `App\Modules\CrmHoras\HoraController` — endpoints HTTP.
- **Vistas:**
    - `views/turnero.php`: vista principal mobile-first del operador.
    - `views/diferido.php`: form de carga post-facto.
    - `views/index.php`: listado admin con filtros.
- **Frontend:**
    - `public/js/crm-horas-turnero.js`: geolocation + contador en vivo + confirm cierre.
    - `public/css/crm-horas.css`: estilos mobile-first.
- **Tabla:** `crm_horas`.
- **Migraciones:**
    - `database/migrations/2026_04_24_02_create_crm_horas.php`
    - `database/migrations/2026_04_24_03_alter_agenda_eventos_add_hora_origen.php`

## Endpoints
- `GET /mi-empresa/crm/horas` — turnero (vista principal del operador).
- `POST /mi-empresa/crm/horas/iniciar` — inicia turno en vivo (CSRF).
- `POST /mi-empresa/crm/horas/cerrar` — cierra el turno abierto del usuario (CSRF).
- `GET /mi-empresa/crm/horas/diferido` — form de carga diferida.
- `POST /mi-empresa/crm/horas/diferido` — guarda turno diferido (CSRF).
- `GET /mi-empresa/crm/horas/listado` — listado admin con filtros.
- `POST /mi-empresa/crm/horas/{id}/anular` — anula con motivo obligatorio (CSRF).

## Integraciones cross-módulo
- **Agenda CRM** (`crm_agenda_eventos.origen_tipo='hora'`): cada turno cerrado proyecta un evento via `AgendaProyectorService::onHoraSaved`. Color teal `#20c997`. Si el operador tiene Google Calendar conectado, también se sincroniza arriba.
- **Tratativas** (Fase 4 pendiente): `crm_horas.tratativa_id` se setea al elegir tratativa al iniciar/cargar. La tratativa muestra horas trabajadas + totalizador.
- **Notificaciones** (Fase 5 pendiente): hooks `crm_horas.turno_olvidado`, `crm_horas.no_iniciaste`, `crm_horas.ajuste_admin`.
- **RXN Geo Tracking** (Fase futura): expone los turnos abiertos para que GeoTracking dibuje el mapa live de operadores.
- **RXN Live** (Fase futura): dataset `horas_trabajadas` para gráficos semanales y reportes.

## Seguridad
- **Multi-tenant**: TODA query filtra por `empresa_id`. Operador-scoped: las queries del turnero filtran también por `usuario_id`.
- **CSRF**: todos los POST validan token via `Controller::verifyCsrfOrAbort()`.
- **IDOR**: `findById` requiere `empresa_id`. Anular requiere que el id pertenezca a la empresa.
- **Validación**: solapamientos detectados en service. Motivo obligatorio para anulación.
- **Geo**: lat/lng son opcionales; si el browser deniega, se guarda turno con `geo_consent_*=0`. No se bloquea operación.

## Riesgos conocidos
- **Sin edición por operador post-cierre**: si se equivoca al cargar, tiene que pedirle a admin que ajuste. Mitigación: Fase 6 (admin con audit log) que permita edición controlada.
- **Inconsistencia geo solo flag**: hoy se marca pero no se actúa automáticamente. La revisión es manual por admin via la flag.
- **Sin retries del hook a Agenda**: si la proyección falla (DB caída, Google API caída), queda silenciosamente loggeado pero el turno se cierra OK. Rescan manual o futuro endpoint admin.
- **Contador en vivo aproximado**: el JS suma `Date.now() - tickStart` al base del server. Si el reloj del cliente está mal, el contador puede mostrar un valor distinto al real (server-side el dato siempre es correcto).

## Checklist post-cambio
- [ ] Migraciones corren sin errores en local.
- [ ] `/mi-empresa/crm/horas` carga el turnero, pide geo, muestra "Iniciar turno" verde.
- [ ] Iniciar turno funciona, queda abierto, contador empieza a correr.
- [ ] Cerrar turno (con confirm) funciona, contador queda fijo, aparece duración.
- [ ] Intentar abrir un segundo turno con uno abierto → error claro.
- [ ] Cargar diferido funciona y se guarda con `modo=diferido`.
- [ ] Solapamiento al cargar diferido → error.
- [ ] Carga diferida >24hs después → flag `inconsistencia_geo=1`.
- [ ] Turno cerrado aparece en `crm_agenda_eventos` con `origen_tipo='hora'` y color teal.
- [ ] Listado admin con filtros funciona.
- [ ] Mobile (<576px): botones grandes, layout one-column, sin overflow horizontal.
