<!DOCTYPE html>
<html lang="es" <?= \App\Core\Helpers\UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuario - rxnTiendasIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/rxnTiendasIA/public/css/rxn-theming.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5" style="max-width: 600px;">
        <div class="mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold">Editar Usuario</h2>
                <p class="text-muted mb-0">Modificando la cuenta de <strong><?= htmlspecialchars($usuario->nombre) ?></strong> (#<?= $usuario->id ?>) 
                    <span class="badge bg-secondary ms-2 align-middle">Empresa #<?= $usuario->empresa_id ?></span>
                </p>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form action="/rxnTiendasIA/public/mi-empresa/usuarios/<?= $usuario->id ?>" method="POST">
                    
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre Completo</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required
                               value="<?= htmlspecialchars($old['nombre'] ?? $usuario->nombre) ?>">
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Correo Electrónico</label>
                        <input type="email" class="form-control" id="email" name="email" required
                               value="<?= htmlspecialchars($old['email'] ?? $usuario->email) ?>">
                    </div>

                    <?php if (isset($isGlobalAdmin) && $isGlobalAdmin && !empty($empresas)): ?>
                    <div class="mb-3">
                        <label for="empresa_id" class="form-label">Transferir de Empresa (Gestión Master)</label>
                        <select class="form-select border-primary" id="empresa_id" name="empresa_id">
                            <?php foreach ($empresas as $emp): ?>
                                <option value="<?= $emp->id ?>" <?= ((isset($old['empresa_id']) ? $old['empresa_id'] : $usuario->empresa_id) == $emp->id) ? 'selected' : '' ?>>
                                    [#<?= $emp->id ?>] <?= htmlspecialchars($emp->nombre) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Cambiar esto transferirá el administrador instantáneamente a otro Inquilino.</small>
                    </div>
                    <?php endif; ?>

                    <div class="mb-4">
                        <label for="password" class="form-label">Nueva Contraseña <small class="text-muted">(Opcional)</small></label>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Dejar vació para mantener la actual">
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="activo" name="activo" 
                                   <?= (isset($old['activo']) ? ($old['activo']==='on') : ($usuario->activo == 1)) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="activo">Usuario Activo</label>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="es_admin" name="es_admin" 
                                   <?= (isset($old['es_admin']) ? ($old['es_admin']==='on') : ($usuario->es_admin == 1)) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="es_admin">Posee privilegios de Administrador</label>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="/rxnTiendasIA/public/mi-empresa/usuarios" class="btn btn-light border">Cancelar</a>
                        <button type="submit" class="btn btn-primary px-4 fw-bold">Actualizar Datos</button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</body>
</html>
