<?php
use App\Core\Helpers\UIHelper;
use App\Core\View;

$ui = isset($environmentLabel) ? compact('environmentLabel', 'dashboardPath') : [];
if (isset($topbarLeftHtml)) {
    $ui['topbarLeftHtml'] = $topbarLeftHtml;
}

$empresaId = \App\Core\Context::getEmpresaId();
$faviconUrl = null;
$siteTitle = 'RXN Suite';

if ($empresaId) {
    try {
        $empresaObj = (new \App\Modules\Empresas\EmpresaRepository())->findById($empresaId);
        $faviconUrl = $empresaObj->favicon_url ?? null;
        if (!empty($empresaObj->titulo_pestana)) {
            $siteTitle = (string)$empresaObj->titulo_pestana;
        }
    } catch (\Throwable $th) {
        // Ignorar si hay error de DB o de import
    }
}

// Si la vista todavía tiene código residual, lo ignoramos para construir el title limpio.
if (isset($pageTitle) && in_array($pageTitle, ['RXN Suite', 'RXN Suite'])) {
    $finalPageTitle = $siteTitle;
} else {
    $finalPageTitle = isset($pageTitle) ? ($pageTitle . ' - ' . $siteTitle) : $siteTitle;
}

?>
<!DOCTYPE html>
<html lang="es" <?= UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if ($empresaId): ?>
    <meta name="rxn-empresa-id" content="<?= (int) $empresaId ?>">
    <?php endif; ?>
    <title><?= htmlspecialchars($finalPageTitle) ?></title>
    <?php if ($faviconUrl): ?>
        <link rel="icon" href="<?= htmlspecialchars($faviconUrl) ?>">
    <?php endif; ?>
    <!-- Persistencia global de filtros de listado (corre antes del render para evitar flash) -->
    <script src="/js/rxn-filter-persistence.js?v=<?= time() ?>"></script>
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
    <script src="/js/rxn-spotlight.js?v=<?= time() ?>"></script>
    <?= $extraScripts ?? '' ?>
    <script src="/js/rxn-shortcuts.js"></script>
    <script>
    // Table scroll indicator for mobile
    document.querySelectorAll('.rxn-table-responsive').forEach(function(el) {
        function checkScroll() {
            el.classList.toggle('is-scrollable', el.scrollWidth > el.clientWidth + 4);
        }
        checkScroll();
        el.addEventListener('scroll', function() {
            el.classList.toggle('is-scrollable',
                el.scrollLeft < el.scrollWidth - el.clientWidth - 4);
        });
        window.addEventListener('resize', checkScroll);
    });
    </script>
</body>
</html>
