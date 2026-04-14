<?php
$pageTitle = 'RXN LIVE - Reporting';
ob_start();
?>
<?php
$usePageHeader = true;
$pageHeaderTitle = 'RXN LIVE';
$pageHeaderSubtitle = 'Métricas operativas y análisis <span class="badge bg-primary ms-1" style="font-size: 0.7em;">BETA</span>';
$pageHeaderIcon = 'bi bi-graph-up-arrow';

$pageHeaderBackUrl = $_SESSION['rxn_live_back_url'] ?? '/';
$pageHeaderBackLabel = $_SESSION['rxn_live_back_label'] ?? 'Volver a Suite';
?>

        <!-- Buscador de Módulos (Estándar F3 / /) -->
        <?php require BASE_PATH . '/app/shared/views/components/dashboard_search.php'; ?>

        <div id="dashboard-grid-rxnlive" class="row g-4 pt-2">
            <?php foreach ($datasets as $key => $ds): ?>
                <div class="col-sm-6 col-lg-4 rxn-sortable-col" data-id="<?= htmlspecialchars($key) ?>">
                    <div class="card rxn-module-card text-center p-4 h-100 position-relative shadow-sm">
                        <!-- Safe Mode escape hatch: si una vista rota tumba el dataset, abrir desde acá -->
                        <a href="/rxn_live/dataset?dataset=<?= htmlspecialchars($key) ?>&safe_mode=1"
                           class="position-absolute top-0 end-0 p-2 text-warning opacity-50 hover-opacity-100"
                           style="z-index: 2; font-size: 0.85rem;"
                           title="Abrir en Safe Mode (ignora vistas y filtros guardados — útil si el dataset queda titilando)"
                           data-bs-toggle="tooltip">
                            <i class="bi bi-shield-exclamation"></i>
                        </a>
                        <div class="card-body d-flex flex-column align-items-center justify-content-center p-0">
                            <div class="rxn-module-icon text-primary"><i class="bi bi-database fs-4"></i></div>
                            <h5 class="fw-bold mb-2"><?= htmlspecialchars($ds['name']) ?></h5>
                            <p class="text-muted small px-2 mb-0"><?= htmlspecialchars($ds['description']) ?></p>
                            <a href="/rxn_live/dataset?dataset=<?= htmlspecialchars($key) ?>" class="stretched-link"></a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

<?php
$content = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
