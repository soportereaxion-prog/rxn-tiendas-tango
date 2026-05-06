# Release 1.47.0 — Matriz de permisos modulares (empresa + usuario) + UX click-to-center en GeoTracking

**Fecha**: 2026-05-06
**Build**: 20260506.2
**Iteración**: 51

## Qué se hizo

### Fase 1 — Empresa (super admin contrata módulos)

7 nuevas columnas TINYINT en `empresas` con DEFAULT 0:
- `crm_modulo_pedidos_servicio`
- `crm_modulo_agenda`
- `crm_modulo_mail_masivos`
- `crm_modulo_horas_turnero`
- `crm_modulo_geo_tracking`
- `crm_modulo_presupuestos_pwa`
- `crm_modulo_horas_pwa`

UI en `/empresas/:id/editar` y `/empresas/crear` con los 7 switches en el bloque CRM, con dependencia en cascada del switch padre. `EmpresaAccessService` extendido con `hasCrmXxxAccess()` y `requireCrmXxxAccess()` para cada uno. Persistencia en repository + service.

### Fase 2 — Usuario (admin de empresa asigna módulos)

11 columnas TINYINT en `usuarios` con DEFAULT 1 (todos los users existentes y nuevos arrancan con todo habilitado):
- `usuario_modulo_notas`, `_llamadas`, `_monitoreo`, `_rxn_live`
- `usuario_modulo_pedidos_servicio`, `_agenda`, `_mail_masivos`, `_horas_turnero`, `_geo_tracking`, `_presupuestos_pwa`, `_horas_pwa`

Tabla nueva `usuarios_modulos_audit` + trigger MySQL `AFTER UPDATE` que captura cambios y registra `flags_before` / `flags_after` en JSON con atribución vía `@audit_user_id` (seteado por `UsuarioRepository::save` antes del UPDATE desde `$_SESSION`).

Service nuevo `App\Modules\Auth\UserModuleAccessService` con:
- `current()` — usuario logueado cacheado.
- `userHas(string $key): bool` — chequea flag con bypass automático para `es_admin` / `es_rxn_admin`.
- `requireUserAccess(string $key, string $label)` — rebota 403 con mensaje friendly.
- `flags(): array` — útil para vistas (launcher PWA).

UI en `Usuarios/views/editar.php` y `crear.php`: bloque "Módulos habilitados" con los 11 toggles, **filtrados por contratado de empresa** (solo aparecen los que la empresa contrata), `disabled` si el editor no es admin.

Defensa server-side en `UsuarioService::applyModuleFlags()`: aunque alguien manipule el HTML y mande flags por POST, el service descarta los cambios si `!canManageAdminPrivileges()`.

### Fase 3 — PWA dinámico

`RxnPwaController::launcher()` ahora construye `$pwaApps` dinámicamente filtrando por `EmpresaAccessService::hasCrmXxxPwaAccess()` Y `UserModuleAccessService::userHas()`. Cards de Presupuestos PWA y Horas PWA aparecen solo si ambos chequeos pasan. Si no hay módulos disponibles, mensaje friendly invitando a contactar al admin de empresa.

11 endpoints PWA endurecidos con doble compuerta (los métodos de Presupuestos llaman `$this->requirePresupuestosPwa()`, los de Horas llaman `$this->requireHorasPwa()`).

### Fase 4 — Endurecer módulos en runtime

5 controllers operativos con doble compuerta (empresa + usuario):
- **CrmAgenda** (`AgendaController` con helper `requireAgendaAccess()`).
- **CrmHoras turnero** (`HoraController` con helper `requireHorasTurneroAccess()`).
- **RxnGeoTracking** (`RxnGeoTrackingController` y `RxnGeoTrackingConfigController` con helper `requireGeoTrackingAccess()`). Consent y Report quedan sin chequeo de módulo (cualquier user logueado reporta su propia posición).
- **CrmMailMasivos** (`MailMasivosDashboardController`, `TemplateController`, `ReportController`, `JobController` con `requireCrmMailMasivosAccess()` + `requireUserAccess('mail_masivos', ...)` inline). `TrackingController` NO tocado (webhooks externos sin login).
- **CrmPedidosServicio** (`PedidoServicioController` con helper `requirePedidosServicioAccess()`).

