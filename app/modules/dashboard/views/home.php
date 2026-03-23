<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio - rxnTiendasIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .hero { padding: 4rem 2rem; background: #fff; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="hero text-center">
            <h1 class="display-5 fw-bold">Bienvenido a rxnTiendasIA</h1>
            <p class="lead text-muted mb-4">Plataforma central de administración multiempresa.</p>
            
            <div class="d-flex justify-content-center gap-3 mt-5">
                <div class="card p-4 shadow-sm border-0 bg-light" style="width: 280px;">
                    <h5 class="text-secondary fw-bold">🏢 RXN Backoffice</h5>
                    <p class="small text-muted mb-4">Administración global de licenciatarios.</p>
                    <a href="/rxnTiendasIA/public/empresas" class="btn btn-outline-dark w-100 mt-auto">Listado de Empresas</a>
                </div>
                
                <div class="card p-4 shadow-sm border-0 bg-primary text-white" style="width: 280px;">
                    <h5 class="fw-bold">🚀 Entorno Operativo</h5>
                    <p class="small text-light mb-4 text-opacity-75">Tu propio punto de venta y gestión.</p>
                    <a href="/rxnTiendasIA/public/mi-empresa/configuracion" class="btn btn-light text-primary fw-bold w-100 mt-auto">Ir a Mi Configuración</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
