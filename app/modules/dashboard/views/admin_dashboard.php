<!DOCTYPE html>
<html lang="es" <?= \App\Core\Helpers\UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RXN Backoffice - rxnTiendasIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
    </style>
</head>
<body class="d-flex flex-column min-vh-100 p-4 p-md-5">

    <div class="container-fluid" style="max-width: 1200px;">
        
        <div class="d-flex justify-content-between align-items-center mb-5 pb-2 border-bottom border-secondary border-opacity-25">
            <div>
                <h1 class="hero-title mb-1">🏢 RXN Backoffice</h1>
                <p class="text-muted mb-0">Gestión global de licenciatarios y configuración maestra.</p>
            </div>
            <div>
                <a href="/rxnTiendasIA/public/" class="btn btn-outline-secondary rounded-pill px-4">⬅️ Volver al Launcher</a>
            </div>
        </div>

        <div class="row g-4">
            
            <div class="col-sm-6 col-lg-4">
                <div class="card module-card text-center p-4 h-100 position-relative shadow-sm">
                    <div class="card-body d-flex flex-column align-items-center justify-content-center p-0">
                        <div class="module-icon">🏢</div>
                        <h5 class="fw-bold mb-2 text-white">Listado de Empresas</h5>
                        <p class="text-muted small px-2">ABM de tenants, asignación de licencias y suspensión de cuentas.</p>
                        <a href="/rxnTiendasIA/public/empresas" class="stretched-link"></a>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4">
                <div class="card module-card text-center p-4 h-100 position-relative shadow-sm" style="opacity: 0.6;">
                    <div class="card-body d-flex flex-column align-items-center justify-content-center p-0">
                        <div class="module-icon">⚙️</div>
                        <h5 class="fw-bold mb-2 text-white">Configuración Global</h5>
                        <p class="text-muted small px-2">Ajustes SMTP maestros para el pool de correos RXN.</p>
                        <a href="/rxnTiendasIA/public/admin/smtp-global" class="stretched-link"></a>
                    </div>
                </div>
            </div>
            
        </div>

    </div>

</body>
</html>
