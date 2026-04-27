<?php
$pageTitle = 'Cargar turno diferido';
$csrf = \App\Core\CsrfHelper::generateToken();
$flashErr = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);
ob_start();
?>
<div class="container py-3 crm-horas-shell">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 fw-bold mb-0"><i class="bi bi-clock-history text-warning"></i> Cargar turno diferido</h1>
            <div class="text-muted small">Para turnos trabajados sin haber registrado en vivo.</div>
        </div>
        <a href="/mi-empresa/crm/horas" class="btn btn-outline-secondary btn-sm" title="Volver al turnero" data-rxn-back="/mi-empresa/crm/horas">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <?php if ($flashErr): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flashErr) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="alert alert-info small">
        <i class="bi bi-info-circle"></i>
        Estás cargando un turno <strong>a posteriori</strong>. El sistema va a guardar dónde estás <strong>ahora</strong> (no donde trabajaste). Si la diferencia es grande, queda marcado para revisión.
    </div>

    <div class="card rxn-form-card">
        <div class="card-body p-3 p-md-4">
            <form method="POST" action="/mi-empresa/crm/horas/diferido">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="lat" value="" class="rxn-geo-lat">
                <input type="hidden" name="lng" value="" class="rxn-geo-lng">
                <input type="hidden" name="geo_consent" value="0" class="rxn-geo-consent">

                <?php if (!empty($esAdmin) && !empty($usuariosTenant)): ?>
                <div class="mb-3">
                    <label class="form-label small">
                        <i class="bi bi-person-gear text-info"></i>
                        Cargar turno para
                    </label>
                    <select name="target_user_id" class="form-select">
                        <option value="0">— Yo mismo —</option>
                        <?php foreach ($usuariosTenant as $uid => $unombre): ?>
                            <?php if ((int) $uid === (int) ($_SESSION['user_id'] ?? 0)) continue; ?>
                            <option value="<?= (int) $uid ?>"><?= htmlspecialchars($unombre) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text small">
                        Solo visible para administradores. Cualquier carga en nombre de otro queda registrada en el audit log y se le notifica al usuario.
                    </div>
                </div>
                <?php endif; ?>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label small">Inicio</label>
                        <input type="datetime-local" name="started_at" class="form-control" required>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small">Fin</label>
                        <input type="datetime-local" name="ended_at" class="form-control" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small">Concepto (opcional)</label>
                    <input type="text" name="concepto" class="form-control" placeholder="Ej: Visita técnica - Cliente X" maxlength="255">
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
                </div>
                <?php endif; ?>

                <div class="text-center mb-3">
                    <small class="text-muted rxn-geo-status">
                        <i class="bi bi-geo-alt"></i> <span class="rxn-geo-label">Pidiendo ubicación…</span>
                    </small>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-check-circle"></i> Guardar turno
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
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
