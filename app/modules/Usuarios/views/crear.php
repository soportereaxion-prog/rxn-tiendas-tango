<!DOCTYPE html>
<html lang="es" <?= \App\Core\Helpers\UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Usuario - rxnTiendasIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/rxnTiendasIA/public/css/rxn-theming.css" rel="stylesheet">
</head>
<body class="rxn-page-shell">
    <?php
    $area = $area ?? 'tiendas';
    $basePath = $basePath ?? '/rxnTiendasIA/public/mi-empresa/usuarios';
    $indexPath = $basePath . '?' . http_build_query(['area' => $area]);
    ?>
    <div class="container mt-5 mb-5 rxn-responsive-container rxn-form-shell">
        <div class="rxn-module-header mb-4">
            <div>
                <h2 class="fw-bold mb-1">Alta de Usuario</h2>
                <p class="text-muted mb-0">Crear un nuevo acceso para el entorno operativo.</p>
            </div>
            <a href="<?= htmlspecialchars($indexPath) ?>" class="btn btn-outline-secondary">Volver al listado</a>
        </div>

        <?php
        $moduleNotesKey = 'usuarios';
        $moduleNotesLabel = 'Usuarios';
        require BASE_PATH . '/app/shared/views/components/module_notes_panel.php';
        ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card rxn-form-card border-0 shadow-sm">
            <div class="card-body p-4 p-lg-5">
                <form action="<?= htmlspecialchars($indexPath) ?>" method="POST">
                    <div class="rxn-form-section">
                        <div class="rxn-form-section-title">Credenciales base</div>
                        <div class="rxn-form-grid">
                            <div class="rxn-form-span-6">
                                <label for="nombre" class="form-label">Nombre Completo</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required value="<?= htmlspecialchars($old['nombre'] ?? '') ?>">
                            </div>

                            <div class="rxn-form-span-6">
                                <label for="email" class="form-label">Correo Electrónico</label>
                                <input type="email" class="form-control" id="email" name="email" required value="<?= htmlspecialchars($old['email'] ?? '') ?>">
                            </div>

                            <?php if (isset($isGlobalAdmin) && $isGlobalAdmin && !empty($empresas)): ?>
                            <div class="rxn-form-span-6">
                                <label for="empresa_id" class="form-label">Empresa Asignada (Gestión Local)</label>
                                <select class="form-select border-primary" id="empresa_id" name="empresa_id">
                                    <?php foreach ($empresas as $emp): ?>
                                        <option value="<?= $emp->id ?>" <?= (isset($old['empresa_id']) && $old['empresa_id'] == $emp->id) ? 'selected' : '' ?>>
                                            [#<?= $emp->id ?>] <?= htmlspecialchars($emp->nombre) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Como Master RXN puedes delegar este usuario a cualquier Inquilino.</small>
                            </div>
                            <?php endif; ?>

                            <div class="rxn-form-span-6">
                                <label for="password" class="form-label">Contraseña</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                        </div>
                    </div>

                    <div class="rxn-form-section">
                        <div class="rxn-form-section-title">Permisos y estado</div>
                        <div class="rxn-form-switches">
                            <div class="rxn-form-switch-card">
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="activo" name="activo" checked>
                                    <label class="form-check-label fw-semibold" for="activo">Usuario activo</label>
                                    <div class="form-text mb-0">Permite iniciar sesión y operar normalmente.</div>
                                </div>
                            </div>

                            <?php if (!empty($canManageAdminPrivileges)): ?>
                            <div class="rxn-form-switch-card">
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="es_admin" name="es_admin" <?= (isset($old['es_admin']) && $old['es_admin'] === 'on') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="es_admin">Privilegios de administrador</label>
                                    <div class="form-text mb-0">Habilita gestión operativa ampliada dentro del tenant.</div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="rxn-form-actions">
                        <a href="<?= htmlspecialchars($indexPath) ?>" class="btn btn-light border">Cancelar</a>
                        <button type="submit" class="btn btn-primary px-4 fw-bold">Crear Usuario</button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</body>
</html>
