<?php

$backofficeUserSummary = \App\Shared\Services\BackofficeContextService::currentUserSummary();
$backofficeUserName = (string) ($backofficeUserSummary['userName'] ?? 'Usuario');
$backofficeEmpresaNombre = trim((string) ($backofficeUserSummary['empresaNombre'] ?? ''));
$backofficeStoreUrl = $backofficeUserSummary['storeUrl'] ?? null;
$backofficeStoreLabel = (string) ($backofficeUserSummary['storeLabel'] ?? '');
?>
<div class="d-flex flex-column align-items-start align-items-lg-end gap-2">
    <div class="small fw-semibold text-secondary text-lg-end">
        <i class="bi bi-person-circle"></i>
        Hola, <?= htmlspecialchars($backofficeUserName) ?>
        <?php if ($backofficeEmpresaNombre !== ''): ?>
            <span class="text-muted d-block d-lg-inline">| <?= htmlspecialchars($backofficeEmpresaNombre) ?></span>
        <?php endif; ?>
    </div>
    <?php if (is_string($backofficeStoreUrl) && $backofficeStoreUrl !== ''): ?>
        <a href="<?= htmlspecialchars($backofficeStoreUrl) ?>" class="btn btn-sm btn-outline-info" target="_blank" rel="noopener noreferrer">
            <i class="bi bi-box-arrow-up-right"></i> Abrir tienda
        </a>
    <?php else: ?>
        <span class="small text-muted text-lg-end"><?= htmlspecialchars($backofficeStoreLabel) ?></span>
    <?php endif; ?>
</div>
