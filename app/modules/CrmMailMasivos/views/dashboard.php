<?php
$pageTitle = 'Mail Masivos - rxn_suite';
ob_start();
?>
<div class="container-fluid mt-2 mb-5 rxn-responsive-container">
    <div class="rxn-module-header mb-4">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-envelope-paper-fill"></i> Mail Masivos</h2>
            <p class="text-muted mb-0">Diseñar reportes de destinatarios, armar plantillas HTML y disparar envíos masivos procesados por n8n.</p>
        </div>
        <a href="/mi-empresa/crm/dashboard" class="btn btn-outline-secondary">← Volver a CRM</a>
    </div>

    <div class="row g-4">
        <?php foreach ($cards as $card): ?>
            <div class="col-sm-6 col-lg-4">
                <div class="card rxn-module-card text-center p-4 h-100 position-relative shadow-sm <?= $card['link'] === null ? 'opacity-75' : '' ?>">
                    <?php if (!empty($card['badge'])): ?>
                        <span class="badge bg-warning text-dark position-absolute top-0 end-0 m-2">
                            <?= htmlspecialchars($card['badge']) ?>
                        </span>
                    <?php endif; ?>
                    <div class="card-body d-flex flex-column align-items-center justify-content-center p-0">
                        <div class="rxn-module-icon text-primary">
                            <i class="bi <?= htmlspecialchars($card['icon']) ?>"></i>
                        </div>
                        <h5 class="fw-bold mb-2"><?= htmlspecialchars($card['title']) ?></h5>
                        <p class="text-muted small px-2 mb-0"><?= htmlspecialchars($card['desc']) ?></p>
                        <?php if ($card['link']): ?>
                            <a href="<?= htmlspecialchars($card['link']) ?>" class="stretched-link"></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
