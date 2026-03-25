<?php
// Extracción segura del JSON de ordenamiento guardado
$orderJson = $_SESSION['dashboard_order'] ?? '[]';
$orderArray = json_decode($orderJson, true) ?: [];

// Generamos las Tarjetas Nivel 2B
$defaultCards = [
    'articulos' => [
        'title' => 'Catálogo de Artículos', 
        'desc' => 'Gestión de tu catálogo, imágenes y precios online.', 
        'icon' => '<i class="bi bi-box-seam"></i>', 
        'link' => '/rxnTiendasIA/public/mi-empresa/articulos'
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
        'link' => '/rxnTiendasIA/public/mi-perfil'
    ],
    'usuarios' => [
        'title' => 'Administrar Cuentas', 
        'desc' => 'Alta, baja y modificación de accesos corporativos.', 
        'icon' => '<i class="bi bi-shield-lock"></i>', 
        'link' => '/rxnTiendasIA/public/mi-empresa/usuarios'
    ],
    'configuracion' => [
        'title' => 'Configuración', 
        'desc' => 'Branding público, envíos SMTP y Tango Connect.', 
        'icon' => '<i class="bi bi-sliders"></i>', 
        'link' => '/rxnTiendasIA/public/mi-empresa/configuracion'
    ]
];

// Regla de Seguridad: Ocultar Administrar Cuentas si el Auth NO es Tenant Admin ni Global Admin
$isTenantAdmin = (!empty($_SESSION['es_admin']) && $_SESSION['es_admin'] == 1);
$isGlobalAdmin = (!empty($_SESSION['es_rxn_admin']) && $_SESSION['es_rxn_admin'] == 1);

if (!$isTenantAdmin && !$isGlobalAdmin) {
    unset($defaultCards['usuarios']);
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
<!DOCTYPE html>
<html lang="es" <?= \App\Core\Helpers\UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entorno Operativo - rxnTiendasIA</title>
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
    </style>
</head>
<body class="d-flex flex-column min-vh-100 p-4 p-md-5">

    <div class="container-fluid" style="max-width: 1200px;">
        
        <div class="d-flex justify-content-between align-items-center mb-5 pb-2 border-bottom border-secondary border-opacity-25">
            <div>
                <h1 class="hero-title mb-1"><i class="bi bi-rocket-takeoff"></i> Entorno Operativo</h1>
                <p class="text-muted mb-0">Portal corporativo <span class="badge bg-secondary ms-1">Empresa #<?= $_SESSION['empresa_id'] ?? '' ?></span></p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="text-secondary small d-none d-md-block fw-bold"><i class="bi bi-person"></i> <?= htmlspecialchars($_SESSION['user_name'] ?? 'Usuario') ?></span>
                <a href="/rxnTiendasIA/public/" class="btn btn-outline-secondary rounded-pill px-4"><i class="bi bi-arrow-left"></i> Volver al Launcher</a>
            </div>
        </div>

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
                    body: JSON.stringify({ order: currentOrder })
                })
                .then(response => response.json())
                .catch(err => console.error("Error interconexión D&D", err));
            }
        });
    });
    </script>
</body>
</html>
