<?php
/**
 * @var string $title
 * @var string|null $subtitle
 * @var string|null $iconClass
 * @var string|null $actionsHtml  (HTML for buttons)
 * @var string|null $backUrl
 * @var string|null $backLabel
 */

$title = $title ?? 'Módulo';
$subtitle = $subtitle ?? '';
$iconClass = $iconClass ?? '';
$actionsHtml = $actionsHtml ?? '';
$backUrl = $backUrl ?? '';
$backLabel = $backLabel ?? 'Volver';
$mode = $mode ?? 'standard';

$headerClasses = 'rxn-module-header ';
if ($mode === 'compact') {
    $headerClasses .= 'mb-2 pb-1 border-bottom border-secondary border-opacity-25';
} else {
    $headerClasses .= 'mb-3 pb-2 border-bottom border-secondary border-opacity-25';
}
?>
<div class="<?= $headerClasses ?>">
    <div>
        <h1 class="h3 fw-bold mb-1">
            <?php if ($iconClass): ?><i class="<?= htmlspecialchars($iconClass) ?> me-2"></i><?php endif; ?>
            <?= htmlspecialchars($title) ?>
        </h1>
        <?php if ($subtitle): ?>
            <p class="text-muted mb-0"><?= $subtitle ?></p>
        <?php endif; ?>
    </div>
    <div class="rxn-module-actions d-flex flex-wrap gap-2 align-items-center">
        <?= $actionsHtml ?>
        <?php if ($backUrl): ?>
            <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> <?= htmlspecialchars($backLabel) ?></a>
        <?php endif; ?>
    </div>
</div>
