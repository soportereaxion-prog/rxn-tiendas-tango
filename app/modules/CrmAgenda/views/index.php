<?php
use App\Core\View;

$pageTitle = 'Agenda CRM - rxn_suite';
$authMode = $authMode ?? 'usuario';
$authActive = $authActive ?? null;

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
            <a href="<?= htmlspecialchars($basePath) ?>/crear" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo Evento</a>
        </div>
    </div>

    <?php if (($flashSuccess = \App\Core\Flash::get('success')) !== null): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <?= htmlspecialchars($flashSuccess) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (($flashDanger = \App\Core\Flash::get('danger')) !== null): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <?= htmlspecialchars($flashDanger) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (($flashWarning = \App\Core\Flash::get('warning')) !== null): ?>
        <div class="alert alert-warning alert-dismissible fade show shadow-sm" role="alert">
            <?= htmlspecialchars($flashWarning) ?>
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
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card bg-dark text-light border-0 shadow-sm h-100">
                <div class="card-header border-bottom border-secondary border-opacity-25">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-google"></i> Google Calendar</h5>
                </div>
                <div class="card-body">
                    <?php if ($authActive !== null): ?>
                        <p class="mb-2">
                            <span class="badge bg-success"><i class="bi bi-check-circle"></i> Conectado</span>
                        </p>
                        <p class="small text-muted mb-1">Cuenta: <strong><?= htmlspecialchars((string) ($authActive['google_email'] ?? '-')) ?></strong></p>
                        <p class="small text-muted mb-1">Modo: <strong><?= htmlspecialchars($authMode) ?></strong></p>
                        <?php if (!empty($authActive['last_sync_at'])): ?>
                            <p class="small text-muted mb-2">Último push: <?= htmlspecialchars((string) $authActive['last_sync_at']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($authActive['last_error'])): ?>
                            <div class="alert alert-warning small p-2 mb-2"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars((string) $authActive['last_error']) ?></div>
                        <?php endif; ?>
                        <form action="<?= htmlspecialchars($basePath) ?>/google/disconnect" method="POST" class="rxn-confirm-form" data-msg="¿Desconectar Google Calendar? Los eventos ya sincronizados NO se borran.">
                            <button type="submit" class="btn btn-sm btn-outline-danger w-100"><i class="bi bi-box-arrow-right"></i> Desconectar</button>
                        </form>
                    <?php else: ?>
                        <p class="small text-muted mb-3">Conectá tu cuenta de Google para que los eventos del CRM aparezcan en tu Google Calendar automáticamente (sync push-only).</p>
                        <a href="<?= htmlspecialchars($basePath) ?>/google/connect" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-link-45deg"></i> Conectar con Google</a>
                        <p class="small text-muted mt-2 mb-0">
                            Requiere configurar <code>GOOGLE_CLIENT_ID</code>, <code>GOOGLE_CLIENT_SECRET</code> y <code>GOOGLE_REDIRECT_URI</code> en el <code>.env</code>.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card bg-dark text-light border-0 shadow-sm">
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

    const activeOrigenes = () => {
        const boxes = document.querySelectorAll('.origen-filter:checked');
        return Array.from(boxes).map(b => b.value);
    };

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
            info.el.title = (props.descripcion || '') + '\n' + (props.usuario_nombre || '') + syncMark;
        }
    });

    calendar.render();

    document.querySelectorAll('.origen-filter').forEach(cb => {
        cb.addEventListener('change', () => calendar.refetchEvents());
    });

    const refreshBtn = document.getElementById('btn-refresh');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => calendar.refetchEvents());
    }
})();
</script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
