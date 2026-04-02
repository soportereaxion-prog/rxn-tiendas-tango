<!DOCTYPE html>
<html lang="es" <?= \App\Core\Helpers\UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reenviar Verificación</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/rxnTiendasIA/public/css/rxn-ui.css" rel="stylesheet">
    <link href="/rxnTiendasIA/public/css/rxn-theming.css" rel="stylesheet">
</head>
<body class="rxn-page-shell rxn-auth-screen">
    <div class="container rxn-responsive-container rxn-form-shell-sm rxn-auth-shell">
    <div class="card rxn-form-card rxn-auth-card shadow-sm p-4 p-lg-5 w-100">
        <div class="text-center mb-4">
            <img src="/rxnTiendasIA/public/uploads/empresas/1/branding/logo_1774467026.png" alt="Reaxion Soluciones Inteligentes" class="rxn-auth-logo mb-3">
            <p class="rxn-auth-eyebrow mb-3">Suite RXN</p>
            <h4 class="rxn-auth-title fw-bold">Reenviar Correo</h4>
            <p class="text-secondary small rxn-auth-subtitle">Si no recibiste tu enlace de validación, te enviamos uno nuevo al instante.</p>
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

        <div class="text-center mt-4 pt-3 border-top rxn-auth-footer">
            <a href="https://reaxion.com.ar/" target="_blank" rel="noopener noreferrer" class="text-decoration-none small d-block mb-2">reaxion.com.ar</a>
            <a href="mailto:soporte@reaxion.com.ar" class="text-decoration-none text-muted small">soporte@reaxion.com.ar</a>
        </div>
    </div>
    </div>
    <script src="/rxnTiendasIA/public/js/rxn-shortcuts.js"></script>
</body>
</html>
