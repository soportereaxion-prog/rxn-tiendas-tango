<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Empresa - rxnTiendasIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: none; }
    </style>
</head>
<body>
    <div class="container mt-5" style="max-width: 600px;">
        <div class="mb-4">
            <h2>Alta de Empresa</h2>
            <p class="text-muted">Crear un nuevo registro empresarial.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form action="/rxnTiendasIA/public/empresas" method="POST">
                    
                    <div class="mb-3">
                        <label for="codigo" class="form-label">Código (Obligatorio)</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" required
                               value="<?= htmlspecialchars($old['codigo'] ?? '') ?>">
                        <div class="form-text">Identificador único. ej: EMP-001</div>
                    </div>

                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre (Obligatorio)</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required
                               value="<?= htmlspecialchars($old['nombre'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="razon_social" class="form-label">Razón Social</label>
                        <input type="text" class="form-control" id="razon_social" name="razon_social"
                               value="<?= htmlspecialchars($old['razon_social'] ?? '') ?>">
                    </div>

                    <div class="mb-4">
                        <label for="cuit" class="form-label">CUIT</label>
                        <input type="text" class="form-control" id="cuit" name="cuit"
                               value="<?= htmlspecialchars($old['cuit'] ?? '') ?>">
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="/rxnTiendasIA/public/empresas" class="btn btn-light">Cancelar</a>
                        <button type="submit" class="btn btn-primary px-4">Guardar Empresa</button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</body>
</html>
