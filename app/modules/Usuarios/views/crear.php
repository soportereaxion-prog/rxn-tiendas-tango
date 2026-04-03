<?php
$area = $area ?? 'tiendas';
$basePath = $basePath ?? '/mi-empresa/usuarios';
$indexPath = $basePath . '?' . http_build_query(['area' => $area]);

$pageTitle = 'Nuevo Usuario - rxn_suite';

ob_start();
?>
    <div class="container mt-5 mb-5 rxn-responsive-container rxn-form-shell">
        <?php
        \App\Core\View::render('app/shared/views/partials/page_header.php', [
            'title' => 'Alta de Usuario',
            'subtitle' => 'Crear un nuevo acceso para el entorno operativo.',
            'backUrl' => $indexPath,
            'backLabel' => 'Volver al listado'
        ]);
        ?>

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
                                <label for="email" class="form-label">Correo ElectrÃ³nico</label>
                                <input type="email" class="form-control" id="email" name="email" required value="<?= htmlspecialchars($old['email'] ?? '') ?>">
                            </div>

                            <?php if (isset($isGlobalAdmin) && $isGlobalAdmin && !empty($empresas)): ?>
                            <div class="rxn-form-span-6">
                                <label for="empresa_id" class="form-label">Empresa Asignada (GestiÃ³n Local)</label>
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
                                <label for="password" class="form-label">ContraseÃ±a</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                        </div>
                    </div>

                    <div class="rxn-form-section">
                        <div class="rxn-form-section-title">IntegraciÃ³n Tango</div>
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
                                                            : null;
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
                                <div class="form-text">Si se asocia, todas las cabeceras de pedidos o presupuestos emitidos por esta persona se generarÃ¡n con los vendedores y talonarios definidos en este Perfil de Tango.</div>
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
                                    <div class="form-text mb-0">Permite iniciar sesiÃ³n y operar normalmente.</div>
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

                            <?php if (isset($isGlobalAdmin) && $isGlobalAdmin): ?>
                            <div class="rxn-form-switch-card border-danger border-opacity-50" style="background-color: rgba(220,53,69,0.03);">
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input bg-danger border-danger" type="checkbox" role="switch" id="es_rxn_admin" name="es_rxn_admin" <?= (isset($old['es_rxn_admin']) && $old['es_rxn_admin'] === 'on') ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold text-danger" for="es_rxn_admin">Privilegios Super Administrador (RXN)</label>
                                    <div class="form-text mb-0 text-danger opacity-75">Otorga el control global de sistema sobre todos los Módulos de Mantenimiento y Tenants.</div>
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
<?php
$content = ob_get_clean();

require BASE_PATH . '/app/shared/views/admin_layout.php';
?>

