<?php
$pageTitle = 'Monitor Envío #' . ($job['id'] ?? '?') . ' - rxn_suite';
ob_start();
$flash = \App\Core\Flash::get();
$job = $job ?? [];
$items = $items ?? [];
$tracking = $tracking ?? ['opens' => 0, 'clicks' => 0, 'unique_openers' => 0, 'unique_clickers' => 0];

$estadoBadge = static function (string $estado): array {
    return match ($estado) {
        'queued'    => ['bg-secondary', 'bi-hourglass-split', 'En cola'],
        'running'   => ['bg-primary', 'bi-arrow-repeat', 'Enviando'],
        'paused'    => ['bg-warning text-dark', 'bi-pause-circle', 'Pausado'],
        'completed' => ['bg-success', 'bi-check-circle-fill', 'Completado'],
        'cancelled' => ['bg-warning text-dark', 'bi-x-circle', 'Cancelado'],
        'failed'    => ['bg-danger', 'bi-exclamation-triangle-fill', 'Error'],
        default     => ['bg-light text-dark', 'bi-question-circle', $estado],
    };
};
$itemBadge = static function (string $estado): array {
    return match ($estado) {
        'pending' => ['bg-secondary', 'Pendiente'],
        'sent'    => ['bg-success', 'Enviado'],
        'failed'  => ['bg-danger', 'Fallido'],
        'skipped' => ['bg-warning text-dark', 'Saltado'],
        default   => ['bg-light text-dark', $estado],
    };
};

[$jBadgeClass, $jIcon, $jEstadoLabel] = $estadoBadge((string) $job['estado']);
$total = (int) $job['total_destinatarios'];
$ok = (int) $job['total_enviados'];
$fail = (int) $job['total_fallidos'];
$skp = (int) $job['total_skipped'];
$done = $ok + $fail + $skp;
$pct = $total > 0 ? (int) floor($done * 100 / $total) : 0;
$isFinal = in_array($job['estado'], ['completed', 'cancelled', 'failed'], true);
?>
<link rel="stylesheet" href="/css/mail-masivos-envios.css">

