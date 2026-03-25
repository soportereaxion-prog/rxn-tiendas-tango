<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Entorno Operativo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: none; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center" style="height: 100vh;">
    <div class="container" style="max-width: 400px;">
        <div class="text-center mb-4">
            <h2 class="fw-bold text-primary">Entorno Operativo</h2>
            <p class="text-muted">Inicia sesión en tu espacio de trabajo</p>
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

        <div class="card p-4">
            <form action="/rxnTiendasIA/public/login" method="POST">
                
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
                <a href="/rxnTiendasIA/public/auth/forgot" class="text-decoration-none small text-secondary">¿Olvidaste tu contraseña?</a>
                <a href="/rxnTiendasIA/public/auth/resend-verify" class="text-decoration-none small text-secondary">Aún no recibí el enlace de validación</a>
            </div>

            <div class="text-center mt-4">
                <a href="/rxnTiendasIA/public/" class="text-decoration-none text-muted small">← Volver al Home</a>
            </div>
        </div>
    </div>
</body>
</html>
