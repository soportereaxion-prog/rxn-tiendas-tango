<?php
use App\Core\Helpers\UIHelper;
use App\Core\View;

$pageTitle = $pageTitle ?? 'Admin - rxn_suite';
$ui = isset($environmentLabel) ? compact('environmentLabel', 'dashboardPath') : [];
if (isset($topbarLeftHtml)) {
    $ui['topbarLeftHtml'] = $topbarLeftHtml;
}
?>
<!DOCTYPE html>
<html lang="es" <?= UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="/css/rxn-theming.css?v=<?= time() ?>" rel="stylesheet">
    <?= $extraHead ?? '' ?>
</head>
<body class="d-flex flex-column min-vh-100 rxn-launcher-shell pt-2">
    
    <!-- Topbar global del admin (Banner de usuario) -->
    <?php View::render('app/shared/views/components/backoffice_user_banner.php', $ui); ?>

    <!-- Contenido principal -->
    <main class="container-fluid flex-grow-1 px-4 mb-4" style="max-width: 1400px;">
        <?php 
        $usePageHeader = $usePageHeader ?? false;
        $headerMode = $headerMode ?? 'standard';
        
        if ($usePageHeader && !in_array($headerMode, ['none', 'custom'], true)) {
            View::render('app/shared/views/partials/page_header.php', [
                'title' => $pageHeaderTitle ?? $pageTitle ?? 'Módulo',
                'subtitle' => $pageHeaderSubtitle ?? '',
                'iconClass' => $pageHeaderIcon ?? '',
                'actionsHtml' => $pageHeaderActions ?? '',
                'backUrl' => $pageHeaderBackUrl ?? '',
                'backLabel' => $pageHeaderBackLabel ?? 'Volver',
                'mode' => $headerMode
            ]);
        }
        ?>
        <?= $content ?? '' ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/js/rxn-confirm-modal.js"></script>
    <script src="/js/rxn-row-links.js"></script>
    <?= $extraScripts ?? '' ?>
    <script src="/js/rxn-shortcuts.js"></script>
</body>
</html>
