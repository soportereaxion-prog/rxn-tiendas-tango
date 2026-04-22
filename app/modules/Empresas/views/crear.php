<?php
$pageTitle = 'RXN Suite';
ob_start();
?>
<div class="container mt-5 mb-5 rxn-responsive-container rxn-form-shell">
        <div class="rxn-module-header mb-4">
            <div>
                <h2 class="mb-1">Alta de Empresa</h2>
                
            </div>
            <a href="/empresas" class="btn btn-outline-secondary btn-sm" title="Volver al listado de Empresas"><i class="bi bi-arrow-left"></i> Volver</a>
        </div>

        <?php
        $moduleNotesKey = 'empresas';
        $moduleNotesLabel = 'Empresas';
        require BASE_PATH . '/app/shared/views/components/module_notes_panel.php';
        ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card rxn-form-card">
            <div class="card-body p-4 p-lg-5">
                <form action="/empresas" method="POST">
                    <div class="rxn-form-section">
                        <div class="rxn-form-section-title">Identidad de la empresa</div>
                        <div class="rxn-form-section-text">Carga los datos base del tenant manteniendo una estructura clara tipo sabana.</div>

                        <div class="rxn-form-grid">
                            <div class="rxn-form-span-4">
                                <label for="codigo" class="form-label">Código (Obligatorio)</label>
                                <input type="text" class="form-control" id="codigo" name="codigo" required value="<?= htmlspecialchars($old['codigo'] ?? '') ?>">
                                <div class="form-text">Identificador único. ej: EMP-001</div>
                            </div>

                            <div class="rxn-form-span-8">
                                <label for="nombre" class="form-label">Nombre (Obligatorio)</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required value="<?= htmlspecialchars($old['nombre'] ?? '') ?>">
                            </div>
                            
                            <div class="rxn-form-span-12">
                                <label for="titulo_pestana" class="form-label">Título de la empresa (Pestaña)</label>
                                <input type="text" class="form-control" id="titulo_pestana" name="titulo_pestana" value="<?= htmlspecialchars($old['titulo_pestana'] ?? '') ?>" placeholder="Ej: Empresa Acme">
                                <div class="form-text">Modifica el título de la pestaña del navegador para el entorno de esta empresa.</div>
                            </div>
                        </div>
                    </div>

                    <div class="rxn-form-section">
                        <div class="rxn-form-section-title">Estado operativo</div>
                        <div class="rxn-form-switches">
                            <div class="rxn-form-switch-card">
                                <div class="form-check form-switch m-0">
                                    <?php 
                                    $activada = isset($old) ? isset($old['activa']) : true; 
                                    $modTiendas = isset($old) && isset($old['modulo_tiendas']);
                                    $modCrm = isset($old) && isset($old['modulo_crm']);
                                    $rxnLive = isset($old) && isset($old['modulo_rxn_live']);
                                    ?>
                                    <input class="form-check-input" type="checkbox" role="switch" id="activa" name="activa" <?= $activada ? 'checked' : '' ?> data-empresa-activa-toggle>
                                    <label class="form-check-label fw-semibold" for="activa">Empresa activa</label>
                                    <div class="form-text mb-0">Si está apagada, queda fuera del circuito operativo.</div>
                                </div>
                            </div>

                            <div class="rxn-form-switch-card">
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="modulo_tiendas" name="modulo_tiendas" <?= $modTiendas ? 'checked' : '' ?> <?= $activada ? '' : 'disabled' ?> data-empresa-dependiente="tiendas">
                                    <label class="form-check-label fw-semibold" for="modulo_tiendas">Tiendas</label>
                                    <div class="form-text mb-0">Habilita el circuito de tienda para esta empresa cuando el tenant esté activo.</div>
                                </div>
                                <div class="d-flex flex-column gap-2 mt-3 pt-3 border-top border-secondary border-opacity-25">
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="tiendas_modulo_notas" name="tiendas_modulo_notas" <?= (isset($old) && isset($old['tiendas_modulo_notas'])) ? 'checked' : '' ?> <?= $modTiendas ? '' : 'disabled' ?> data-empresa-subdependiente="tiendas">
                                        <label class="form-check-label" for="tiendas_modulo_notas">Módulo "Notas"</label>
                                    </div>
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="tiendas_modulo_rxn_live" name="tiendas_modulo_rxn_live" <?= (isset($old) && isset($old['tiendas_modulo_rxn_live'])) ? 'checked' : '' ?> <?= $modTiendas ? '' : 'disabled' ?> data-empresa-subdependiente="tiendas">
                                        <label class="form-check-label" for="tiendas_modulo_rxn_live">RXN Live</label>
                                    </div>
                                </div>
                            </div>

                            <div class="rxn-form-switch-card">
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="modulo_crm" name="modulo_crm" <?= $modCrm ? 'checked' : '' ?> <?= $activada ? '' : 'disabled' ?> data-empresa-dependiente="crm">
                                    <label class="form-check-label fw-semibold" for="modulo_crm">CRM</label>
                                    <div class="form-text mb-0">Reserva el tenant para futuras funciones de CRM una vez que la empresa esté activa.</div>
                                </div>
                                <div class="d-flex flex-column gap-2 mt-3 pt-3 border-top border-secondary border-opacity-25">
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="crm_modulo_notas" name="crm_modulo_notas" <?= (isset($old) && isset($old['crm_modulo_notas'])) ? 'checked' : '' ?> <?= $modCrm ? '' : 'disabled' ?> data-empresa-subdependiente="crm">
                                        <label class="form-check-label" for="crm_modulo_notas">Módulo "Notas"</label>
                                    </div>
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="crm_modulo_llamadas" name="crm_modulo_llamadas" <?= (isset($old) && isset($old['crm_modulo_llamadas'])) ? 'checked' : '' ?> <?= $modCrm ? '' : 'disabled' ?> data-empresa-subdependiente="crm">
                                        <label class="form-check-label" for="crm_modulo_llamadas">Llamadas CRM</label>
                                    </div>
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="crm_modulo_monitoreo" name="crm_modulo_monitoreo" <?= (isset($old) && isset($old['crm_modulo_monitoreo'])) ? 'checked' : '' ?> <?= $modCrm ? '' : 'disabled' ?> data-empresa-subdependiente="crm">
                                        <label class="form-check-label" for="crm_modulo_monitoreo">Monitoreo de Usuarios</label>
                                    </div>
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="crm_modulo_rxn_live" name="crm_modulo_rxn_live" <?= (isset($old) && isset($old['crm_modulo_rxn_live'])) ? 'checked' : '' ?> <?= $modCrm ? '' : 'disabled' ?> data-empresa-subdependiente="crm">
                                        <label class="form-check-label" for="crm_modulo_rxn_live">RXN Live</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rxn-form-actions">
                        <a href="/empresas" class="btn btn-light">Cancelar</a>
                        <button type="submit" class="btn btn-primary px-4">Guardar Empresa</button>
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
        (function () {
            var activa = document.querySelector('[data-empresa-activa-toggle]');
            var dependientes = Array.prototype.slice.call(document.querySelectorAll('[data-empresa-dependiente]'));
            var subdependientes = Array.prototype.slice.call(document.querySelectorAll('[data-empresa-subdependiente]'));

            if (!activa) return;

            function syncAll() {
                var empresaHabilitada = activa.checked;
                
                dependientes.forEach(function (checkMod) {
                    checkMod.disabled = !empresaHabilitada;
                    if (!empresaHabilitada) {
                        checkMod.checked = false;
                    }

                    var modName = checkMod.getAttribute('data-empresa-dependiente');
                    var subchecks = subdependientes.filter(function(sub) { 
                        return sub.getAttribute('data-empresa-subdependiente') === modName; 
                    });

                    var modHabilitado = empresaHabilitada && checkMod.checked;
                    subchecks.forEach(function(subcheck) {
                        subcheck.disabled = !modHabilitado;
                        if (!modHabilitado) {
                            subcheck.checked = false;
                        }
                    });
                });
            }

            activa.addEventListener('change', syncAll);
            dependientes.forEach(function (checkMod) {
                checkMod.addEventListener('change', syncAll);
            });
            syncAll();

            
                });
            });
        }());
    </script>
    <script src="/js/rxn-shortcuts.js"></script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
