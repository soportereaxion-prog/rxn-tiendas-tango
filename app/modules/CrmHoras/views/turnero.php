<?php
$pageTitle = 'Turnero — Horas';
$csrf = \App\Core\CsrfHelper::generateToken();
$flashOk = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']);
$flashErr = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);

$abiertoStartIso = '';
if ($abierto) {
    try {
        $abiertoStartIso = (new DateTimeImmutable((string) $abierto['started_at']))->format('c');
    } catch (\Throwable) {}
}

ob_start();
?>
<div class="container py-3 crm-horas-shell">

    <?php if ($flashOk): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flashOk) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($flashErr): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flashErr) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 fw-bold mb-0"><i class="bi bi-stopwatch text-info"></i> Turnero</h1>
            <div class="text-muted small">Hoy <?= htmlspecialchars((new DateTime($today))->format('d/m/Y')) ?></div>
        </div>
        <a href="/mi-empresa/crm/dashboard" class="btn btn-outline-secondary btn-sm" title="Volver al CRM"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    <?php /* Contador del día — siempre visible */ ?>
    <div class="card rxn-form-card mb-3 text-center">
        <div class="card-body py-3">
            <div class="text-muted small mb-1">Hoy llevás trabajadas</div>
            <div class="fw-bold display-5" id="totalHoyDisplay" data-base-seg="<?= (int) $totalSeg ?>" data-abierto-iso="<?= htmlspecialchars($abiertoStartIso) ?>">00:00:00</div>
        </div>
    </div>

    <?php /* Botón principal: depende del estado actual */ ?>
    <div class="card rxn-form-card mb-3">
        <div class="card-body p-3 p-md-4">
            <?php if ($abierto): ?>
                <div class="text-center mb-3">
                    <div class="text-muted small">Turno abierto desde</div>
                    <div class="fw-bold fs-5"><?= htmlspecialchars((new DateTime((string) $abierto['started_at']))->format('H:i')) ?></div>
                    <?php if (!empty($abierto['concepto'])): ?>
                        <div class="text-secondary small mt-1"><?= htmlspecialchars((string) $abierto['concepto']) ?></div>
                    <?php endif; ?>
                </div>

                <form method="POST" action="/mi-empresa/crm/horas/cerrar" id="cerrarForm" class="d-grid">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="lat" value="" class="rxn-geo-lat">
                    <input type="hidden" name="lng" value="" class="rxn-geo-lng">
                    <input type="hidden" name="geo_consent" value="0" class="rxn-geo-consent">
                    <button type="submit" class="btn btn-danger btn-lg fw-bold py-3" id="btnCerrar" data-confirm="¿Cerrar el turno actual?">
                        <i class="bi bi-stop-circle"></i> Cerrar turno
                    </button>
                </form>
            <?php else: ?>
                <form method="POST" action="/mi-empresa/crm/horas/iniciar" id="iniciarForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="lat" value="" class="rxn-geo-lat">
                    <input type="hidden" name="lng" value="" class="rxn-geo-lng">
                    <input type="hidden" name="geo_consent" value="0" class="rxn-geo-consent">

                    <div class="mb-3">
                        <label class="form-label small">Concepto (opcional)</label>
                        <textarea name="concepto" class="form-control" placeholder="Ej: Visita técnica - Cliente X. Detalles del servicio, contexto, observaciones..." maxlength="2000" rows="3" autocomplete="off"></textarea>
                    </div>

                    <?php if (!empty($tratativas)): ?>
                    <div class="mb-3">
                        <label class="form-label small">Vincular a tratativa (opcional)</label>
                        <select name="tratativa_id" class="form-select">
                            <option value="">— ninguna —</option>
                            <?php foreach ($tratativas as $t): ?>
                                <option value="<?= (int) $t['id'] ?>">
                                    #<?= (int) $t['numero'] ?> — <?= htmlspecialchars((string) $t['titulo']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text small">Mostramos las 30 tratativas activas más recientes.</div>
                    </div>
                    <?php endif; ?>

                    <details class="mb-3">
                        <summary class="small text-muted">Aplicar descuento al tiempo (opcional)</summary>
                        <div class="row g-2 mt-2">
                            <div class="col-12 col-md-5">
                                <label class="form-label small">Descuento (HH:MM:SS)</label>
                                <input type="text" name="descuento" class="form-control" placeholder="00:00:00" pattern="^\d{1,3}:[0-5]?\d:[0-5]?\d$">
                            </div>
                            <div class="col-12 col-md-7">
                                <label class="form-label small">Motivo del descuento</label>
                                <textarea name="motivo_descuento" class="form-control" placeholder="Ej: Pausa larga, almuerzo, traslado no facturable..." rows="2"></textarea>
                            </div>
                            <div class="form-text small">Si cargás descuento, el motivo es obligatorio. Lo restamos del tiempo neto al cerrar.</div>
                        </div>
                    </details>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-success btn-lg fw-bold py-3" id="btnIniciar">
                            <i class="bi bi-play-circle"></i> Iniciar turno
                        </button>
                    </div>
                </form>
            <?php endif; ?>

            <div class="text-center mt-3">
                <small class="text-muted rxn-geo-status">
                    <i class="bi bi-geo-alt"></i> <span class="rxn-geo-label">Pidiendo ubicación…</span>
                </small>
            </div>
        </div>
    </div>

    <?php /* Acciones secundarias */ ?>
    <div class="d-flex gap-2 mb-4">
        <a href="/mi-empresa/crm/horas/diferido" class="btn btn-outline-info btn-sm flex-grow-1">
            <i class="bi bi-clock-history"></i> Cargar turno diferido
        </a>
        <a href="/mi-empresa/crm/horas/listado" class="btn btn-outline-secondary btn-sm flex-grow-1">
            <i class="bi bi-list-ul"></i> Ver listado
        </a>
    </div>

    <?php /* Lista del día */ ?>
    <h2 class="h6 fw-bold text-muted text-uppercase small mb-2">Turnos de hoy</h2>
    <div class="card rxn-form-card">
        <div class="list-group list-group-flush">
            <?php if (empty($turnos)): ?>
                <div class="list-group-item text-muted small text-center py-4">
                    Todavía no registraste turnos hoy.
                </div>
            <?php else: ?>
                <?php foreach ($turnos as $t): ?>
                    <?php
                    $start = (new DateTime((string) $t['started_at']))->format('H:i');
                    $end = $t['ended_at'] ? (new DateTime((string) $t['ended_at']))->format('H:i') : '—';
                    $duracion = '';
                    if ($t['ended_at']) {
                        try {
                            $sec = (new DateTimeImmutable((string) $t['ended_at']))->getTimestamp() - (new DateTimeImmutable((string) $t['started_at']))->getTimestamp();
                            $h = intdiv(max(0, $sec), 3600);
                            $m = intdiv(max(0, $sec) % 3600, 60);
                            $duracion = sprintf('%dh %02dm', $h, $m);
                        } catch (\Throwable) {}
                    }
                    $isOpen = $t['estado'] === 'abierto';
                    $isAnul = $t['estado'] === 'anulado';
                    $isDiferido = $t['modo'] === 'diferido';
                    ?>
                    <div class="list-group-item d-flex gap-2 align-items-center <?= $isAnul ? 'opacity-50' : '' ?>">
                        <div class="flex-grow-1">
                            <div class="fw-semibold">
                                <?= $start ?> <span class="text-muted">→</span> <?= $end ?>
                                <?php if ($duracion): ?>
                                    <span class="badge bg-secondary-subtle text-secondary ms-2"><?= $duracion ?></span>
                                <?php endif; ?>
                                <?php if ($isOpen): ?>
                                    <span class="badge bg-success ms-1">EN CURSO</span>
                                <?php endif; ?>
                                <?php if ($isAnul): ?>
                                    <span class="badge bg-danger ms-1">ANULADO</span>
                                <?php endif; ?>
                                <?php if ($isDiferido): ?>
                                    <span class="badge bg-warning text-dark ms-1">DIFERIDO</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($t['concepto'])): ?>
                                <div class="small text-muted" style="white-space: pre-line"><?= htmlspecialchars((string) $t['concepto']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($t['descuento_segundos']) && (int) $t['descuento_segundos'] > 0): ?>
                                <?php $ds = (int) $t['descuento_segundos']; ?>
                                <div class="small text-warning">
                                    <i class="bi bi-dash-circle"></i>
                                    Descuento: <?= sprintf('%02d:%02d:%02d', intdiv($ds, 3600), intdiv($ds % 3600, 60), $ds % 60) ?>
                                    <?php if (!empty($t['motivo_descuento'])): ?>· <?= htmlspecialchars((string) $t['motivo_descuento']) ?><?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($t['inconsistencia_geo']): ?>
                                <div class="small text-warning">
                                    <i class="bi bi-exclamation-triangle"></i> Geo de carga marcada para revisión
                                </div>
                            <?php endif; ?>
                        </div>
                        <a href="/mi-empresa/crm/horas/<?= (int) $t['id'] ?>" class="btn btn-outline-secondary btn-sm" title="Ver detalle / Adjuntos">
                            <i class="bi bi-paperclip"></i>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
window.RXN_HORAS_HAS_OPEN = <?= $abierto ? 'true' : 'false' ?>;
</script>

<?php
$content = ob_get_clean();
ob_start();
?>
<link rel="stylesheet" href="/css/crm-horas.css?v=<?= time() ?>">
<script src="/js/crm-horas-turnero.js?v=<?= time() ?>"></script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
