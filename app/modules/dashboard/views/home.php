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

if ($isLoggedIn && $hasTiendasAccess) {
    $launcherCards[] = [
        'title' => 'Entorno Operativo de Tiendas',
        'desc' => 'Catalogo, clientes web, pedidos, usuarios y configuracion comercial del tenant.',
        'icon' => 'bi-shop-window',
        'href' => '/rxnTiendasIA/public/mi-empresa/dashboard',
        'badge' => 'Empresa #' . ($_SESSION['empresa_id'] ?? ''),
    ];
}

if ($isLoggedIn && $hasCrmAccess) {
    $launcherCards[] = [
        'title' => 'Entorno Operativo de CRM',
        'desc' => 'Base comercial inicial para configuracion y articulos CRM del tenant.',
        'icon' => 'bi-diagram-3',
        'href' => '/rxnTiendasIA/public/mi-empresa/crm/dashboard',
        'badge' => 'Empresa #' . ($_SESSION['empresa_id'] ?? ''),
    ];
}

if (!$isLoggedIn) {
    $launcherCards[] = [
        'title' => 'Ingresar al Sistema',
        'desc' => 'Accede con tu usuario para ver los entornos operativos habilitados para tu empresa.',
        'icon' => 'bi-box-arrow-in-right',
        'href' => '/rxnTiendasIA/public/login',
    ];
}
?>
<!DOCTYPE html>
<html lang="es" <?= \App\Core\Helpers\UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Launcher Principal - rxnTiendasIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="/rxnTiendasIA/public/css/rxn-theming.css" rel="stylesheet">
    <style>
        body { background-color: var(--bg-color, #121212); color: var(--text-color, #f8f9fa); }
        .hero-title { font-size: 2.5rem; font-weight: 800; letter-spacing: -1px; }
        .hero-subtitle { font-size: 1.1rem; color: #a0a0a0; }

        .launcher-card {
            background-color: var(--card-bg, #1e1e1e);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            min-height: 280px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .launcher-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.3);
            border-color: rgba(255, 255, 255, 0.15);
        }

        .launcher-icon {
            font-size: 3.5rem;
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
<body class="d-flex align-items-center justify-content-center min-vh-100 py-5 rxn-launcher-shell">
    <div class="container rxn-responsive-container" style="max-width: 1100px;">
        <div class="text-center mb-5 pb-3">
            <h1 class="hero-title mb-2">Bienvenido a rxnTiendasIA</h1>
            <p class="hero-subtitle">Plataforma central de administracion multiempresa.</p>
        </div>

        <div class="row g-4 justify-content-center">
            <?php foreach ($launcherCards as $card): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card launcher-card text-center p-4 h-100 position-relative shadow-sm">
                        <div class="card-body d-flex flex-column align-items-center justify-content-center p-0">
                            <div class="launcher-icon"><i class="bi <?= htmlspecialchars($card['icon']) ?>"></i></div>
                            <h4 class="fw-bold mb-2 text-white"><?= htmlspecialchars($card['title']) ?></h4>
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

        <?php if ($isLoggedIn && !$hasTiendasAccess && !$hasCrmAccess && !$canViewBackoffice): ?>
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

        <div class="text-center mt-5">
            <?php if ($isLoggedIn): ?>
                <a href="/rxnTiendasIA/public/logout" class="btn btn-outline-secondary btn-sm rounded-pill px-4 text-muted border-secondary border-opacity-50">Cerrar Sesion</a>
            <?php else: ?>
                <a href="/rxnTiendasIA/public/login" class="btn btn-outline-light btn-sm rounded-pill px-4">Iniciar Sesion</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
