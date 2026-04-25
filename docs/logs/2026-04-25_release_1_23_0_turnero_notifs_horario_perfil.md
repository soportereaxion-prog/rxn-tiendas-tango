# Release 1.23.0 — Turnero CRM + Notificaciones in-app + Horario laboral + polish Mi Perfil

**Fecha**: 2026-04-25
**Build**: 20260425.1

## Qué se hizo

Release grande con tres features nuevas y polish responsive:

### 1. Módulo CrmHoras (Turnero CRM, mobile-first)
- Tabla `crm_horas` + auditoría `crm_horas_audit`.
- `HoraRepository` / `HoraService` / `HoraController` / `HoraAuditRepository` / `HoraNotificationDispatcher`.
- Vistas: `turnero.php` (operador, mobile-first), `diferido.php` (carga post-facto), `index.php` (admin).
- Frontend: `public/js/crm-horas-turnero.js` (geo + contador en vivo), `public/css/crm-horas.css` (estilos one-column con botones de 56px).
- Reflejo automático en `crm_agenda_eventos` con `origen_tipo='hora'` (color teal `#20c997`) via `AgendaProyectorService::onHoraSaved`.
- `MODULE_CONTEXT.md` con decisiones de diseño y checklist post-cambio.
- Migraciones: `2026_04_24_02_create_crm_horas`, `2026_04_24_03_alter_agenda_eventos_add_hora_origen`, `2026_04_24_04_create_crm_horas_audit`.

### 2. Sistema de Notificaciones in-app
- `App\Core\Services\NotificationService` (servicio core, transversal a toda la suite).
- `App\Modules\Notifications\NotificationController` + vista `index.php` (inbox con filtros all/unread/read).
- Componente reusable `app/shared/views/components/notifications_bell.php` (campanita en topbar global con badge rojo).
- Frontend: `public/js/rxn-notifications.js` (hidrata dropdown y mark-as-read) + `public/css/rxn-notifications.css`.
- Tabla `notifications` (multi-tenant + soft-delete + dedupeKey 24hs anti-spam).
- Hooks integrados desde admin_layout: `HoraNotificationDispatcher::checkAndNotify` corre en cada request del CRM.
- `MODULE_CONTEXT.md` con convención de naming de tipos (`<modulo>.<accion>`).
- Migración: `2026_04_24_00_create_notifications`.

### 3. Horario Laboral por usuario
- `App\Modules\Usuarios\UsuarioHorarioLaboralRepository` con bloques por día (replace-all).
- Endpoint `POST /mi-perfil/horario` (`UsuarioPerfilController::guardarHorario`).
- Vista en `mi_perfil.php`: tabla con bloques L-D + flags `notif_no_iniciaste_activa` y `minutos_tolerancia_olvido`.
- Migración: `2026_04_24_01_create_usuario_horario_laboral`.

### 4. Polish responsive (sesión actual)

**a) Gate SMTP de Mail Masivos por admin** (PC + mobile):
- `UsuarioPerfilController::index()` calcula `$canSeeMailMasivos = AuthService::hasAdminPrivileges()`. Solo carga `smtpConfig` si tiene permiso.
- `guardar()` ignora campos `smtp_*` para no-admin (defensa server-side).
- `testSmtp()` devuelve 403 para no-admin.
- Vista `mi_perfil.php` envuelve el bloque SMTP con `<?php if ($canSeeMailMasivos): ?>`.
- **Razón**: los operadores no necesitan SMTP propio y mostrarles ese form era ruido visual + exposición innecesaria, especialmente en celu.

**b) Horario laboral → cards apiladas en `<768px`**:
- Nuevo `public/css/mi-perfil.css` cargado vía `$extraHead`.
- Media query transforma `<table class="rxn-horario-table">` en bloque-por-día: thead oculto, cada `<tr>` se renderiza como card con borde redondeado, los time inputs usan `flex: 1 1 40%` para acomodarse cómodos, el botón "+ Bloque" queda full-width abajo de cada día.
- **Razón**: la tabla original con `min-width: 640px` requería scroll horizontal en celu para llegar al botón de agregar bloque. Inviable para operadores que cargan su horario desde el smartphone.

