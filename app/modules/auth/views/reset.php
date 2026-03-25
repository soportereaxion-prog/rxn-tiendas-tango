<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/rxnTiendasIA/public/css/rxn-ui.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="height: 100vh;">
    <div class="card shadow-sm p-4" style="max-width: 400px; width: 100%;">
        <div class="text-center mb-4">
            <h4 class="text-dark fw-bold">Nueva Contraseña</h4>
            <p class="text-secondary small">Elegí una clave segura para tu cuenta.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="/rxnTiendasIA/public/auth/reset" method="POST">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token ?? '') ?>">
            
            <div class="mb-3">
                <label class="form-label small fw-medium text-secondary">Nueva Contraseña</label>
                <input type="password" name="password" class="form-control" required minlength="6">
            </div>
            
            <div class="mb-4">
                <label class="form-label small fw-medium text-secondary">Confirmar Contraseña</label>
                <input type="password" name="password_confirmation" class="form-control" required minlength="6">
            </div>

            <button type="submit" class="btn btn-success w-100 fw-bold">Guardar e Ingresar</button>
        </form>
    </div>
</body>
</html>
