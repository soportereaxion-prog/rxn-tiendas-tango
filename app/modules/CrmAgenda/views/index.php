<?php
use App\Core\View;

$pageTitle = 'Agenda CRM - rxn_suite';
$authMode = $authMode ?? 'usuario';
$authActive = $authActive ?? null;
$authEmpresa = $authEmpresa ?? null;
$authConfigured = $authConfigured ?? false;
$missingFields = $missingFields ?? [];
$empresaConfig = $empresaConfig ?? ['client_id' => '', 'redirect_uri' => '', 'auth_mode' => 'usuario'];
$usuariosCrm = $usuariosCrm ?? [];

ob_start();
?>

<main class="container-fluid flex-grow-1 px-4 mb-5" style="max-width: 1600px;">
    <div class="rxn-module-header mb-4 pb-3 border-bottom border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h3 fw-bold mb-1"><i class="bi bi-calendar-event"></i> Agenda CRM</h1>
            <p class="text-muted small mb-0">Eventos de PDS, Presupuestos y Tratativas en un solo calendario, con sync push a Google.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= htmlspecialchars($dashboardPath ?? '/') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
            <button type="button" id="btn-fullscreen" class="btn btn-outline-info" title="Pantalla completa (Alt+A)"><i class="bi bi-arrows-fullscreen"></i></button>
            <a href="<?= htmlspecialchars($basePath) ?>/crear" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo Evento</a>
        </div>
    </div>

    <?php $flash = \App\Core\Flash::get(); ?>
    <?php if ($flash): ?>
        <div class="alert alert-<?= htmlspecialchars((string) $flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= htmlspecialchars((string) $flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card bg-dark text-light border-0 shadow-sm">
                <div class="card-header border-bottom border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-funnel"></i> Filtros</h5>
                    <div class="small text-muted">
                        Modo de conexión Google: <span class="badge bg-secondary"><?= htmlspecialchars($authMode) ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-3 align-items-center">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input origen-filter" type="checkbox" value="pds" id="filter_pds" checked>
                            <label class="form-check-label" for="filter_pds">
                                <span class="badge" style="background:#0d6efd;">PDS</span>
                            </label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input origen-filter" type="checkbox" value="presupuesto" id="filter_pres" checked>
                            <label class="form-check-label" for="filter_pres">
                                <span class="badge" style="background:#198754;">Presupuestos</span>
                            </label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input origen-filter" type="checkbox" value="tratativa" id="filter_trat" checked>
                            <label class="form-check-label" for="filter_trat">
                                <span class="badge" style="background:#ffc107;color:#000;">Tratativas</span>
                            </label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input origen-filter" type="checkbox" value="llamada" id="filter_lla" checked>
                            <label class="form-check-label" for="filter_lla">
                                <span class="badge" style="background:#6610f2;">Llamadas</span>
                            </label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input origen-filter" type="checkbox" value="manual" id="filter_man" checked>
                            <label class="form-check-label" for="filter_man">
                                <span class="badge" style="background:#6c757d;">Manuales</span>
                            </label>
                        </div>
                        <button type="button" id="btn-refresh" class="btn btn-sm btn-outline-info ms-auto"><i class="bi bi-arrow-clockwise"></i> Refrescar</button>
                    </div>

                    <?php if ($usuariosCrm !== []): ?>
                        <hr class="border-secondary my-3">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <span class="small text-muted me-1"><i class="bi bi-people"></i> Operadores:</span>
                            <?php foreach ($usuariosCrm as $usr): ?>
                                <div class="form-check form-check-inline m-0">
                                    <input class="form-check-input usuario-filter" type="checkbox" value="<?= (int) $usr['id'] ?>" id="filter_user_<?= (int) $usr['id'] ?>" checked>
                                    <label class="form-check-label" for="filter_user_<?= (int) $usr['id'] ?>">
                                        <span class="badge rounded-pill" style="background:<?= htmlspecialchars($usr['color_calendario'] ?? '#007bff') ?>;color:#fff;"><?= htmlspecialchars($usr['nombre'] ?? 'Usuario') ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card bg-dark text-light border-0 shadow-sm mb-3">
                <div class="card-header border-bottom border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-google"></i> Google Calendar</h5>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#googleConfigPanel">
                        <i class="bi bi-gear"></i>
                    </button>
                </div>

                <div class="collapse <?= !$authConfigured ? 'show' : '' ?>" id="googleConfigPanel">
                    <div class="card-body border-bottom border-secondary border-opacity-25">
                        <form action="<?= htmlspecialchars($basePath) ?>/google/config" method="POST">
                            <p class="small text-muted mb-2">Credenciales OAuth de <a href="https://console.cloud.google.com/" target="_blank" rel="noopener" class="text-info">Google Cloud Console</a>:</p>
                            <div class="mb-2">
                                <label class="form-label small text-muted mb-0">Client ID</label>
                                <input type="text" name="google_oauth_client_id" class="form-control form-control-sm bg-dark text-light border-secondary"
                                    value="<?= htmlspecialchars($empresaConfig['client_id']) ?>"
                                    placeholder="xxxxx.apps.googleusercontent.com">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small text-muted mb-0">Client Secret</label>
                                <input type="password" name="google_oauth_client_secret" class="form-control form-control-sm bg-dark text-light border-secondary"
                                    placeholder="<?= $empresaConfig['client_secret'] !== '' ? 'Guardado. Dejá vacío para mantener.' : 'GOCSPX-xxxx' ?>">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small text-muted mb-0">Redirect URI</label>
                                <input type="text" name="google_oauth_redirect_uri" class="form-control form-control-sm bg-dark text-light border-secondary"
                                    value="<?= htmlspecialchars($empresaConfig['redirect_uri']) ?>"
                                    placeholder="https://tudominio/mi-empresa/crm/agenda/google/callback">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small text-muted mb-0">Modo de sync</label>
                                <select name="agenda_google_auth_mode" class="form-select form-select-sm bg-dark text-light border-secondary">
                                    <option value="usuario" <?= $authMode === 'usuario' ? 'selected' : '' ?>>Por usuario (cada operador conecta su cuenta)</option>
                                    <option value="empresa" <?= $authMode === 'empresa' ? 'selected' : '' ?>>Por empresa (un calendario compartido)</option>
                                    <option value="ambos" <?= $authMode === 'ambos' ? 'selected' : '' ?>>Ambos (empresa + cada usuario)</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-check-lg"></i> Guardar configuración</button>
                        </form>
                    </div>
                </div>

                <div class="card-body">
                    <?php if (!$authConfigured): ?>
                        <div class="alert alert-warning small p-2 mb-2 border-0">
                            <i class="bi bi-exclamation-triangle"></i> <strong>Configuración pendiente</strong>
                        </div>
                        <p class="small text-muted mb-0">Completá las credenciales de Google OAuth arriba (click en <i class="bi bi-gear"></i>) para habilitar el sync.</p>
                    <?php elseif ($authActive !== null): ?>
                        <p class="mb-2">
                            <span class="badge bg-success"><i class="bi bi-check-circle"></i> Conectado</span>
                            <span class="badge bg-secondary ms-1"><?= htmlspecialchars($authMode) ?></span>
                        </p>
                        <p class="small text-muted mb-1">Cuenta: <strong><?= htmlspecialchars((string) ($authActive['google_email'] ?? '-')) ?></strong></p>
                        <?php if (!empty($authActive['last_sync_at'])): ?>
                            <p class="small text-muted mb-2">Último push: <?= htmlspecialchars((string) $authActive['last_sync_at']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($authActive['last_error'])): ?>
                            <div class="alert alert-warning small p-2 mb-2 border-0"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars((string) $authActive['last_error']) ?></div>
                        <?php endif; ?>
                        <form action="<?= htmlspecialchars($basePath) ?>/google/disconnect" method="POST" class="rxn-confirm-form" data-msg="¿Desconectar tu cuenta de Google Calendar? Los eventos ya sincronizados NO se borran de Google.">
                            <button type="submit" class="btn btn-sm btn-outline-danger w-100"><i class="bi bi-box-arrow-right"></i> Desconectar mi cuenta</button>
                        </form>
                        <?php if ($authMode === 'ambos' && $authEmpresa !== null): ?>
                            <hr class="border-secondary my-2">
                            <p class="small text-muted mb-1"><i class="bi bi-building"></i> Empresa: <strong><?= htmlspecialchars((string) ($authEmpresa['google_email'] ?? '-')) ?></strong></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="small text-muted mb-3">Conectá tu cuenta para que los eventos del CRM aparezcan en tu Google Calendar (sync push-only).</p>
                        <a href="<?= htmlspecialchars($basePath) ?>/google/connect" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-link-45deg"></i> Conectar con Google</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end mb-2">
        <form action="<?= htmlspecialchars($basePath) ?>/rescan" method="POST" class="rxn-confirm-form" data-msg="¿Escanear PDS, Presupuestos y Tratativas existentes para proyectarlos en la Agenda? Puede tardar unos segundos.">
            <button type="submit" class="btn btn-sm btn-outline-warning"><i class="bi bi-arrow-repeat"></i> Rescan histórico</button>
        </form>
    </div>

    <div class="card bg-dark text-light border-0 shadow-sm" id="calendar-container">
        <div class="card-body p-3">
            <div id="rxn-agenda-calendar"></div>
        </div>
    </div>
