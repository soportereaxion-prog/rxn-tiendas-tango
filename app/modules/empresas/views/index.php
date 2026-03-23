<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo Empresas - rxnTiendasIA</title>
    <!-- CSS Bootstrap 5 CDN para pruebas rápidas como base -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: none; }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Gestión de Empresas</h2>
            <div>
                <a href="/rxnTiendasIA/public/" class="btn btn-outline-secondary me-2">Volver al Inicio</a>
                <a href="/rxnTiendasIA/public/empresas/crear" class="btn btn-primary">Nueva Empresa</a>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Razón Social</th>
                            <th>CUIT</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($empresas)): ?>
                        <tr><td colspan="6" class="text-center py-4">No hay empresas registradas.</td></tr>
                        <?php else: ?>
                            <?php foreach ($empresas as $empresa): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)$empresa->id) ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($empresa->codigo) ?></span></td>
                                <td class="fw-bold"><?= htmlspecialchars($empresa->nombre) ?></td>
                                <td><?= htmlspecialchars((string)$empresa->razon_social) ?></td>
                                <td><?= htmlspecialchars((string)$empresa->cuit) ?></td>
                                <td>
                                    <?php if ($empresa->activa): ?>
                                        <span class="badge bg-success">Activa</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactiva</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