**c) Input "minutos de tolerancia" sin `max-width` fijo en mobile**:
- Mismo CSS, override `max-width: none !important` en `<768px`. Ocupa el ancho del card en lugar de quedar chico a la izquierda.

**d) Campanita dropdown responsive en `<400px`**:
- `rxn-notifications.css`: media query relaja `min-width` a `calc(100vw - 1.5rem)` y reduce `max-height` de items a `60vh`.
- **Razón**: el `min-width: 340px` del inline original se pegaba al borde derecho en iPhone SE / Android mini con `dropdown-menu-end`.

### 5. Centro de Ayuda extendido
- Cuatro secciones nuevas en `app/modules/Help/views/operational_help.php`:
  - **Horas (Turnero CRM)** (CRM-only): iniciar/cerrar, geo opcional, diferido, cruce medianoche, reflejo en agenda, listado admin, anular.
  - **Notificaciones** (global): campanita, inbox, filtros, anti-duplicado, tipos disponibles.
  - **Uso desde el celular** (global): menú hamburguesa, turnero mobile-first, tips generales.
  - **Mi Perfil** ampliado con horario laboral y SMTP solo admins.
- Índice y sección Novedades sumando los 2 ítems clave.

## Por qué

- **Operadores móviles**: la mayoría del equipo opera desde el celular en visitas/instalaciones. El turnero, las notificaciones y el horario laboral son piezas que tienen que funcionar bien desde un smartphone, no como afterthought.
- **Reducción de ruido**: el SMTP de Mail Masivos no era para operadores. Mostrarlo generaba friction visual y la posibilidad de tocar configuraciones que no aplicaban.
- **Documentación viva**: la ayuda operativa estaba quedando atrás respecto de lo que se construía. Esta release la pone al día y agrega una sección dedicada a uso mobile.

## Impacto

- **Funcional nuevo**: turnero CRM operativo, sistema de notificaciones in-app, horario laboral declarado.
- **UX mobile**: perfil usable end-to-end desde el celular para operadores.
- **Seguridad**: gate del SMTP en server-side (no solo visual) — un operador no puede modificar SMTP por POST manual.

## Decisiones tomadas

- **Gate SMTP por `hasAdminPrivileges()`** en lugar de un permiso granular nuevo. Si más adelante hay vendedores no-admin que necesiten SMTP propio, se suma un flag dedicado en `usuarios`. Decisión simple y reversible.
- **Cards-per-day para el horario en mobile** vía CSS puro (sin duplicar markup). Una sola fuente de verdad del DOM, override por media query.
- **Min-width campanita = `calc(100vw - 1.5rem)` en `<400px`** en lugar de un valor fijo. Se adapta al viewport real.

## Validación

- [ ] Operador (sin admin): entra a `/mi-perfil?area=crm`, NO ve sección SMTP, ve horario laboral.
- [ ] Admin: entra a `/mi-perfil?area=crm`, ve TODO (incluido SMTP).
- [ ] Mobile (<768px): horario laboral se renderiza como cards apiladas, sin scroll horizontal.
- [ ] Mobile (<400px): campanita dropdown no se sale del viewport.
- [ ] POST manual de `smtp_*` con user no-admin → ignorado en server (no se persiste).
- [ ] `POST /mi-perfil/smtp/test` con user no-admin → 403.

## Pendiente / siguientes pasos

- Fase 4 de CrmHoras: panel de horas trabajadas + totalizador en el detalle de Tratativa.
- Fase 5: hooks adicionales de notificaciones (`crm_horas.ajuste_admin`, `crm_tratativas.proxima_a_vencer`).
- Eventual flag granular `usuarios.puede_enviar_masivos` si aparecen vendedores no-admin que necesiten SMTP propio.
