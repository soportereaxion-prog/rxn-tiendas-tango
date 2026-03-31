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
<!DOCTYPE html>
<html lang="es" <?= \App\Core\Helpers\UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RXN Backoffice - rxnTiendasIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="/rxnTiendasIA/public/css/rxn-theming.css" rel="stylesheet">
    <style>
        body { background-color: var(--bg-color, #121212); color: var(--text-color, #f8f9fa); }
        .hero-title { font-size: 2.2rem; font-weight: 800; letter-spacing: -1px; }
        
        .module-card {
            background-color: var(--card-bg, #1e1e1e);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            transition: all 0.3s ease;
            min-height: 250px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .module-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.25);
            border-color: rgba(255, 255, 255, 0.15);
        }
        
        .module-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }

        .release-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.04), rgba(255, 255, 255, 0.02));
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 18px;
        }

        .release-list {
            margin-bottom: 0;
            padding-left: 1rem;
            color: #c7c7c7;
        }

        .release-list li + li {
            margin-top: 0.45rem;
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100 p-4 p-md-5 rxn-launcher-shell">

    <div class="container-fluid rxn-responsive-container" style="max-width: 1200px;">
        
        <div class="rxn-module-header mb-5 pb-2 border-bottom border-secondary border-opacity-25">
            <div>
                <h1 class="hero-title mb-1"><i class="bi bi-buildings"></i> RXN Backoffice</h1>
                <p class="text-muted mb-0">Gestión global de licenciatarios y configuración maestra.</p>
            </div>
            <div class="rxn-module-actions">
                <?php require BASE_PATH . '/app/shared/views/components/backoffice_user_banner.php'; ?>
                <a href="/rxnTiendasIA/public/" class="btn btn-outline-secondary rounded-pill px-4"><i class="bi bi-arrow-left"></i> Volver al Launcher</a>
            </div>
        </div>

        <?php
        $moduleNotesKey = 'rxn_backoffice';
        $moduleNotesLabel = 'RXN Backoffice';
        require BASE_PATH . '/app/shared/views/components/module_notes_panel.php';
        ?>

        <div class="row g-4 justify-content-center">
            
            <div class="col-sm-6 col-lg-4">
                <div class="card module-card text-center p-4 h-100 position-relative shadow-sm">
                    <div class="card-body d-flex flex-column align-items-center justify-content-center p-0">
                        <div class="module-icon"><i class="bi bi-buildings"></i></div>
                        <h5 class="fw-bold mb-2 text-white">Listado de Empresas</h5>
                        <p class="text-muted small px-2">ABM de tenants, asignación de licencias y suspensión de cuentas.</p>
                        <a href="/rxnTiendasIA/public/empresas" class="stretched-link"></a>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4">
                <div class="card module-card text-center p-4 h-100 position-relative shadow-sm" style="opacity: 0.6;">
                    <div class="card-body d-flex flex-column align-items-center justify-content-center p-0">
                        <div class="module-icon"><i class="bi bi-gear"></i></div>
                        <h5 class="fw-bold mb-2 text-white">Configuración Global</h5>
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
                            <p class="text-muted mb-0"><?= htmlspecialchars((string) ($release['summary'] ?? '')) ?></p>
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

</body>
</html>
