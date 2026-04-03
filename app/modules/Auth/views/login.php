<!DOCTYPE html>
<html lang="es" <?= \App\Core\Helpers\UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Entorno Operativo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/css/rxn-theming.css" rel="stylesheet">
</head>
<body class="rxn-page-shell rxn-auth-screen">
    <div class="container rxn-responsive-container rxn-form-shell-sm rxn-auth-shell">
        <div class="text-center mb-4">
            <img src="/uploads/empresas/1/branding/logo_1774467026.png" alt="Reaxion Soluciones Inteligentes" class="rxn-auth-logo mb-3">
            <p class="rxn-auth-eyebrow mb-3">Suite RXN</p>
            <h2 class="fw-bold rxn-auth-title">Entorno Operativo</h2>
            <p class="text-muted mb-0 rxn-auth-subtitle">Inicia sesión en tu espacio de trabajo para administrar la suite Reaxion con una experiencia simple, segura y responsive.</p>
        </div>

        <?php 
        $msg = $_GET['msg'] ?? '';
        if ($msg === 'revisar_correo'): ?>
            <div class="alert alert-info text-center small">Cuenta creada. Por favor revise el correo electrónico para verificarla antes de continuar.</div>
        <?php elseif ($msg === 'cuenta_verificada'): ?>
            <div class="alert alert-success text-center small">¡Cuenta verificada con éxito! Ya puede iniciar sesión.</div>
        <?php elseif ($msg === 'pass_actualizada'): ?>
            <div class="alert alert-success text-center small">Contraseña actualizada correctamente.</div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger text-center"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card rxn-form-card rxn-auth-card p-4 p-lg-5">
            <form action="/login" method="POST">
                
                <div class="mb-3">
                    <label for="email" class="form-label">Correo Electrónico</label>
                    <input type="email" class="form-control" id="email" name="email" required
                           value="<?= htmlspecialchars($old_email ?? '') ?>">
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>

                <button type="submit" class="btn btn-primary w-100 fw-bold">Ingresar</button>
            </form>
            
            <div class="text-center mt-3 d-flex flex-column gap-2">
                <a href="/auth/forgot" class="text-decoration-none small text-secondary">¿Olvidaste tu contraseña?</a>
                <a href="/auth/resend-verify" class="text-decoration-none small text-secondary">Aún no recibí el enlace de validación</a>
            </div>

            <div class="text-center mt-4">
                <a href="/" class="text-decoration-none text-muted small">← Volver al Home</a>
            </div>

            <div class="text-center mt-4 pt-3 border-top rxn-auth-footer">
                <a href="https://reaxion.com.ar/" target="_blank" rel="noopener noreferrer" class="text-decoration-none small d-block mb-2">reaxion.com.ar</a>
                <a href="mailto:soporte@reaxion.com.ar" class="text-decoration-none text-muted small">soporte@reaxion.com.ar</a>
            </div>
        </div>
    </div>
    <script src="/js/rxn-shortcuts.js"></script>
</body>
</html>