<div class="container-fluid mt-4 mb-5 rxn-responsive-container">
    <div class="rxn-module-header mb-3">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-activity"></i> Monitor de Envío #<?= (int) $job['id'] ?></h2>
            <p class="text-muted mb-0 small">Disparado el <?= htmlspecialchars((string) $job['created_at']) ?></p>
        </div>
        <div class="rxn-module-actions">
            <a href="/mi-empresa/crm/mail-masivos/envios" class="btn btn-outline-secondary btn-sm" title="Volver al listado de Envíos"><i class="bi bi-arrow-left"></i> Volver</a>
            <?php if (!$isFinal): ?>
                <?php
                    // Si está en queued con cancel_flag=1 → es un zombie (webhook falló,
                    // user canceló, job esperando batch que nunca llega). El backend lo cierra
                    // directo ahora con closeQueuedAsCancelled, sin esperar.
                    $isZombie = ($job['estado'] === 'queued' && !empty($job['cancel_flag']));
                ?>
                <form method="post" action="/mi-empresa/crm/mail-masivos/envios/<?= (int) $job['id'] ?>/cancelar" class="d-inline" onsubmit="return confirm('¿Cancelar este envío? Los mails ya enviados quedan enviados; los pendientes se saltan.');">
                    <?= \App\Core\CsrfHelper::input() ?>
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <?php if ($isZombie): ?>
                            <i class="bi bi-x-octagon-fill"></i> Destrabar y cerrar
                        <?php elseif (!empty($job['cancel_flag'])): ?>
                            <i class="bi bi-hourglass-split"></i> Cancelando...
                        <?php else: ?>
                            <i class="bi bi-x-circle"></i> Cancelar envío
                        <?php endif; ?>
                    </button>
                </form>
            <?php elseif (in_array($job['estado'], ['cancelled', 'failed'], true)): ?>
                <form method="post" action="/mi-empresa/crm/mail-masivos/envios/<?= (int) $job['id'] ?>/reactivar" class="d-inline" onsubmit="return confirm('¿Reactivar este envío? Los destinatarios que quedaron como saltados vuelven a pendientes y se intenta el envío de nuevo.');">
                    <?= \App\Core\CsrfHelper::input() ?>
                    <button type="submit" class="btn btn-outline-success btn-sm">
                        <i class="bi bi-arrow-clockwise"></i> Reactivar envío
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($flash): ?>
        <?php
            $flashClass = match ($flash['type'] ?? 'info') {
                'success' => 'alert-success',
                'error', 'danger' => 'alert-danger',
                'warning' => 'alert-warning',
                default => 'alert-info',
            };
        ?>
        <div class="alert <?= $flashClass ?> py-2 small"><?= nl2br(htmlspecialchars($flash['message'] ?? '')) ?></div>
    <?php endif; ?>

    <!-- Cabecera con info del job -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-md-5">
                    <div class="text-muted small mb-1">Asunto</div>
                    <div class="fw-semibold"><?= htmlspecialchars((string) $job['asunto']) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small mb-1">Fuentes</div>
                    <?php if (!empty($job['report_nombre'])): ?>
                        <div><i class="bi bi-diagram-3"></i> <?= htmlspecialchars((string) $job['report_nombre']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($job['template_nombre'])): ?>
                        <div><i class="bi bi-file-earmark-text"></i> <?= htmlspecialchars((string) $job['template_nombre']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-3 text-md-end">
                    <div class="text-muted small mb-1">Estado</div>
                    <span class="badge <?= $jBadgeClass ?> fs-6" id="job-estado-badge">
                        <i class="bi <?= $jIcon ?>"></i> <?= htmlspecialchars($jEstadoLabel) ?>
                    </span>
                </div>
            </div>

            <?php if (!empty($job['mensaje_error'])): ?>
                <div class="alert alert-danger mt-3 mb-0 small">
                    <strong><i class="bi bi-exclamation-triangle"></i> Error:</strong>
                    <?= htmlspecialchars((string) $job['mensaje_error']) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Progreso -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fw-bold mb-0"><i class="bi bi-graph-up"></i> Progreso</h6>
                <small class="text-muted" id="progreso-texto"><?= $done ?> de <?= $total ?> (<?= $pct ?>%)</small>
            </div>
            <div class="progress" style="height: 22px;">
                <div class="progress-bar bg-success" role="progressbar" id="bar-ok"
                     style="width: <?= $total > 0 ? ($ok * 100 / $total) : 0 ?>%" title="Enviados">
                    <?= $ok > 0 ? $ok : '' ?>
                </div>
                <div class="progress-bar bg-danger" role="progressbar" id="bar-fail"
                     style="width: <?= $total > 0 ? ($fail * 100 / $total) : 0 ?>%" title="Fallidos">
                    <?= $fail > 0 ? $fail : '' ?>
                </div>
                <div class="progress-bar bg-warning" role="progressbar" id="bar-skp"
                     style="width: <?= $total > 0 ? ($skp * 100 / $total) : 0 ?>%" title="Saltados">
                    <?= $skp > 0 ? $skp : '' ?>
                </div>
            </div>

            <div class="row text-center mt-3 g-2">
                <div class="col"><div class="rxn-envios-stat"><div class="num text-muted" id="stat-pending"><?= $total - $done ?></div><div class="lbl">Pendientes</div></div></div>
                <div class="col"><div class="rxn-envios-stat"><div class="num text-success" id="stat-ok"><?= $ok ?></div><div class="lbl">Enviados</div></div></div>
                <div class="col"><div class="rxn-envios-stat"><div class="num text-danger" id="stat-fail"><?= $fail ?></div><div class="lbl">Fallidos</div></div></div>
                <div class="col"><div class="rxn-envios-stat"><div class="num text-warning" id="stat-skp"><?= $skp ?></div><div class="lbl">Saltados</div></div></div>
                <div class="col"><div class="rxn-envios-stat"><div class="num"><?= $total ?></div><div class="lbl">Total</div></div></div>
            </div>
        </div>
    </div>

    <!-- Tracking (Fase 5): aperturas + clicks -->
    <?php
        $openRate = $ok > 0 ? round((int) $tracking['unique_openers'] * 100 / $ok, 1) : 0;
        $clickRate = $ok > 0 ? round((int) $tracking['unique_clickers'] * 100 / $ok, 1) : 0;
    ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-eye-fill"></i> Tracking</h6>
                <small class="text-muted">Aperturas y clicks de los destinatarios</small>
            </div>
            <div class="row text-center g-2">
                <div class="col">
                    <div class="rxn-envios-stat">
                        <div class="num text-info"><?= (int) $tracking['opens'] ?></div>
                        <div class="lbl">Aperturas totales</div>
                    </div>
                </div>
                <div class="col">
                    <div class="rxn-envios-stat">
                        <div class="num text-info"><?= (int) $tracking['unique_openers'] ?></div>
                        <div class="lbl">Únicos que abrieron</div>
                    </div>
                </div>
                <div class="col">
                    <div class="rxn-envios-stat">
                        <div class="num text-primary"><?= (int) $tracking['clicks'] ?></div>
                        <div class="lbl">Clicks totales</div>
                    </div>
                </div>
                <div class="col">
                    <div class="rxn-envios-stat">
                        <div class="num text-primary"><?= (int) $tracking['unique_clickers'] ?></div>
                        <div class="lbl">Únicos que clickearon</div>
                    </div>
                </div>
                <div class="col">
                    <div class="rxn-envios-stat">
                        <div class="num"><?= $openRate ?>%</div>
                        <div class="lbl">Open rate</div>
                    </div>
                </div>
                <div class="col">
                    <div class="rxn-envios-stat">
                        <div class="num"><?= $clickRate ?>%</div>
                        <div class="lbl">Click rate</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de items -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                <h6 class="fw-bold mb-0"><i class="bi bi-list-ul"></i> Destinatarios (primeros 200)</h6>
                <small class="text-muted">
                    <span class="rxn-envios-poll-dot" id="poll-dot"></span>
                    <span id="poll-hint">
                        <?= $isFinal ? 'Cerrado — no refresca' : 'Actualiza cada 3 seg' ?>
                    </span>
                </small>
            </div>

            <?php if (empty($items)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-inbox" style="font-size: 2rem; opacity: 0.4;"></i>
                    <p class="mt-2 mb-0 small">No hay items cargados todavía.</p>
                </div>
            <?php else: ?>
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40px;">#</th>
                            <th>Email</th>
                            <th>Nombre</th>
                            <th>Estado</th>
                            <th class="text-center" title="Aperturas">👁</th>
                            <th class="text-center" title="Clicks">🔗</th>
                            <th>Enviado</th>
                            <th>Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it): ?>
                            <?php [$ibClass, $ibLabel] = $itemBadge((string) $it['estado']); ?>
                            <tr>
                                <td class="text-muted small">#<?= (int) $it['id'] ?></td>
                                <td class="small"><?= htmlspecialchars((string) $it['recipient_email']) ?></td>
                                <td class="small text-muted"><?= htmlspecialchars((string) ($it['recipient_name'] ?? '')) ?: '—' ?></td>
                                <td><span class="badge <?= $ibClass ?>"><?= htmlspecialchars($ibLabel) ?></span></td>
                                <td class="text-center small">
                                    <?php $opens = (int) ($it['opens'] ?? 0); ?>
                                    <?php if ($opens > 0): ?>
                                        <span class="badge bg-info"><?= $opens ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center small">
                                    <?php $clicks = (int) ($it['clicks'] ?? 0); ?>
                                    <?php if ($clicks > 0): ?>
                                        <span class="badge bg-primary"><?= $clicks ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= htmlspecialchars((string) ($it['sent_at'] ?? '')) ?: '—' ?></td>
                                <td class="small text-danger" style="max-width: 260px;">
                                    <?= !empty($it['error_msg']) ? htmlspecialchars((string) $it['error_msg']) : '' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
window.MailEnviosMonitor = {
    jobId: <?= (int) $job['id'] ?>,
    apiStatus: '/mi-empresa/crm/mail-masivos/envios/<?= (int) $job['id'] ?>/status',
    isFinal: <?= $isFinal ? 'true' : 'false' ?>,
    total: <?= $total ?>,
};
</script>
<script src="/js/mail-masivos-envios-monitor.js" defer></script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
