<?php
$pageTitle = 'RXN Tiendas IA';
ob_start();
?>
<?php
    $area = $area ?? 'tiendas';
    $basePath = $basePath ?? '/mi-empresa/usuarios';
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

                            <div class="rxn-form-span-6">
                                <label for="anura_interno" class="form-label">Interno Anura (Softphone)</label>
                                <input type="text" class="form-control" id="anura_interno" name="anura_interno" value="<?= htmlspecialchars($old['anura_interno'] ?? ($usuario->anura_interno ?? '')) ?>" placeholder="Ej: 101">
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
                                <input type="hidden" id="tango_perfil_snapshot_json" name="tango_perfil_snapshot_json" value="<?= htmlspecialchars($usuario->tango_perfil_snapshot_json ?? '') ?>">

                                <!-- Panel de IDs resueltos del perfil -->
                                <div id="tango_profile_ids_panel" class="mt-2 p-2 rounded border" style="font-size:0.82rem; display:none; background:var(--bs-body-bg,#1e1e2e);">
                                    <div class="d-flex flex-wrap gap-3">
                                        <span>🗂 <strong>ID_GVA43</strong> <code id="lbl_gva43">–</code></span>
                                        <span>🏭 <strong>ID_STA22</strong> <code id="lbl_sta22">–</code></span>
                                        <span>💳 <strong>ID_GVA23</strong> <code id="lbl_gva23">–</code></span>
                                    </div>
                                    <div id="lbl_perfil_fetch_status" class="text-muted mt-1" style="font-size:0.75rem;"></div>
                                </div>
                                <div class="form-text">Si se asocia, las cabeceras de pedidos/presupuestos de esta empresa se generarán con los vendedores y talonarios del Perfil seleccionado.</div>
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

                            <?php if (isset($isGlobalAdmin) && $isGlobalAdmin): ?>
                            <div class="rxn-form-switch-card border-danger border-opacity-50" style="background-color: rgba(220,53,69,0.03);">
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input bg-danger border-danger" type="checkbox" role="switch" id="es_rxn_admin" name="es_rxn_admin" <?= (isset($old['es_rxn_admin']) ? ($old['es_rxn_admin']==='on') : ($usuario->es_rxn_admin == 1)) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold text-danger" for="es_rxn_admin">Privilegios Super Administrador (RXN)</label>
                                    <div class="form-text mb-0 text-danger opacity-75">Otorga el control global de sistema sobre todos los Módulos de Mantenimiento y Tenants.</div>
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
    
<?php
$content = ob_get_clean();
ob_start();
?>
<script>
        document.addEventListener('DOMContentLoaded', () => {
            const elPerfil   = document.getElementById('tango_perfil_pedido');
            const snapInput  = document.getElementById('tango_perfil_snapshot_json');
            const panel      = document.getElementById('tango_profile_ids_panel');
            const lblGva43   = document.getElementById('lbl_gva43');
            const lblSta22   = document.getElementById('lbl_sta22');
            const lblGva23   = document.getElementById('lbl_gva23');
            const lblStatus  = document.getElementById('lbl_perfil_fetch_status');

            // Case-insensitive multi-key finder
            function findProp(obj, keys) {
                const lower = {};
                for (const k in obj) lower[k.toLowerCase()] = obj[k];
                for (const k of keys) {
                    const v = lower[k.toLowerCase()];
                    if (v !== undefined && v !== null && v !== '') return v;
                }
                return null;
            }

            function renderFromSnap(snap) {
                if (!snap || typeof snap !== 'object') return false;
                // Prioridad: buscar en raw (respuesta directa API Tango) con keys exactas confirmadas
                const raw = snap.raw || {};
                const gva43 = findProp(raw,  ['ID_GVA43_TALONARIO_PEDIDO'])
                           || findProp(snap, ['id_gva43_talonario_pedido']);
                const sta22 = findProp(raw,  ['ID_STA22'])
                           || findProp(snap, ['id_sta22']);
                const gva23 = findProp(raw,  ['ID_GVA23_ENCABEZADO'])
                           || findProp(snap, ['id_gva23_encabezado']);

                lblGva43.textContent = gva43 ?? '–';
                lblSta22.textContent = sta22 ?? '–';
                lblGva23.textContent = gva23 ?? '–';
                panel.style.display = '';
                return !!(gva43 || sta22 || gva23);
            }

            // On page load: try to render from stored snapshot
            let populated = false;
            if (snapInput && snapInput.value) {
                try {
                    populated = renderFromSnap(JSON.parse(snapInput.value));
                    if (populated) lblStatus.textContent = '✓ Datos del perfil guardado';
                } catch(e) {}
            }

            // Si hay perfil seleccionado pero sin datos, ir a buscar
            if (!populated && elPerfil && elPerfil.value) {
                fetchProfile(elPerfil.value);
            }

            // On selector change: fetch fresh from API
            if (elPerfil) {
                elPerfil.addEventListener('change', () => {
                    if (!elPerfil.value) {
                        panel.style.display = 'none';
                        snapInput.value = '';
                        return;
                    }
                    fetchProfile(elPerfil.value);
                });
            }

            function fetchProfile(perfilValue) {
                lblStatus.textContent = '⏳ Consultando perfil en Tango...';
                panel.style.display = '';
                lblGva43.textContent = '...';
                lblSta22.textContent = '...';
                lblGva23.textContent = '...';

                fetch('/mi-empresa/usuarios/fetch-tango-profile', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({tango_perfil_pedido: perfilValue})
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.data) {
                        snapInput.value = JSON.stringify(res.data);
                        const ok = renderFromSnap(res.data);
                        if (ok) {
                            lblStatus.textContent = '✓ Datos actualizados desde Tango';
                        } else {
                            // Mostrar las raw_keys para diagnóstico
                            const rawKeys = res.data.raw_keys || Object.keys(res.data.raw || {});
                            lblStatus.textContent = '⚠ No se encontraron ID_GVA43/ID_STA22/ID_GVA23. Keys reales: ' + rawKeys.join(', ');
                        }
                    } else {
                        lblStatus.textContent = '✗ ' + (res.message || 'Error al consultar Tango');
                        lblGva43.textContent = '–';
                        lblSta22.textContent = '–';
                        lblGva23.textContent = '–';
                    }
                })
                .catch(() => {
                    lblStatus.textContent = '✗ Error de red al consultar Tango';
                });
            }
        });
    </script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
