<?php
$pageTitle = 'Editar turno #' . (int) $hora['id'];
$csrf = \App\Core\CsrfHelper::generateToken();
$flashErr = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);
$flashOk = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']);
$ownerNombre = $usuarios[(int) $hora['usuario_id']] ?? ('Usuario #' . (int) $hora['usuario_id']);

$startedIso = '';
$endedIso = '';
try { $startedIso = (new DateTimeImmutable((string) $hora['started_at']))->format('Y-m-d H:i:s'); } catch (\Throwable) {}
try { $endedIso = $hora['ended_at'] ? (new DateTimeImmutable((string) $hora['ended_at']))->format('Y-m-d H:i:s') : ''; } catch (\Throwable) {}

// Helper: segundos -> HH:MM:SS
$segs = (int) ($hora['descuento_segundos'] ?? 0);
$descHms = sprintf('%02d:%02d:%02d', intdiv($segs, 3600), intdiv($segs % 3600, 60), $segs % 60);

// Adjuntos del turno (atttachments con owner_type='crm_hora').
$adjuntos = [];
try {
    $attachmentService = new \App\Core\Services\AttachmentService();
    $adjuntos = $attachmentService->listByOwner((int) $hora['empresa_id'], 'crm_hora', (int) $hora['id']);
} catch (\Throwable) {}

ob_start();
?>
<div class="container py-3 crm-horas-shell">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 fw-bold mb-0"><i class="bi bi-pencil-square text-warning"></i> Editar turno #<?= (int) $hora['id'] ?></h1>
            <div class="text-muted small">Operador: <strong><?= htmlspecialchars($ownerNombre) ?></strong> · Modo: <?= htmlspecialchars((string) $hora['modo']) ?> · Estado: <?= htmlspecialchars((string) $hora['estado']) ?></div>
        </div>
        <a href="/mi-empresa/crm/horas/listado" class="btn btn-outline-secondary btn-sm" data-rxn-back="/mi-empresa/crm/horas/listado">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <?php if ($flashErr): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flashErr) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($flashOk): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flashOk) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="alert alert-warning small">
        <i class="bi bi-shield-exclamation"></i>
        Estás editando un turno como <strong>administrador</strong>. El cambio queda registrado en el audit log y se le notifica al dueño del turno.
    </div>

    <div class="card rxn-form-card">
        <div class="card-body p-3 p-md-4">
            <form method="POST" action="/mi-empresa/crm/horas/<?= (int) $hora['id'] ?>/editar">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label small">Inicio</label>
                        <input type="datetime-local" name="started_at" class="form-control" value="<?= htmlspecialchars($startedIso) ?>" required>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small">Fin <span class="text-muted">(vacío = abierto)</span></label>
                        <input type="datetime-local" name="ended_at" class="form-control" value="<?= htmlspecialchars($endedIso) ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small">Concepto</label>
                    <textarea name="concepto" class="form-control" maxlength="2000" rows="3"><?= htmlspecialchars((string) ($hora['concepto'] ?? '')) ?></textarea>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-12 col-md-5">
                        <label class="form-label small">Descuento (HH:MM:SS)</label>
                        <input type="text" name="descuento" class="form-control" value="<?= htmlspecialchars($descHms) ?>" pattern="^\d{1,3}:[0-5]?\d:[0-5]?\d$">
                    </div>
                    <div class="col-12 col-md-7">
                        <label class="form-label small">Motivo del descuento</label>
                        <textarea name="motivo_descuento" class="form-control" rows="2" placeholder="Ej: pausa larga, almuerzo, traslado no facturable..."><?= htmlspecialchars((string) ($hora['motivo_descuento'] ?? '')) ?></textarea>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small">Motivo de la edición <span class="text-danger">*</span></label>
                    <textarea name="motivo" class="form-control" rows="2" required placeholder="Ej: corrección de hora de cierre olvidada"></textarea>
                    <div class="form-text small">Obligatorio. Queda en el audit log.</div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-warning btn-lg">
                        <i class="bi bi-check-circle"></i> Guardar edición
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Adjuntos -->
    <div class="card rxn-form-card mt-3">
        <div class="card-body p-3 p-md-4">
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
                            <form method="POST" action="/mi-empresa/crm/horas/<?= (int) $hora['id'] ?>/adjuntos/<?= (int) $a['id'] ?>/borrar" onsubmit="return confirm('¿Borrar el adjunto?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Borrar">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <form method="POST" action="/mi-empresa/crm/horas/<?= (int) $hora['id'] ?>/adjuntos" enctype="multipart/form-data" class="d-flex gap-2">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="file" name="file" class="form-control" required>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-cloud-upload"></i> Subir
                </button>
            </form>
            <div class="form-text small mt-1">PDF, Word, Excel, imágenes (máx. 100MB). Útil para certificados médicos, planillas, fotos del trabajo.</div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
