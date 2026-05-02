<?php
/**
 * @var array $hora
 * @var string $ownerNombre
 * @var array $adjuntos
 * @var bool $esAdmin
 * @var bool $esDueno
 */
$pageTitle = 'Turno #' . (int) $hora['id'];
$csrf = \App\Core\CsrfHelper::generateToken();
$flashErr = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);
$flashOk  = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']);

$descSegs = (int) ($hora['descuento_segundos'] ?? 0);
$descHms = sprintf('%02d:%02d:%02d', intdiv($descSegs, 3600), intdiv($descSegs % 3600, 60), $descSegs % 60);

$durSegs = 0;
if (!empty($hora['ended_at'])) {
    try {
        $durSegs = max(0, (new DateTimeImmutable((string) $hora['ended_at']))->getTimestamp()
            - (new DateTimeImmutable((string) $hora['started_at']))->getTimestamp());
    } catch (\Throwable) {}
}
$durBruta = sprintf('%02d:%02d:%02d', intdiv($durSegs, 3600), intdiv($durSegs % 3600, 60), $durSegs % 60);
$durNeta  = sprintf('%02d:%02d:%02d', intdiv(max(0, $durSegs - $descSegs), 3600), intdiv(max(0, $durSegs - $descSegs) % 3600, 60), max(0, $durSegs - $descSegs) % 60);

ob_start();
?>
<div class="container py-3 crm-horas-shell">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 fw-bold mb-0"><i class="bi bi-stopwatch text-info"></i> Turno #<?= (int) $hora['id'] ?></h1>
            <div class="text-muted small">
                Operador: <strong><?= htmlspecialchars($ownerNombre) ?></strong> ·
                Modo: <?= htmlspecialchars((string) $hora['modo']) ?> ·
                Estado: <?= htmlspecialchars((string) $hora['estado']) ?>
            </div>
        </div>
        <a href="/mi-empresa/crm/horas" class="btn btn-outline-secondary btn-sm" data-rxn-back="/mi-empresa/crm/horas">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

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

    <!-- Resumen del turno -->
    <div class="card rxn-form-card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="text-muted small">Inicio</div>
                    <div class="fw-bold"><?= htmlspecialchars((new DateTime((string) $hora['started_at']))->format('d/m/Y H:i:s')) ?></div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="text-muted small">Fin</div>
                    <div class="fw-bold">
                        <?= !empty($hora['ended_at']) ? htmlspecialchars((new DateTime((string) $hora['ended_at']))->format('d/m/Y H:i:s')) : '<span class="text-warning">En curso</span>' ?>
                    </div>
                </div>
                <?php if ($durSegs > 0): ?>
                <div class="col-6 col-md-3">
                    <div class="text-muted small">Bruto</div>
                    <div class="fw-bold"><?= $durBruta ?></div>
                </div>
                <?php if ($descSegs > 0): ?>
                <div class="col-6 col-md-3">
                    <div class="text-muted small">Descuento</div>
                    <div class="fw-bold text-warning"><?= $descHms ?></div>
                </div>
                <?php endif; ?>
                <div class="col-6 col-md-3">
                    <div class="text-muted small">Neto</div>
                    <div class="fw-bold text-success"><?= $durNeta ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($hora['concepto'])): ?>
                <div class="col-12">
                    <div class="text-muted small">Concepto</div>
                    <div style="white-space: pre-line"><?= htmlspecialchars((string) $hora['concepto']) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($descSegs > 0 && !empty($hora['motivo_descuento'])): ?>
                <div class="col-12">
                    <div class="text-muted small">Motivo del descuento</div>
                    <div style="white-space: pre-line"><?= htmlspecialchars((string) $hora['motivo_descuento']) ?></div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($esAdmin): ?>
                <div class="mt-3 d-flex gap-2">
                    <a href="/mi-empresa/crm/horas/<?= (int) $hora['id'] ?>/editar" class="btn btn-warning btn-sm">
                        <i class="bi bi-pencil-square"></i> Editar (admin)
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Adjuntos -->
    <div class="card rxn-form-card">
        <div class="card-body">
            <h2 class="h6 fw-bold mb-3"><i class="bi bi-paperclip"></i> Adjuntos</h2>

            <?php if (empty($adjuntos)): ?>
                <div class="text-muted small mb-3">Sin adjuntos cargados.</div>
            <?php else: ?>
                <ul class="list-group list-group-flush mb-3">
                    <?php foreach ($adjuntos as $a): ?>
                        <li class="list-group-item d-flex align-items-center justify-content-between gap-2 px-0">
                            <div class="flex-grow-1">
                                <a href="/<?= htmlspecialchars((string) $a['path']) ?>" target="_blank" rel="noopener" class="fw-semibold text-decoration-none">
                                    <i class="bi bi-file-earmark"></i>
                                    <?= htmlspecialchars((string) ($a['original_name'] ?? 'archivo')) ?>
                                </a>
                                <div class="small text-muted">
                                    <?= htmlspecialchars((string) ($a['mime'] ?? '')) ?> ·
                                    <?= number_format(((int) ($a['size_bytes'] ?? 0)) / 1024, 1) ?> KB
                                </div>
                            </div>
                            <?php if ($esAdmin || $esDueno): ?>
                            <form method="POST" action="/mi-empresa/crm/horas/<?= (int) $hora['id'] ?>/adjuntos/<?= (int) $a['id'] ?>/borrar" onsubmit="return confirm('¿Borrar el adjunto?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Borrar">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <form method="POST" action="/mi-empresa/crm/horas/<?= (int) $hora['id'] ?>/adjuntos" enctype="multipart/form-data" class="d-flex gap-2 align-items-center flex-wrap">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="file" name="file" class="form-control flex-grow-1" required>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-cloud-upload"></i> Subir
                </button>
            </form>
            <div class="form-text small mt-2">
                PDF, Word, Excel, imágenes (máx. 100MB).
                Útil para certificados médicos, planillas, fotos del trabajo.
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
ob_start();
?>
<link rel="stylesheet" href="/css/crm-horas.css?v=<?= time() ?>">
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
