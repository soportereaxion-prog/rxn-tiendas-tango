<?php
$canViewRelease = \App\Modules\Auth\AuthService::hasAdminPrivileges();
$isLoggedIn = !empty($_SESSION['user_id']);
$canViewBackoffice = $canViewRelease;
$hasTiendasAccess = \App\Modules\Empresas\EmpresaAccessService::hasTiendasAccess();
$hasCrmAccess = \App\Modules\Empresas\EmpresaAccessService::hasCrmAccess();

if ($canViewRelease) {
    $release = \App\Shared\Services\VersionService::current();
    $releaseLabel = \App\Shared\Services\VersionService::currentLabel();
    $releaseBuild = \App\Shared\Services\VersionService::currentBuildLabel();
    $releaseDate = \App\Shared\Services\VersionService::formattedDate($release['released_at'] ?? null);
    $releaseItems = \App\Shared\Services\VersionService::currentHighlights(2);
}

$launcherCards = [];

if ($canViewBackoffice) {
    $launcherCards[] = [
        'title' => 'RXN Backoffice',
        'desc' => 'Administracion global de licenciatarios, tenants y configuraciones master.',
        'icon' => 'bi-buildings',
        'href' => '/rxnTiendasIA/public/admin/dashboard',
    ];
}

if ($hasTiendasAccess) {
    $launcherCards[] = [
        'title' => 'Entorno Operativo de Tiendas',
        'desc' => 'Catalogo, clientes web, pedidos, usuarios y configuracion comercial del tenant.',
        'icon' => 'bi-shop-window',
        'href' => '/rxnTiendasIA/public/mi-empresa/dashboard',
        'badge' => 'Empresa #' . ($_SESSION['empresa_id'] ?? ''),
    ];
}

if ($hasCrmAccess) {
    $launcherCards[] = [
        'title' => 'Entorno Operativo de CRM',
        'desc' => 'Base comercial inicial para configuracion y articulos CRM del tenant.',
        'icon' => 'bi-diagram-3',
        'href' => '/rxnTiendasIA/public/mi-empresa/crm/dashboard',
        'badge' => 'Empresa #' . ($_SESSION['empresa_id'] ?? ''),
    ];
}


?>
<?php
$pageTitle = 'RXN Tiendas IA';
ob_start();
?>
<div class="container rxn-responsive-container" style="max-width: 1100px;">
        <div class="text-center mb-5 pb-3">
            <img src="/rxnTiendasIA/public/uploads/empresas/1/branding/logo_1774467026.png" alt="Reaxion Soluciones Inteligentes" class="rxn-auth-logo mb-3" style="max-height: 50px;">
            <p class="hero-subtitle">-Suite Re@xion para administrar tu empresa con centralización en Tango-</p>
        </div>

        <!-- Buscador de Módulos (Estándar F3 / /) -->
        <?php require BASE_PATH . '/app/shared/views/components/dashboard_search.php'; ?>

        <div class="row g-4 justify-content-center">
            <?php foreach ($launcherCards as $card): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card rxn-module-card text-center p-4 h-100 position-relative shadow-sm">
                        <div class="card-body d-flex flex-column align-items-center justify-content-center p-0">
                            <div class="rxn-module-icon text-primary"><i class="bi <?= htmlspecialchars($card['icon']) ?>"></i></div>
                            <h4 class="fw-bold mb-2"><?= htmlspecialchars($card['title']) ?></h4>
                            <p class="text-muted small px-3 mb-0"><?= htmlspecialchars($card['desc']) ?></p>
                            <?php if (!empty($card['badge'])): ?>
                                <div class="mt-4 pt-3 border-top border-secondary border-opacity-25 w-100">
                                    <span class="badge bg-dark text-light border border-secondary px-3 py-2 fw-medium">
                                        <?= htmlspecialchars((string) $card['badge']) ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            <a href="<?= htmlspecialchars($card['href']) ?>" class="stretched-link"></a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!$hasTiendasAccess && !$hasCrmAccess && !$canViewBackoffice): ?>
            <div class="alert alert-secondary mt-4 mb-0 text-center shadow-sm border-0 rounded-4">
                Tu empresa no tiene entornos operativos habilitados todavia.
            </div>
        <?php endif; ?>

        <?php if ($canViewRelease): ?>
            <div class="mt-5 mx-auto" style="max-width: 780px;">
                <div class="release-card p-4 p-md-5 shadow-sm">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3 mb-3">
                        <div>
                            <span class="badge rounded-pill text-bg-light text-dark mb-3">Novedades</span>
                            <h2 class="h4 fw-bold text-white mb-2"><?= htmlspecialchars((string) ($release['title'] ?? 'Release actual')) ?></h2>
                            
                        </div>
                        <div class="text-md-end small text-muted">
                            <div class="fw-bold text-white"><?= htmlspecialchars($releaseLabel) ?></div>
                            <?php if ($releaseBuild !== ''): ?><div><?= htmlspecialchars($releaseBuild) ?></div><?php endif; ?>
                            <?php if ($releaseDate !== ''): ?><div><?= htmlspecialchars($releaseDate) ?></div><?php endif; ?>
                        </div>
                    </div>
                    <?php if ($releaseItems !== []): ?>
                        <ul class="release-list small">
                            <?php foreach ($releaseItems as $releaseItem): ?>
                                <li><?= htmlspecialchars($releaseItem) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="text-center mt-5">
            <a href="/rxnTiendasIA/public/logout" class="btn btn-outline-secondary btn-sm rounded-pill px-4 text-muted border-secondary border-opacity-50">Cerrar Sesion</a>
        </div>
    </div>
    
<?php
$content = ob_get_clean();
ob_start();
?>
<script src="/rxnTiendasIA/public/js/rxn-shortcuts.js"></script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
