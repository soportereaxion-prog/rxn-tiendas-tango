<!DOCTYPE html>
<html lang="es" <?= \App\Core\Helpers\UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio - rxnTiendasIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .hero { padding: 4rem 2rem; background: #fff; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
    </style>
    <link href="/rxnTiendasIA/public/css/rxn-theming.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="hero text-center">
            <h1 class="display-5 fw-bold">Bienvenido a rxnTiendasIA</h1>
            <p class="lead text-muted mb-4">Plataforma central de administración multiempresa.</p>
            
            <div class="d-flex justify-content-center gap-3 mt-5">
                <?php if (isset($_SESSION['es_rxn_admin']) && $_SESSION['es_rxn_admin'] == 1): ?>
                <div class="card p-4 shadow-sm border-0 bg-light" style="width: 280px;">
                    <h5 class="text-secondary fw-bold">🏢 RXN Backoffice</h5>
                    <p class="small text-muted mb-4">Administración global de licenciatarios.</p>
                    <a href="/rxnTiendasIA/public/empresas" class="btn btn-outline-dark w-100 mt-auto">Listado de Empresas</a>
                </div>
                <?php endif; ?>
                
                <div class="card p-4 shadow-sm border-0 bg-primary text-white" style="width: 280px;">
                    <h5 class="fw-bold">🚀 Entorno Operativo</h5>
                    <p class="small text-light mb-4 text-opacity-75">Tu propio punto de venta y gestión.</p>
                    
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="mt-auto">
                            <p class="mb-2 small">👤 <?= htmlspecialchars($_SESSION['user_name'] ?? 'Usuario') ?> <br>(Empresa #<?= $_SESSION['empresa_id'] ?? '' ?>)</p>
                            
                            <?php
                                $defaultCards = [
                                    'pedidos_web' => '<a href="/rxnTiendasIA/public/mi-empresa/pedidos" class="btn btn-dark fw-bold text-white w-100">🛍️ Pedidos Web</a>',
                                    'clientes_web' => '<a href="/rxnTiendasIA/public/mi-empresa/clientes" class="btn btn-info fw-bold text-white w-100">👥 Clientes Web</a>',
                                    'articulos' => '<a href="/rxnTiendasIA/public/mi-empresa/articulos" class="btn btn-warning fw-bold text-dark w-100">🎁 Catálogo de Artículos</a>',
                                    'usuarios' => '<a href="/rxnTiendasIA/public/mi-empresa/usuarios" class="btn btn-light text-primary fw-bold w-100">Administrar Cuentas</a>',
                                    'configuracion' => '<a href="/rxnTiendasIA/public/mi-empresa/configuracion" class="btn btn-light text-primary fw-bold w-100">Mi Configuración</a>',
                                    'mi_perfil' => '<a href="/rxnTiendasIA/public/mi-perfil" class="btn btn-secondary text-white fw-bold w-100">👤 Mi Perfil</a>'
                                ];

                                $savedOrder = !empty($_SESSION['dashboard_order']) ? json_decode($_SESSION['dashboard_order'], true) : [];
                                $finalCards = [];
                                
                                if (is_array($savedOrder)) {
                                    foreach ($savedOrder as $id) {
                                        if (isset($defaultCards[$id])) {
                                            $finalCards[$id] = $defaultCards[$id];
                                            unset($defaultCards[$id]);
                                        }
                                    }
                                }
                                foreach ($defaultCards as $id => $html) {
                                    $finalCards[$id] = $html;
                                }
                            ?>

                            <div id="dashboard-cards" class="d-flex flex-column gap-2 mb-3">
                                <?php foreach ($finalCards as $id => $html): ?>
                                    <div class="rxn-card" data-id="<?= htmlspecialchars($id) ?>" style="cursor: grab;">
                                        <?= $html ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <a href="/rxnTiendasIA/public/logout" class="btn btn-outline-light btn-sm w-100 mt-3">Cerrar Sesión</a>
                        </div>
                    <?php else: ?>
                        <a href="/rxnTiendasIA/public/login" class="btn btn-light text-primary fw-bold w-100 mt-auto">Iniciar Sesión</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var el = document.getElementById('dashboard-cards');
        if (el) {
            new Sortable(el, {
                animation: 150,
                ghostClass: 'opacity-50',
                dragClass: 'shadow-sm',
                onEnd: function (evt) {
                    let order = [];
                    el.querySelectorAll('.rxn-card').forEach(card => {
                        order.push(card.getAttribute('data-id'));
                    });
                    
                    fetch('/rxnTiendasIA/public/mi-perfil/dashboard-order', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ order: order })
                    });
                }
            });
        }
    });
    </script>
</body>
</html>
