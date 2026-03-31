<?php
// Extracción segura del JSON de ordenamiento guardado
$orderJson = $_SESSION['dashboard_order'] ?? '[]';
$decodedOrder = json_decode($orderJson, true);

if (is_array($decodedOrder) && array_is_list($decodedOrder)) {
    $orderArray = $decodedOrder;
} elseif (is_array($decodedOrder)) {
    $orderArray = $decodedOrder['tiendas'] ?? [];
} else {
    $orderArray = [];
}
$canViewRelease = \App\Modules\Auth\AuthService::hasAdminPrivileges();

if ($canViewRelease) {
    $release = \App\Shared\Services\VersionService::current();
    $releaseLabel = \App\Shared\Services\VersionService::currentLabel();
    $releaseBuild = \App\Shared\Services\VersionService::currentBuildLabel();
    $releaseDate = \App\Shared\Services\VersionService::formattedDate($release['released_at'] ?? null);
    $releaseItems = \App\Shared\Services\VersionService::currentHighlights(2);
}

// Generamos las Tarjetas Nivel 2B
$defaultCards = [
    'articulos' => [
        'title' => 'Catálogo de Artículos', 
        'desc' => 'Gestión de tu catálogo, imágenes y precios online.', 
        'icon' => '<i class="bi bi-box-seam"></i>', 
        'link' => '/rxnTiendasIA/public/mi-empresa/articulos'
    ],
    'categorias' => [
        'title' => 'Categorías',
        'desc' => 'Ordena el catálogo y publica accesos directos por rubro.',
        'icon' => '<i class="bi bi-grid-3x3-gap"></i>',
        'link' => '/rxnTiendasIA/public/mi-empresa/categorias'
    ],
    'clientes' => [
        'title' => 'Clientes Web', 
        'desc' => 'Aprobación y listado de clientes de la tienda.', 
        'icon' => '<i class="bi bi-people"></i>', 
        'link' => '/rxnTiendasIA/public/mi-empresa/clientes'
    ],
    'pedidos' => [
        'title' => 'Pedidos Web', 
        'desc' => 'Visualización, rechazo y reproceso de pedidos entrantes.', 
        'icon' => '<i class="bi bi-cart3"></i>', 
        'link' => '/rxnTiendasIA/public/mi-empresa/pedidos'
    ],
    'perfil' => [
        'title' => 'Mi Perfil', 
        'desc' => 'Ajustes de credenciales y apariencia de tu entorno.', 
        'icon' => '<i class="bi bi-person-badge"></i>', 
        'link' => '/rxnTiendasIA/public/mi-perfil?area=tiendas'
    ],
    'usuarios' => [
        'title' => 'Administrar Cuentas', 
        'desc' => 'Alta, baja y modificación de accesos corporativos.', 
        'icon' => '<i class="bi bi-shield-lock"></i>', 
        'link' => '/rxnTiendasIA/public/mi-empresa/usuarios?area=tiendas'
    ],
    'configuracion' => [
        'title' => 'Configuración', 
        'desc' => 'Branding público, envíos SMTP y Tango Connect.', 
        'icon' => '<i class="bi bi-sliders"></i>', 
        'link' => '/rxnTiendasIA/public/mi-empresa/configuracion'
    ]
];

// Transformación del array según matriz persistente del usuario
$finalCards = [];
foreach ($orderArray as $cardId) {
    if (isset($defaultCards[$cardId])) {
        $finalCards[$cardId] = $defaultCards[$cardId];
        unset($defaultCards[$cardId]);
    }
}
foreach ($defaultCards as $cardId => $cardData) {
    $finalCards[$cardId] = $cardData;
}
?>
<!DOCTYPE html>
<html lang="es" <?= \App\Core\Helpers\UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entorno Operativo de Tiendas - rxnTiendasIA</title>
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
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
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
                <h1 class="hero-title mb-1"><i class="bi bi-shop-window"></i> Entorno Operativo de Tiendas</h1>
                <p class="text-muted mb-0">Circuito comercial web del tenant <span class="badge bg-secondary ms-1">Empresa #<?= $_SESSION['empresa_id'] ?? '' ?></span></p>
            </div>
            <div class="rxn-module-actions">
                <?php require BASE_PATH . '/app/shared/views/components/user_action_menu.php'; ?>
                
                <a href="/rxnTiendasIA/public/mi-empresa/ayuda?area=tiendas" class="btn btn-outline-info rounded-pill px-4" target="_blank" rel="noopener noreferrer"><i class="bi bi-question-circle"></i> Ayuda</a>
                <a href="/rxnTiendasIA/public/" class="btn btn-outline-secondary rounded-pill px-4"><i class="bi bi-arrow-left"></i> Volver al Launcher</a>
            </div>
        </div>

        <?php
        $moduleNotesKey = 'entorno_operativo_tiendas';
        $moduleNotesLabel = 'Entorno Operativo de Tiendas';
        require BASE_PATH . '/app/shared/views/components/module_notes_panel.php';
        ?>

        <!-- El grid sortable -->
        <div id="dashboard-grid" class="row g-4">
            <?php foreach ($finalCards as $id => $data): ?>
                <div class="col-sm-6 col-lg-4 rxn-sortable-col" data-id="<?= htmlspecialchars($id) ?>">
                    <div class="card module-card text-center p-4 h-100 position-relative shadow-sm" style="cursor: grab;">
                        <div class="card-body d-flex flex-column align-items-center justify-content-center p-0">
                            <div class="module-icon"><?= $data['icon'] ?></div>
                            <h5 class="fw-bold mb-2 text-white"><?= htmlspecialchars($data['title']) ?></h5>
                            <p class="text-muted small px-2"><?= htmlspecialchars($data['desc']) ?></p>
                            <a href="<?= htmlspecialchars($data['link']) ?>" class="stretched-link"></a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
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

    <!-- Script de Inyección Física D&D -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const grid = document.getElementById('dashboard-grid');
        new Sortable(grid, {
            animation: 250,
            ghostClass: 'opacity-50',
            handle: '.module-card',
            onEnd: function () {
                let currentOrder = [];
                document.querySelectorAll('.rxn-sortable-col').forEach(col => {
                    currentOrder.push(col.getAttribute('data-id'));
                });

                fetch('/rxnTiendasIA/public/mi-perfil/dashboard-order', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ area: 'tiendas', order: currentOrder })
                })
                .then(response => response.json())
                .catch(err => console.error("Error interconexión D&D", err));
            }
        });
    });
    </script>
</body>
</html>
