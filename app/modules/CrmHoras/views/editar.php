<?php
$pageTitle = 'Editar turno #' . (int) $hora['id'];
$csrf = \App\Core\CsrfHelper::generateToken();
$flashErr = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);
$ownerNombre = $usuarios[(int) $hora['usuario_id']] ?? ('Usuario #' . (int) $hora['usuario_id']);

$startedIso = '';
$endedIso = '';
try { $startedIso = (new DateTimeImmutable((string) $hora['started_at']))->format('Y-m-d H:i:s'); } catch (\Throwable) {}
try { $endedIso = $hora['ended_at'] ? (new DateTimeImmutable((string) $hora['ended_at']))->format('Y-m-d H:i:s') : ''; } catch (\Throwable) {}

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
                    <input type="text" name="concepto" class="form-control" maxlength="255" value="<?= htmlspecialchars((string) ($hora['concepto'] ?? '')) ?>">
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
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
