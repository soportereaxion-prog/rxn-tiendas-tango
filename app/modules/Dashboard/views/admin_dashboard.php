<?php
$canViewRelease = \App\Modules\Auth\AuthService::hasAdminPrivileges();

if ($canViewRelease) {
    $release = \App\Shared\Services\VersionService::current();
    $releaseLabel = \App\Shared\Services\VersionService::currentLabel();
    $releaseBuild = \App\Shared\Services\VersionService::currentBuildLabel();
    $releaseDate = \App\Shared\Services\VersionService::formattedDate($release['released_at'] ?? null);
    $releaseItems = \App\Shared\Services\VersionService::currentHighlights(2);
}
?>
<?php
$pageTitle = 'RXN Tiendas IA';
ob_start();
?>
<div class="container-fluid rxn-responsive-container" style="max-width: 1200px;">
        
        <div class="rxn-module-header mb-5 pb-2 border-bottom border-secondary border-opacity-25">
            <div>
                <h1 class="hero-title mb-1"><i class="bi bi-buildings"></i> RXN Backoffice</h1>
                
            </div>
            <div class="rxn-module-actions">
                
                <a href="/rxnTiendasIA/public/" class="btn btn-outline-secondary rounded-pill px-4"><i class="bi bi-arrow-left"></i> Volver al Launcher</a>
            </div>
        </div>

        <?php
        $moduleNotesKey = 'rxn_backoffice';
        $moduleNotesLabel = 'RXN Backoffice';
        require BASE_PATH . '/app/shared/views/components/module_notes_panel.php';
        ?>

        <!-- Buscador de Módulos (Estándar F3 / /) -->
        <?php require BASE_PATH . '/app/shared/views/components/dashboard_search.php'; ?>

        <div class="row g-4 justify-content-center">
            
            <div class="col-sm-6 col-lg-4">
                <div class="card rxn-module-card text-center p-4 h-100 position-relative shadow-sm">
                    <div class="card-body d-flex flex-column align-items-center justify-content-center p-0">
                        <div class="rxn-module-icon text-primary"><i class="bi bi-buildings"></i></div>
                        <h5 class="fw-bold mb-2">Listado de Empresas</h5>
                        <p class="text-muted small px-2">ABM de tenants, asignación de licencias y suspensión de cuentas.</p>
                        <a href="/rxnTiendasIA/public/empresas" class="stretched-link"></a>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4">
                <div class="card rxn-module-card text-center p-4 h-100 position-relative shadow-sm" style="opacity: 0.6;">
                    <div class="card-body d-flex flex-column align-items-center justify-content-center p-0">
                        <div class="rxn-module-icon text-secondary"><i class="bi bi-gear"></i></div>
                        <h5 class="fw-bold mb-2">Configuración Global</h5>
                        <p class="text-muted small px-2">Ajustes SMTP maestros para el pool de correos RXN.</p>
                        <a href="/rxnTiendasIA/public/admin/smtp-global" class="stretched-link"></a>
                    </div>
                </div>
            </div>
            
        </div>

        <?php if ($canViewRelease): ?>
            <div class="mt-5">
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