</main>

<?php
$content = ob_get_clean();

ob_start();
?>
<style>
    /* Fullscreen mode */
    .agenda-fullscreen {
        position: fixed !important;
        top: 0; left: 0; right: 0; bottom: 0;
        z-index: 9999;
        background: var(--bg-color, #121212);
        overflow-y: auto;
        padding: 1rem;
        border-radius: 0 !important;
    }
    .agenda-fullscreen .card-body { padding: 1rem !important; }
    .agenda-fullscreen-active { overflow: hidden; } /* body */

    /* FullCalendar dark theme touches */
    #rxn-agenda-calendar { color: #f8f9fa; }
    #rxn-agenda-calendar .fc {
        --fc-border-color: rgba(255,255,255,0.15);
        --fc-page-bg-color: transparent;
        --fc-neutral-bg-color: rgba(255,255,255,0.03);
        --fc-list-event-hover-bg-color: rgba(255,255,255,0.05);
        --fc-today-bg-color: rgba(13, 110, 253, 0.15);
    }
    #rxn-agenda-calendar .fc-toolbar-title { color: #fff; font-weight: 700; }
    #rxn-agenda-calendar .fc-col-header-cell-cushion,
    #rxn-agenda-calendar .fc-daygrid-day-number { color: #f8f9fa; text-decoration: none; }
    #rxn-agenda-calendar .fc-button {
        background: #343a40;
        border-color: #495057;
        color: #f8f9fa;
    }
    #rxn-agenda-calendar .fc-button:hover { background: #495057; }
    #rxn-agenda-calendar .fc-button-active { background: #0d6efd !important; border-color: #0d6efd !important; }
    #rxn-agenda-calendar .fc-event { cursor: pointer; font-size: 0.85rem; padding: 2px 4px; }
