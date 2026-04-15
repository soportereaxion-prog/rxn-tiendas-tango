<?php
$pageTitle = 'Envíos Masivos - rxn_suite';
ob_start();
$flash = \App\Core\Flash::get();

// Helpers para badges de estado
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
?>
<link rel="stylesheet" href="/css/mail-masivos-envios.css">

<div class="container mt-5 mb-5 rxn-responsive-container">
    <div class="rxn-module-header mb-4">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-send-fill"></i> Envíos Masivos</h2>
            <p class="text-muted mb-0">Disparar envíos de mail a partir de un reporte + plantilla, monitorear progreso y cancelar si hace falta.</p>
        </div>
        <div class="rxn-module-actions">
            <a href="/mi-empresa/crm/mail-masivos" class="btn btn-outline-secondary">← Volver</a>
            <a href="/mi-empresa/crm/mail-masivos/envios/crear" class="btn btn-primary fw-bold">
                <i class="bi bi-rocket-takeoff"></i> Nuevo Envío
            </a>
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

    <form method="get" class="mb-4">
        <div class="input-group" style="max-width: 480px;">
            <input type="search" name="search" class="form-control" placeholder="Buscar por asunto, reporte o plantilla..." value="<?= htmlspecialchars($search ?? '') ?>">
            <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i></button>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($jobs)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-send" style="font-size: 2.5rem; opacity: 0.4;"></i>
                    <p class="mt-3 mb-1">Todavía no disparaste ningún envío.</p>
                    <p class="small">Armá el primero — reporte + plantilla + click y a la cola.</p>
                </div>
            <?php else: ?>
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Asunto</th>
                            <th>Reporte / Plantilla</th>
                            <th>Estado</th>
                            <th class="text-center">Progreso</th>
                            <th>Disparado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jobs as $j): ?>
                            <?php [$badgeClass, $icon, $estadoLabel] = $estadoBadge((string) $j['estado']); ?>
                            <?php
                                $total = (int) $j['total_destinatarios'];
                                $ok = (int) $j['total_enviados'];
                                $fail = (int) $j['total_fallidos'];
                                $pct = $total > 0 ? (int) floor(($ok + $fail) * 100 / $total) : 0;
                            ?>
                            <tr>
                                <td class="text-muted">#<?= (int) $j['id'] ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars((string) $j['asunto']) ?></td>
                                <td class="small text-muted">
                                    <?php if (!empty($j['report_nombre'])): ?>
                                        <div><i class="bi bi-diagram-3"></i> <?= htmlspecialchars((string) $j['report_nombre']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($j['template_nombre'])): ?>
                                        <div><i class="bi bi-file-earmark-text"></i> <?= htmlspecialchars((string) $j['template_nombre']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $badgeClass ?>">
                                        <i class="bi <?= $icon ?>"></i> <?= htmlspecialchars($estadoLabel) ?>
                                    </span>
                                    <?php if (!empty($j['cancel_flag'])): ?>
                                        <div><small class="text-warning">🚫 cancelación solicitada</small></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="rxn-envios-progress-mini">
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar" role="progressbar" style="width: <?= $pct ?>%"></div>
                                        </div>
                                        <small class="text-muted">
                                            <?= $ok + $fail ?>/<?= $total ?>
                                            <?php if ($fail > 0): ?>
                                                · <span class="text-danger"><?= $fail ?> fail</span>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </td>
                                <td class="text-muted small"><?= htmlspecialchars((string) $j['created_at']) ?></td>
                                <td class="text-end">
                                    <a href="/mi-empresa/crm/mail-masivos/envios/<?= (int) $j['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-activity"></i> Monitor
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
