<?php
$orderJson = $_SESSION['dashboard_order'] ?? '[]';
$decodedOrder = json_decode($orderJson, true);

if (is_array($decodedOrder) && array_is_list($decodedOrder)) {
    $orderArray = [];
} elseif (is_array($decodedOrder)) {
    $orderArray = $decodedOrder['crm'] ?? [];
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

$defaultCards = [
    'configuracion' => [
        'title' => 'Configuracion',
        'desc' => 'Parametros operativos propios de CRM con persistencia separada del entorno Tiendas.',
        'icon' => '<i class="bi bi-sliders"></i>',
        'link' => '/rxnTiendasIA/public/mi-empresa/crm/configuracion',
    ],
    'articulos' => [
        'title' => 'Articulos CRM',
        'desc' => 'Base inicial de articulos del CRM con estructura propia y estilo alineado al circuito de tiendas.',
        'icon' => '<i class="bi bi-box-seam"></i>',
        'link' => '/rxnTiendasIA/public/mi-empresa/crm/articulos',
    ],
    'clientes' => [
        'title' => 'Clientes CRM',
        'desc' => 'Directorio de Clientes CRM y vinculacion comercial, con BD independiente de Tiendas.',
        'icon' => '<i class="bi bi-people"></i>',
        'link' => '/rxnTiendasIA/public/mi-empresa/crm/clientes',
    ],
    'pedidos_servicio' => [
        'title' => 'Pedidos de Servicio',
        'desc' => 'Alta, seguimiento y calculo de tiempos operativos para el circuito tecnico/comercial de CRM.',
        'icon' => '<i class="bi bi-tools"></i>',
        'link' => '/rxnTiendasIA/public/mi-empresa/crm/pedidos-servicio',
    ],
    'presupuestos' => [
        'title' => 'Presupuestos CRM',
        'desc' => 'Gestión de presupuestos comerciales referenciados a clientes, con valorización y envio de PDF.',
        'icon' => '<i class="bi bi-file-earmark-spreadsheet"></i>',
        'link' => '/rxnTiendasIA/public/mi-empresa/crm/presupuestos',
    ],
    'notas' => [
        'title' => 'Notas CRM',
        'desc' => 'Historial de interacciones y trazabilidad de contactos con clientes. Base de conocimiento.',
        'icon' => '<i class="bi bi-journal-text"></i>',
        'link' => '/rxnTiendasIA/public/mi-empresa/crm/notas',
    ],
    'formularios' => [
        'title' => 'Formularios Impresos',
        'desc' => 'Configuración de tipografías y datos fiscales para documentos generados en PDF.',
        'icon' => '<i class="bi bi-file-earmark-richtext"></i>',
        'link' => '/rxnTiendasIA/public/mi-empresa/crm/formularios-impresion',
    ],
    'llamadas' => [
        'title' => 'Llamadas CRM',
        'desc' => 'Historial de llamadas de la central telefónica con reproducción de audios.',
        'icon' => '<i class="bi bi-telephone-fill"></i>',
        'link' => '/rxnTiendasIA/public/mi-empresa/crm/llamadas',
    ],
    'usuarios' => [
        'title' => 'Administrar Cuentas',
        'desc' => 'Gestion de usuarios internos compartida entre los entornos operativos del tenant.',
        'icon' => '<i class="bi bi-shield-lock"></i>',
        'link' => '/rxnTiendasIA/public/mi-empresa/usuarios?area=crm',
    ],
    'perfil' => [
        'title' => 'Mi Perfil',
        'desc' => 'Preferencias visuales y de uso para seguir trabajando desde CRM sin salir del circuito.',
        'icon' => '<i class="bi bi-person-badge"></i>',
        'link' => '/rxnTiendasIA/public/mi-perfil?area=crm',
    ],
];

if (!\App\Modules\Empresas\EmpresaAccessService::hasCrmNotasAccess()) {
    unset($defaultCards['notas']);
}

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
$environmentLabel = 'Entorno Operativo';
$dashboardPath = '/rxnTiendasIA/public/';
$pageTitle = 'Entorno Operativo de CRM - rxnTiendasIA';

ob_start();
?>
<style>
    body { background-color: var(--bg-color, #121212); color: var(--text-color, #f8f9fa); }
    .hero-title { font-size: 2.2rem; font-weight: 800; letter-spacing: -1px; }

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
<?php
$extraHead = ob_get_clean();

ob_start();
?>
<?php
ob_start();
?>
<a href="/rxnTiendasIA/public/mi-empresa/ayuda?area=crm" class="btn btn-outline-info rounded-pill px-4" target="_blank" rel="noopener noreferrer"><i class="bi bi-question-circle"></i> Ayuda</a>
<?php
$actionsHtml = ob_get_clean();

\App\Core\View::render('app/shared/views/partials/page_header.php', [
    'title' => 'Entorno Operativo de CRM',
    'subtitle' => 'Base inicial del circuito CRM <span class="badge bg-secondary ms-1">Empresa #' . ($_SESSION['empresa_id'] ?? '') . '</span>',
    'iconClass' => 'bi bi-diagram-3',
    'actionsHtml' => $actionsHtml,
    'backUrl' => '/rxnTiendasIA/public/',
    'backLabel' => 'Volver al Launcher'
]);
?>

<?php
        $moduleNotesKey = 'entorno_operativo_crm';
        $moduleNotesLabel = 'Entorno Operativo de CRM';
        require BASE_PATH . '/app/shared/views/components/module_notes_panel.php';
        ?>

        <!-- Buscador de Módulos (Estándar F3 / /) -->
        <?php require BASE_PATH . '/app/shared/views/components/dashboard_search.php'; ?>

        <div id="dashboard-grid-crm" class="row g-4">
            <?php foreach ($finalCards as $id => $card): ?>
                <div class="col-sm-6 col-lg-4 rxn-sortable-col" data-id="<?= htmlspecialchars((string) $id) ?>">
                    <div class="card rxn-module-card text-center p-4 h-100 position-relative shadow-sm">
                        <div class="card-body d-flex flex-column align-items-center justify-content-center p-0">
                            <div class="rxn-module-icon text-primary"><?= $card['icon'] ?></div>
                            <h5 class="fw-bold mb-2"><?= htmlspecialchars($card['title']) ?></h5>
                            <p class="text-muted small px-2 mb-0"><?= htmlspecialchars($card['desc']) ?></p>
                            <a href="<?= htmlspecialchars($card['link']) ?>" class="stretched-link"></a>
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
<?php
$content = ob_get_clean();

ob_start();
?>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var grid = document.getElementById('dashboard-grid-crm');
        if (!grid) {
            return;
        }

        new Sortable(grid, {
            animation: 250,
            ghostClass: 'opacity-50',
            handle: '.rxn-module-card',
            onEnd: function () {
                var currentOrder = [];
                document.querySelectorAll('#dashboard-grid-crm .rxn-sortable-col').forEach(function (col) {
                    currentOrder.push(col.getAttribute('data-id'));
                });

                fetch('/rxnTiendasIA/public/mi-perfil/dashboard-order', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ area: 'crm', order: currentOrder })
                }).catch(function (err) {
                    console.error('Error ordenando menu CRM', err);
                });
            }
        });
    });
    </script>
<?php
$extraScripts = ob_get_clean();

require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