### Bugfix — recursión infinita en helpers

Durante la Fase 4, el `replace_all` que usé para reemplazar `AuthService::requireLogin();` por `$this->requireXxxAccess();` también pisó la línea **adentro del helper**, dejando que el helper se llamara a sí mismo. Stack depth 512 frames antes de que Xdebug abortara. Charly lo cazó al entrar a `/mi-empresa/geo-tracking`.

Archivos rotos: `AgendaController`, `RxnGeoTrackingController`, `RxnGeoTrackingConfigController`, `PedidoServicioController`. Fix: restituir la línea original (`AuthService::requireLogin()` o `requireBackofficeAdmin()`) adentro de cada helper.

`HoraController` y `RxnPwaController` zafaron porque sus replace_all eran de patrones distintos al contenido del helper. CrmMailMasivos no usó helper, replace inline.

**Lección**: cuando uso `replace_all` con un patrón que coincide con el contenido del helper, agregar el helper DESPUÉS del replace_all, o usar patrones específicos que no matcheen el contenido del helper.

### Bugfix — toggle "Usuario activo" anti-auto-baja

Bug pre-existente que el feature destapó: el toggle `Usuario activo` en `Usuarios/views/editar.php` era editable libremente.
- Un usuario podía auto-desactivarse y perder su sesión.
- Un operador no-admin podía desactivar a otros usuarios.

Fix doble:
- **UI**: input `disabled` con mensaje claro si self-edit O editor no admin.
- **Server**: `UsuarioService::update()` solo procesa el flag `activo` si `!isSelfEdit && canManageAdminPrivileges()`. Si no, preserva el valor del DB.

### Fase 5 — UX bonus: click-to-center en GeoTracking

Pedido de Charly al cierre. Dashboard de `/mi-empresa/geo-tracking` ahora:
- Filas con `lat`/`lng` son clickables (cursor pointer + hover azul + barrita lateral + estado activo en azul más fuerte).
- Click → `map.panTo()` + zoom 16 + scroll suave al mapa + abre popup del marker correspondiente.
- Filas sin lat/lng (denied/error) NO son clickables.
- Si el evento está fuera del límite de 500 puntos del mapa, popup mínimo construido desde data-attrs del row.

JS refactor: `markerById = new Map()` indexa markers por id de evento; `pointById` cachea los datos del point para reconstruir popup; `focusEvent()` centraliza la lógica; `wireTableRowClicks()` vincula clicks.

## Por qué

- Hasta ahora los toggles eran solo a nivel empresa: si una empresa contrataba CRM, todos sus usuarios veían y usaban todo. Faltaba granularidad por usuario.
- Charly necesita poder asignar módulos a usuarios específicos desde "Administrar cuentas". Un operador de Mail Masivos no debería ver Geo Tracking; un técnico de campo debería tener Horas PWA pero no Mail Masivos.
- Cascada limpia: super admin contrata → admin de empresa asigna → operador usa solo lo asignado.

## Impacto

- **Para cliente final**: empresa nueva contratada arranca con todos los módulos del CRM apagados. Activación explícita por el super admin.
- **Para usuarios existentes**: todos arrancan con todos los módulos del CRM habilitados (DEFAULT 1). Cero regresión funcional. El admin de empresa los va apagando si quiere restringir.
- **Para el operador**: si el admin le quita un módulo, deja de aparecer en el menú lateral (vía `EmpresaAccessService::hasXxxAccess()` que ya respetaban los menús). Y si intenta entrar por URL directa, rebota con 403 friendly.

## Decisiones tomadas

