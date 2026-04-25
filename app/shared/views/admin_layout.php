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
    <meta name="csrf-token" content="<?= htmlspecialchars(\App\Core\CsrfHelper::generateToken()) ?>">
    <?php
    // Geo-tracking: si hay un evento pendiente de reportar posición (típicamente
    // el login recién hecho, o la creación de un presupuesto/tratativa/PDS que
    // dejó el ID en sesión antes de redirigir), lo inyectamos como meta tag y
    // lo consumimos una sola vez. El JS rxn-geo-tracking.js lee este meta al
    // DOMContentLoaded y dispara el request a navigator.geolocation.
    if (!empty($_SESSION['rxn_geo_pending_event_id'])) {
        $pendingGeoEventId = (int) $_SESSION['rxn_geo_pending_event_id'];
        unset($_SESSION['rxn_geo_pending_event_id']);
        if ($pendingGeoEventId > 0) {
            echo '<meta name="rxn-pending-geo-event" content="' . $pendingGeoEventId . '">' . "\n";
        }
    }
    ?>
    <title><?= htmlspecialchars($finalPageTitle) ?></title>
    <?php if ($faviconUrl): ?>
        <link rel="icon" href="<?= htmlspecialchars($faviconUrl) ?>">
    <?php endif; ?>
    <!-- Persistencia global de filtros de listado (corre antes del render para evitar flash) -->
    <script src="/js/rxn-filter-persistence.js?v=<?= time() ?>"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
    <link href="/css/rxn-theming.css?v=<?= time() ?>" rel="stylesheet">
    <link href="/css/rxn-shortcuts.css?v=<?= time() ?>" rel="stylesheet">
    <link href="/css/rxn-notifications.css?v=<?= time() ?>" rel="stylesheet">
    <?= $extraHead ?? '' ?>
</head>
<body class="d-flex flex-column min-vh-100 rxn-launcher-shell pt-2">
    
    <!-- Topbar global del admin (Banner de usuario) -->
    <?php View::render('app/shared/views/components/backoffice_user_banner.php', $ui); ?>

    <!-- Contenido principal -->
    <main class="container-fluid flex-grow-1 px-4 mb-4" style="min-width: 0;">
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

    <?php
    // Banner de consentimiento de geo-tracking (RxnGeoTracking).
    // El partial decide internamente si rendersar o no — retorna vacío si
    // el user ya respondió la versión vigente o el módulo está deshabilitado.
    View::render('app/modules/RxnGeoTracking/views/_consent_banner.php');
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/es.js"></script>
    <script src="/js/rxn-datetime.js?v=<?= time() ?>"></script>
    <script src="/js/rxn-confirm-modal.js"></script>
    <script src="/js/rxn-row-links.js"></script>
    <script src="/js/rxn-spotlight.js?v=<?= time() ?>"></script>
    <script src="/js/rxn-geo-consent.js?v=<?= time() ?>"></script>
    <script src="/js/rxn-geo-tracking.js?v=<?= time() ?>"></script>
    <script src="/js/rxn-shortcuts.js?v=<?= time() ?>"></script>
    <script src="/js/rxn-list-shortcuts.js?v=<?= time() ?>"></script>
    <script src="/js/rxn-notifications.js?v=<?= time() ?>"></script>
    <?= $extraScripts ?? '' ?>
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
