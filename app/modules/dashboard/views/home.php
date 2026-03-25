<!DOCTYPE html>
<html lang="es" <?= \App\Core\Helpers\UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Launcher Administrativo - rxnTiendasIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
    </style>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 py-5">

    <div class="container" style="max-width: 900px;">
        
        <div class="text-center mb-5 pb-3">
            <h1 class="hero-title mb-2">Bienvenido a rxnTiendasIA</h1>
            <p class="hero-subtitle">Plataforma central de administración multiempresa.</p>
        </div>

        <div class="row g-4 justify-content-center">
            
            <?php if (!empty($_SESSION['es_rxn_admin']) && $_SESSION['es_rxn_admin'] == 1): ?>
            <!-- TARJETA 1: RXN BACKOFFICE -->
            <div class="col-md-6 col-lg-5">
                <div class="card launcher-card text-center p-4 h-100 position-relative shadow-sm">
                    <div class="card-body d-flex flex-column align-items-center justify-content-center p-0">
                        <div class="launcher-icon">🏢</div>
                        <h4 class="fw-bold mb-2 text-white">RXN Backoffice</h4>
                        <p class="text-muted small px-3">Administración global de licenciatarios, tenants y configuraciones master.</p>
                        <a href="/rxnTiendasIA/public/admin/dashboard" class="stretched-link"></a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- TARJETA 2: ENTORNO OPERATIVO -->
            <div class="col-md-6 col-lg-5">
                <div class="card launcher-card text-center p-4 h-100 position-relative shadow-sm">
                    <div class="card-body d-flex flex-column align-items-center justify-content-center p-0">
                        <div class="launcher-icon">🚀</div>
                        <h4 class="fw-bold mb-2 text-white">Entorno Operativo</h4>
                        <p class="text-muted small px-3">Tu propio punto de venta, gestión de clientes, catálogos y pedidos web.</p>
                        <div class="mt-4 pt-3 border-top border-secondary border-opacity-25 w-100">
                            <span class="badge bg-dark text-light border border-secondary px-3 py-2 fw-medium">
                                👤 <?= htmlspecialchars($_SESSION['user_name'] ?? 'Usuario') ?> <span class="opacity-50 ms-1">(Empresa #<?= $_SESSION['empresa_id'] ?? '' ?>)</span>
                            </span>
                        </div>
                        <a href="/rxnTiendasIA/public/mi-empresa/dashboard" class="stretched-link"></a>
                    </div>
                </div>
            </div>

        </div>

        <!-- Footer / Logout de Emergencia -->
        <div class="text-center mt-5">
            <a href="/rxnTiendasIA/public/logout" class="btn btn-outline-secondary btn-sm rounded-pill px-4 text-muted border-secondary border-opacity-50">Cerrar Sesión</a>
        </div>

    </div>

</body>
</html>