1. **Persistencia: columnas TINYINT en `usuarios` (no tabla relacional)**. Charly eligió esta opción para mantener consistencia con el patrón ya existente en `empresas`. Audit/histórico se cubre con trigger MySQL.
2. **Default 1 para usuarios**: cuando se habilita un módulo a nivel empresa, todos los usuarios existentes lo ven automáticamente. Más práctico para empresas chicas. Si quieren restringir, lo hacen explícito.
3. **Bypass automático para `es_admin` y `es_rxn_admin`**: el admin de empresa ve y usa todos los módulos contratados sin tildarse a sí mismo. El super admin ve todo siempre.
4. **Los 11 módulos van en la matriz por usuario, no solo los 7 nuevos**. Charly priorizó consistencia: si un módulo es contratado, debe poder asignarse o quitarse por usuario, sin importar si es viejo o nuevo.

## Validación

- `php -l` limpio en los 19 archivos tocados (controllers + services + repositories + vistas + migraciones).
- `node -e` syntax check del JS de GeoTracking dashboard.
- Migraciones corridas idempotentes en local sin warnings:
  - `2026_05_06_03_add_modulos_extendidos_to_empresas.php`
  - `2026_05_06_04_add_modulos_usuarios_audit_trigger.php`
- Charly testeó en browser:
  - `/empresas/:id/editar` → los 7 switches nuevos aparecen y guardan OK.
  - `/mi-empresa/usuarios/:id/editar` → bloque "Módulos habilitados" aparece con toggles filtrados por empresa.
  - Bug del toggle "Usuario activo" auto-edit detectado por Charly y fixeado.
  - Bug de recursión infinita en GeoTracking detectado por Charly y fixeado.
  - Click-to-center del mapa funcionando.

## Pendiente para próxima iteración

- **Auditoría formal de seguridad — punto B**: 5 módulos NO cubiertos en iteración 50 (RxnLive, Notifications, Drafts, RxnGeoTracking, CrmHoras turnero PWA).
- **Mapping ASVS L2** consolidado en `docs/seguridad/`.
- Más permisos modulares si Charly los pide.

## Relevant Files

- `database/migrations/2026_05_06_03_add_modulos_extendidos_to_empresas.php` — 7 columnas TINYINT en empresas.
- `database/migrations/2026_05_06_04_add_modulos_usuarios_audit_trigger.php` — 11 columnas + audit + trigger.
- `database/migrations/2026_05_06_05_seed_customer_notes_release_1_47_0.php` — nota visible al cliente.
- `app/modules/Empresas/{Empresa.php,EmpresaAccessService.php,EmpresaRepository.php,EmpresaService.php}` — modelo + access + repo + service extendidos.
- `app/modules/Empresas/views/{editar.php,crear.php}` — 7 switches nuevos.
- `app/modules/Auth/{Usuario.php,UsuarioRepository.php,UserModuleAccessService.php}` — modelo + repo + service nuevo.
- `app/modules/Usuarios/{UsuarioService.php,UsuarioController.php,views/editar.php,views/crear.php}` — UI permisos + bugfix activo.
- `app/modules/RxnPwa/{RxnPwaController.php,views/launcher.php}` — launcher dinámico + helpers + 11 endpoints endurecidos.
- `app/modules/CrmAgenda/AgendaController.php` — helper `requireAgendaAccess`.
- `app/modules/CrmHoras/HoraController.php` — helper `requireHorasTurneroAccess`.
- `app/modules/RxnGeoTracking/{RxnGeoTrackingController.php,RxnGeoTrackingConfigController.php}` — helper `requireGeoTrackingAccess`.
- `app/modules/CrmMailMasivos/{MailMasivosDashboardController,TemplateController,ReportController,JobController}.php` — doble guard inline.
- `app/modules/CrmPedidosServicio/PedidoServicioController.php` — helper `requirePedidosServicioAccess`.
- `app/modules/RxnGeoTracking/views/dashboard.php` + `public/js/rxn-geo-tracking-dashboard.js` — UX click-to-center.
- `app/config/version.php` — bump 1.47.0 / build 20260506.2.
