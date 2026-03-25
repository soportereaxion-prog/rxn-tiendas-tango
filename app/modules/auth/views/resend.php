<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reenviar Verificación</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/rxnTiendasIA/public/css/rxn-ui.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="height: 100vh;">
    <div class="card shadow-sm p-4" style="max-width: 400px; width: 100%;">
        <div class="text-center mb-4">
            <h4 class="text-dark fw-bold">Reenviar Correo</h4>
            <p class="text-secondary small">Si no recibiste tu enlace de validación, te enviamos uno nuevo.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success py-2 small"><?= htmlspecialchars($success) ?></div>
        <?php else: ?>
            <form action="/rxnTiendasIA/public/auth/resend-verify" method="POST">
                <div class="mb-3">
                    <label class="form-label small fw-medium text-secondary">Correo Electrónico</label>
                    <input type="email" name="email" class="form-control" required placeholder="tu@email.com">
                </div>
                <button type="submit" class="btn btn-primary w-100 fw-bold">Reenviar Enlace</button>
            </form>
        <?php endif; ?>

        <div class="text-center mt-4">
            <a href="javascript:history.back()" class="text-decoration-none small text-secondary">← Volver</a>
        </div>
    </div>
</body>
</html>
