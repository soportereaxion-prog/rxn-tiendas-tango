<!DOCTYPE html>
<html lang="es" <?= \App\Core\Helpers\UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuario - rxnTiendasIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/rxnTiendasIA/public/css/rxn-theming.css" rel="stylesheet">
</head>
<body class="rxn-page-shell">
    <?php
    $area = $area ?? 'tiendas';
    $basePath = $basePath ?? '/rxnTiendasIA/public/mi-empresa/usuarios';
    $indexPath = $basePath . '?' . http_build_query(['area' => $area]);
    $formPath = $basePath . '/' . (int) $usuario->id . '?' . http_build_query(['area' => $area]);
    ?>
    <div class="container mt-5 mb-5 rxn-responsive-container rxn-form-shell">
        <div class="rxn-module-header mb-4">
            <div>
                <h2 class="fw-bold mb-1">Editar Usuario</h2>
                <p class="text-muted mb-0">Modificando la cuenta de <strong><?= htmlspecialchars($usuario->nombre) ?></strong> (#<?= $usuario->id ?>) 
                    <span class="badge bg-secondary ms-2 align-middle">Empresa #<?= $usuario->empresa_id ?></span>
                </p>
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
                <form action="<?= htmlspecialchars($formPath) ?>" method="POST">
                    <div class="rxn-form-section">
                        <div class="rxn-form-section-title">Datos de acceso</div>
                        <div class="rxn-form-grid">
                            <div class="rxn-form-span-6">
                                <label for="nombre" class="form-label">Nombre Completo</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required value="<?= htmlspecialchars($old['nombre'] ?? $usuario->nombre) ?>">
                            </div>

                            <div class="rxn-form-span-6">
                                <label for="email" class="form-label">Correo Electrónico</label>
                                <input type="email" class="form-control" id="email" name="email" required value="<?= htmlspecialchars($old['email'] ?? $usuario->email) ?>">
                            </div>

                            <?php if (isset($isGlobalAdmin) && $isGlobalAdmin && !empty($empresas)): ?>
                            <div class="rxn-form-span-6">
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

                            <div class="rxn-form-span-6">
                                <label for="password" class="form-label">Nueva Contraseña <small class="text-muted">(Opcional)</small></label>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Dejar vació para mantener la actual">
                            </div>
                        </div>
                    </div>

                    <div class="rxn-form-section">
                        <div class="rxn-form-section-title">Integración Tango</div>
                        <div class="rxn-form-grid">
                            <div class="rxn-form-span-12">
                                <label for="tango_perfil_pedido" class="form-label">Perfil de Pedido (Operativa CRM/Ventas)</label>
                                <select class="form-select" id="tango_perfil_pedido" name="tango_perfil_pedido">
                                    <option value="">(No asociado / Usar Predeterminado)</option>
                                    <?php if (!empty($tangoProfiles)): ?>
                                        <?php foreach ($tangoProfiles as $perfil): ?>
                                            <?php 
                                            $perfilVal = $perfil['id'] . '|' . $perfil['codigo'] . '|' . $perfil['nombre'];
                                            $currentId = isset($old['tango_perfil_pedido']) 
                                                            ? explode('|', $old['tango_perfil_pedido'])[0] 
                                                            : $usuario->tango_perfil_pedido_id;
                                            $isSelected = ((int)$currentId === (int)$perfil['id']) ? 'selected' : '';
                                            ?>
                                            <option value="<?= htmlspecialchars($perfilVal) ?>" <?= $isSelected ?>>
                                                [<?= htmlspecialchars($perfil['codigo']) ?>] <?= htmlspecialchars($perfil['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled>Sin acceso a Tango o sin perfiles configurados.</option>
                                    <?php endif; ?>
                                </select>
                                <div class="form-text">Si se asocia, todas las cabeceras de pedidos o presupuestos emitidos por esta persona se generarán con los vendedores y talonarios definidos en este Perfil de Tango.</div>
                            </div>
                        </div>
                    </div>

                    <div class="rxn-form-section">
                        <div class="rxn-form-section-title">Permisos y estado</div>
                        <div class="rxn-form-switches">
                            <div class="rxn-form-switch-card">
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="activo" name="activo" <?= (isset($old['activo']) ? ($old['activo']==='on') : ($usuario->activo == 1)) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="activo">Usuario activo</label>
                                    <div class="form-text mb-0">Permite seguir operando dentro del entorno.</div>
                                </div>
                            </div>

                            <?php if (!empty($canManageAdminPrivileges)): ?>
                            <div class="rxn-form-switch-card">
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="es_admin" name="es_admin" <?= (isset($old['es_admin']) ? ($old['es_admin']==='on') : ($usuario->es_admin == 1)) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="es_admin">Privilegios de administrador</label>
                                    <div class="form-text mb-0">Amplía acceso a módulos sensibles del tenant.</div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="rxn-form-actions">
                        <a href="<?= htmlspecialchars($indexPath) ?>" class="btn btn-light border">Cancelar</a>
                        <button type="submit" class="btn btn-primary px-4 fw-bold">Actualizar Datos</button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</body>
</html>