</style>

<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<script>
(function () {
    const calendarEl = document.getElementById('rxn-agenda-calendar');
    if (!calendarEl || typeof FullCalendar === 'undefined') {
        return;
    }

    const activeOrigenes = () => Array.from(document.querySelectorAll('.origen-filter:checked')).map(b => b.value);
    const activeUsuarios = () => Array.from(document.querySelectorAll('.usuario-filter:checked')).map(b => b.value);

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'es',
        firstDay: 1,
        height: 'auto',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
        },
        buttonText: {
            today: 'Hoy',
            month: 'Mes',
            week: 'Semana',
            day: 'Día',
            list: 'Lista'
        },
        events: function (fetchInfo, successCallback, failureCallback) {
            const params = new URLSearchParams({
                start: fetchInfo.startStr,
                end: fetchInfo.endStr,
            });
            activeOrigenes().forEach(o => params.append('origenes[]', o));
            activeUsuarios().forEach(u => params.append('usuarios[]', u));

            fetch('<?= htmlspecialchars($basePath) ?>/events?' + params.toString())
                .then(r => r.json())
                .then(data => successCallback(data.data || []))
                .catch(err => failureCallback(err));
        },
        eventClick: function (info) {
            const props = info.event.extendedProps || {};
            const origen = props.origen_tipo || 'manual';
            const origenId = props.origen_id || 0;

            // Si el evento viene de otro modulo, lo abrimos en su modulo de origen
            if (origen === 'pds' && origenId > 0) {
                window.location.href = '/mi-empresa/crm/pedidos-servicio/' + origenId + '/editar';
                return;
            }
            if (origen === 'presupuesto' && origenId > 0) {
                window.location.href = '/mi-empresa/crm/presupuestos/' + origenId + '/editar';
                return;
            }
            if (origen === 'tratativa' && origenId > 0) {
                window.location.href = '/mi-empresa/crm/tratativas/' + origenId;
                return;
            }
            // Evento manual: abrir edit propio de agenda
            window.location.href = '<?= htmlspecialchars($basePath) ?>/' + info.event.id + '/editar';
        },
        dateClick: function (info) {
            // Crear un evento manual prellenado con la fecha clickeada
            const startIso = info.dateStr;
            window.location.href = '<?= htmlspecialchars($basePath) ?>/crear?start=' + encodeURIComponent(startIso);
        },
        eventDidMount: function (info) {
            const props = info.event.extendedProps || {};
            const syncMark = props.sync === 'synced' ? ' ☁' : '';
            const tipo = (props.origen_tipo || 'manual').toUpperCase();
            info.el.title = tipo + ' | ' + (props.usuario_nombre || 'Sin asignar') + syncMark + '\n' + (props.descripcion || '');

            // Reforzar el borde izquierdo grueso con el color del tipo de origen
            if (props.color_tipo && info.el) {
                info.el.style.borderLeft = '4px solid ' + props.color_tipo;
            }
        }
    });

    calendar.render();

    document.querySelectorAll('.origen-filter, .usuario-filter').forEach(cb => {
        cb.addEventListener('change', () => calendar.refetchEvents());
    });

    const refreshBtn = document.getElementById('btn-refresh');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => calendar.refetchEvents());
    }

    // Fullscreen toggle
    const container = document.getElementById('calendar-container');
    const btnFs = document.getElementById('btn-fullscreen');
    function toggleFullscreen() {
        if (!container) return;
        const isFs = container.classList.toggle('agenda-fullscreen');
        document.body.classList.toggle('agenda-fullscreen-active', isFs);
        if (btnFs) {
            btnFs.innerHTML = isFs ? '<i class="bi bi-fullscreen-exit"></i>' : '<i class="bi bi-arrows-fullscreen"></i>';
        }
        // FullCalendar necesita recalcular tamaño
        setTimeout(() => calendar.updateSize(), 100);
    }
    if (btnFs) { btnFs.addEventListener('click', toggleFullscreen); }

    // Alt+A shortcut
    document.addEventListener('keydown', (e) => {
        if (e.altKey && (e.key === 'a' || e.key === 'A')) {
            e.preventDefault();
            toggleFullscreen();
        }
        // Escape sale del fullscreen
        if (e.key === 'Escape' && container && container.classList.contains('agenda-fullscreen')) {
            e.preventDefault();
            toggleFullscreen();
        }
    });
})();
</script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
