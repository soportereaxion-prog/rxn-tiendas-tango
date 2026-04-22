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
        'link' => '/mi-empresa/articulos'
    ],
    'categorias' => [
        'title' => 'Categorías',
        'desc' => 'Ordena el catálogo y publica accesos directos por rubro.',
        'icon' => '<i class="bi bi-grid-3x3-gap"></i>',
        'link' => '/mi-empresa/categorias'
    ],
    'clientes' => [
        'title' => 'Clientes Web', 
        'desc' => 'Aprobación y listado de clientes de la tienda.', 
        'icon' => '<i class="bi bi-people"></i>', 
        'link' => '/mi-empresa/clientes'
    ],
    'pedidos' => [
        'title' => 'Pedidos Web', 
        'desc' => 'Visualización, rechazo y reproceso de pedidos entrantes.', 
        'icon' => '<i class="bi bi-cart3"></i>', 
        'link' => '/mi-empresa/pedidos'
    ],
    'perfil' => [
        'title' => 'Mi Perfil', 
        'desc' => 'Ajustes de credenciales y apariencia de tu entorno.', 
        'icon' => '<i class="bi bi-person-badge"></i>', 
        'link' => '/mi-perfil?area=tiendas'
    ],
    'usuarios' => [
        'title' => 'Administrar Cuentas', 
        'desc' => 'Alta, baja y modificación de accesos corporativos.', 
        'icon' => '<i class="bi bi-shield-lock"></i>', 
        'link' => '/mi-empresa/usuarios?area=tiendas'
    ],
    'configuracion' => [
        'title' => 'Configuración', 
        'desc' => 'Branding público, envíos SMTP y Tango Connect.', 
        'icon' => '<i class="bi bi-sliders"></i>', 
        'link' => '/mi-empresa/configuracion'
    ],
    'reporting' => [
        'title' => 'RXN LIVE Reporting', 
        'desc' => 'Evolución transaccional y métricas detalladas.', 
        'icon' => '<i class="bi bi-graph-up-arrow"></i>', 
        'link' => '/rxn_live?from=tiendas'
    ],
    'rxn_sync' => [
        'title' => 'RXN Sync',
        'desc' => 'Auditoría y control de sincronización de Entidades hacia Tango (Push/Pull).',
        'icon' => '<i class="bi bi-arrow-left-right"></i>',
        'link' => '/mi-empresa/rxn-sync'
    ]
];

if (!\App\Modules\Empresas\EmpresaAccessService::hasTiendasRxnLiveAccess()) {
    unset($defaultCards['reporting']);
}

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
<?php
$pageTitle = 'RXN Suite';
ob_start();
?>
<div class="container-fluid rxn-responsive-container" style="max-width: 1200px;">
        
        <div class="rxn-module-header mb-5 pb-2 border-bottom border-secondary border-opacity-25">
            <div>
                <h1 class="hero-title mb-1"><i class="bi bi-shop-window"></i> Entorno Operativo de Tiendas</h1>
                
            </div>
            <div class="rxn-module-actions">

                
                <a href="/mi-empresa/ayuda?area=tiendas" class="btn btn-outline-info btn-sm" target="_blank" rel="noopener noreferrer" title="Ayuda del Entorno de Tiendas"><i class="bi bi-question-circle"></i> Ayuda</a>
                <a href="/" class="btn btn-outline-secondary btn-sm" title="Volver al Launcher"><i class="bi bi-arrow-left"></i> Volver</a>
            </div>
        </div>

        <?php
        $moduleNotesKey = 'entorno_operativo_tiendas';
        $moduleNotesLabel = 'Entorno Operativo de Tiendas';
        require BASE_PATH . '/app/shared/views/components/module_notes_panel.php';
        ?>

        <!-- Buscador de Módulos (Estándar F3 / /) -->
        <?php require BASE_PATH . '/app/shared/views/components/dashboard_search.php'; ?>

        <!-- El grid sortable -->
        <div id="dashboard-grid" class="row g-4">
            <?php foreach ($finalCards as $id => $data): ?>
                <div class="col-sm-6 col-lg-4 rxn-sortable-col" data-id="<?= htmlspecialchars($id) ?>">
                    <div class="card rxn-module-card text-center p-4 h-100 position-relative shadow-sm" style="cursor: grab;">
                        <div class="card-body d-flex flex-column align-items-center justify-content-center p-0">
                            <div class="rxn-module-icon text-primary"><?= $data['icon'] ?></div>
                            <h5 class="fw-bold mb-2"><?= htmlspecialchars($data['title']) ?></h5>
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
    
<?php
$content = ob_get_clean();
ob_start();
?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const grid = document.getElementById('dashboard-grid');
        new Sortable(grid, {
            animation: 250,
            ghostClass: 'opacity-50',
            handle: '.rxn-module-card',
            onEnd: function () {
                let currentOrder = [];
                document.querySelectorAll('.rxn-sortable-col').forEach(col => {
                    currentOrder.push(col.getAttribute('data-id'));
                });

                fetch('/mi-perfil/dashboard-order', {
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
    <script src="/js/rxn-shortcuts.js"></script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
