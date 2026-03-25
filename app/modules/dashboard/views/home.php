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
                            <a href="/rxnTiendasIA/public/mi-empresa/pedidos" class="btn btn-dark fw-bold text-white w-100 mb-2">🛍️ Pedidos Web</a>
                            <a href="/rxnTiendasIA/public/mi-empresa/clientes" class="btn btn-info fw-bold text-white w-100 mb-2">👥 Clientes Web</a>
                            <a href="/rxnTiendasIA/public/mi-empresa/articulos" class="btn btn-warning fw-bold text-dark w-100 mb-2">🎁 Catálogo de Artículos</a>
                            <a href="/rxnTiendasIA/public/mi-empresa/usuarios" class="btn btn-light text-primary fw-bold w-100 mb-2">Administrar Cuentas</a>
                            <a href="/rxnTiendasIA/public/mi-empresa/configuracion" class="btn btn-light text-primary fw-bold w-100 mb-2">Mi Configuración</a>
                            <a href="/rxnTiendasIA/public/mi-perfil" class="btn btn-secondary text-white fw-bold w-100 mb-2">👤 Mi Perfil</a>
                            <a href="/rxnTiendasIA/public/logout" class="btn btn-outline-light btn-sm w-100 mt-3">Cerrar Sesión</a>
                        </div>
                    <?php else: ?>
                        <a href="/rxnTiendasIA/public/login" class="btn btn-light text-primary fw-bold w-100 mt-auto">Iniciar Sesión</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
