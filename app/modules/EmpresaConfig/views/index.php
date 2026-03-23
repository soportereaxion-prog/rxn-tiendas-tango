<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Empresa - rxnTiendasIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: none; }
    </style>
</head>
<body>
    <div class="container mt-5" style="max-width: 600px;">
        <div class="mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h2>Configuración de la Empresa</h2>
                <p class="text-muted">Gestión del entorno operativo actual.</p>
            </div>
            <span class="badge bg-info text-dark">Contexto Activo: ID #<?= htmlspecialchars((string) \App\Core\Context::getEmpresaId()) ?></span>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">Configuración guardada exitosamente.</div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form action="/rxnTiendasIA/public/mi-empresa/configuracion" method="POST">
                    
                    <div class="mb-3">
                        <label for="nombre_fantasia" class="form-label">Nombre de Fantasía</label>
                        <input type="text" class="form-control" id="nombre_fantasia" name="nombre_fantasia"
                               value="<?= htmlspecialchars($old['nombre_fantasia'] ?? ($config->nombre_fantasia ?? '')) ?>">
                    </div>

                    <div class="mb-3">
                        <label for="email_contacto" class="form-label">Email de Contacto</label>
                        <input type="email" class="form-control" id="email_contacto" name="email_contacto"
                               value="<?= htmlspecialchars($old['email_contacto'] ?? ($config->email_contacto ?? '')) ?>">
                    </div>

                    <div class="mb-4">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <input type="text" class="form-control" id="telefono" name="telefono"
                               value="<?= htmlspecialchars($old['telefono'] ?? ($config->telefono ?? '')) ?>">
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="/rxnTiendasIA/public/" class="btn btn-light">Volver a Inicio</a>
                        <button type="submit" class="btn btn-primary px-4">Guardar Configuración</button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</body>
</html>
