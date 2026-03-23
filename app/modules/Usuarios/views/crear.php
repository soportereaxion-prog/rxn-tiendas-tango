<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Usuario - rxnTiendasIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5" style="max-width: 600px;">
        <div class="mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold">Alta de Usuario</h2>
                <p class="text-muted">Crear un nuevo acceso para el entorno operativo.</p>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form action="/rxnTiendasIA/public/mi-empresa/usuarios" method="POST">
                    
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre Completo</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required
                               value="<?= htmlspecialchars($old['nombre'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Correo Electrónico</label>
                        <input type="email" class="form-control" id="email" name="email" required
                               value="<?= htmlspecialchars($old['email'] ?? '') ?>">
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="activo" name="activo" checked>
                            <label class="form-check-label" for="activo">Usuario Activo</label>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="es_admin" name="es_admin" 
                                   <?= (isset($old['es_admin']) && $old['es_admin'] === 'on') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="es_admin">Posee privilegios de Administrador</label>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="/rxnTiendasIA/public/mi-empresa/usuarios" class="btn btn-light border">Cancelar</a>
                        <button type="submit" class="btn btn-primary px-4 fw-bold">Crear Usuario</button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</body>
</html>
