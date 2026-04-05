<?php
$pageTitle = 'RXN Suite';
ob_start();
?>
<div class="container mt-5 mb-5 rxn-responsive-container rxn-form-shell">
        <?php
        $activaActual = isset($old) ? isset($old['activa']) : (bool) $empresa->activa;
        $tiendasActual = $activaActual && (isset($old) ? isset($old['modulo_tiendas']) : (bool) ($empresa->modulo_tiendas ?? 0));
        $tiendasNotasActual = $tiendasActual && (isset($old) ? isset($old['tiendas_modulo_notas']) : (bool) ($empresa->tiendas_modulo_notas ?? 0));
        $crmActual = $activaActual && (isset($old) ? isset($old['modulo_crm']) : (bool) ($empresa->modulo_crm ?? 0));
        $crmNotasActual = $crmActual && (isset($old) ? isset($old['crm_modulo_notas']) : (bool) ($empresa->crm_modulo_notas ?? 0));
        $rxnLiveActual = $activaActual && (isset($old) ? isset($old['modulo_rxn_live']) : (bool) ($empresa->modulo_rxn_live ?? 0));
        $crmLlamadasActual = $crmActual && (isset($old) ? isset($old['crm_modulo_llamadas']) : (bool) ($empresa->crm_modulo_llamadas ?? 0));
        $crmMonitoreoActual = $crmActual && (isset($old) ? isset($old['crm_modulo_monitoreo']) : (bool) ($empresa->crm_modulo_monitoreo ?? 0));
        ?>
        <div class="rxn-module-header mb-4">
            <div>
                <h2 class="mb-1">Editar Empresa</h2>
                
            </div>
            <div class="d-flex gap-2">
                <a href="/empresas" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Volver a Empresas
                </a>
                <form action="/empresas/<?= $empresa->id ?>/copiar" method="POST" class="d-inline">
                    <button type="submit" class="btn btn-outline-success" title="Duplicar Empresa">
                        <i class="bi bi-copy"></i>
                    </button>
                </form>
                <form action="/empresas/<?= $empresa->id ?>/eliminar" method="POST" class="d-inline" onsubmit="return confirm('¿Enviar empresa a la papelera?');">
                    <button type="submit" class="btn btn-outline-danger" title="Enviar a papelera">
                        <i class="bi bi-trash"></i>
                    </button>
                </form>
            </div>
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
                <form action="/empresas/<?= htmlspecialchars((string)$empresa->id) ?>" method="POST">
                    <div class="rxn-form-section">
                        <div class="rxn-form-section-title">Identidad de la empresa</div>
                        <div class="rxn-form-grid">
                            <div class="rxn-form-span-4">
                                <label for="codigo" class="form-label">Código (Obligatorio)</label>
                                <input type="text" class="form-control" id="codigo" name="codigo" required value="<?= htmlspecialchars($old['codigo'] ?? $empresa->codigo) ?>">
                            </div>

                            <div class="rxn-form-span-8">
                                <label for="nombre" class="form-label">Nombre (Obligatorio)</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required value="<?= htmlspecialchars($old['nombre'] ?? $empresa->nombre) ?>">
                            </div>
                            
                            <div class="rxn-form-span-12 mt-3">
                                <label for="titulo_pestana" class="form-label">Título de la empresa (Pestaña)</label>
                                <input type="text" class="form-control" id="titulo_pestana" name="titulo_pestana" value="<?= htmlspecialchars((string)($old['titulo_pestana'] ?? $empresa->titulo_pestana)) ?>" placeholder="Ej: Empresa Acme">
                                <div class="form-text">Modifica el título de la pestaña del navegador para el entorno de esta empresa.</div>
                            </div>
                            <!-- Variables de estado latente ocultas -->
                            <input type="hidden" name="razon_social" value="<?= htmlspecialchars($old['razon_social'] ?? (string)$empresa->razon_social) ?>">
                            <input type="hidden" name="cuit" value="<?= htmlspecialchars($old['cuit'] ?? (string)$empresa->cuit) ?>">
                        </div>
                    </div>

                    <div class="rxn-form-section">
                        <div class="rxn-form-section-title">Estado operativo</div>
                        <div class="rxn-form-switches">
                            <div class="rxn-form-switch-card">
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="activa" name="activa" <?= $activaActual ? 'checked' : '' ?> data-empresa-activa-toggle>
                                    <label class="form-check-label fw-semibold" for="activa">Empresa activa</label>
                                    <div class="form-text mb-0">Define si el tenant sigue disponible para operar.</div>
                                </div>
                            </div>


                            <div class="rxn-form-switch-card">
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="modulo_tiendas" name="modulo_tiendas" <?= $tiendasActual ? 'checked' : '' ?> <?= $activaActual ? '' : 'disabled' ?> data-empresa-dependiente="tiendas">
                                    <label class="form-check-label fw-semibold" for="modulo_tiendas">Tiendas</label>
                                    <div class="form-text mb-0">Habilita el circuito de tienda para esta empresa cuando el tenant esté activo.</div>
                                </div>
                                <div class="d-flex flex-column gap-2 mt-3 pt-3 border-top border-secondary border-opacity-25">
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="tiendas_modulo_notas" name="tiendas_modulo_notas" <?= $tiendasNotasActual ? 'checked' : '' ?> <?= $tiendasActual ? '' : 'disabled' ?> data-empresa-subdependiente="tiendas">
                                        <label class="form-check-label" for="tiendas_modulo_notas">Módulo "Notas"</label>
                                    </div>
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="tiendas_modulo_rxn_live" name="tiendas_modulo_rxn_live" <?= $empresa->tiendas_modulo_rxn_live === 1 ? 'checked' : '' ?> <?= $tiendasActual ? '' : 'disabled' ?> data-empresa-subdependiente="tiendas">
                                        <label class="form-check-label" for="tiendas_modulo_rxn_live">RXN Live</label>
                                    </div>
                                </div>
                            </div>

                            <div class="rxn-form-switch-card">
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="modulo_crm" name="modulo_crm" <?= $crmActual ? 'checked' : '' ?> <?= $activaActual ? '' : 'disabled' ?> data-empresa-dependiente="crm">
                                    <label class="form-check-label fw-semibold" for="modulo_crm">CRM</label>
                                    <div class="form-text mb-0">Reserva el tenant para futuras funciones de CRM una vez que la empresa esté activa.</div>
                                </div>
                                <div class="d-flex flex-column gap-2 mt-3 pt-3 border-top border-secondary border-opacity-25">
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="crm_modulo_notas" name="crm_modulo_notas" <?= $crmNotasActual ? 'checked' : '' ?> <?= $crmActual ? '' : 'disabled' ?> data-empresa-subdependiente="crm">
                                        <label class="form-check-label" for="crm_modulo_notas">Módulo "Notas"</label>
                                    </div>
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="crm_modulo_llamadas" name="crm_modulo_llamadas" <?= $crmLlamadasActual ? 'checked' : '' ?> <?= $crmActual ? '' : 'disabled' ?> data-empresa-subdependiente="crm">
                                        <label class="form-check-label" for="crm_modulo_llamadas">Llamadas CRM</label>
                                    </div>
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="crm_modulo_monitoreo" name="crm_modulo_monitoreo" <?= $crmMonitoreoActual ? 'checked' : '' ?> <?= $crmActual ? '' : 'disabled' ?> data-empresa-subdependiente="crm">
                                        <label class="form-check-label" for="crm_modulo_monitoreo">Monitoreo de Usuarios</label>
                                    </div>
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="crm_modulo_rxn_live" name="crm_modulo_rxn_live" <?= $empresa->crm_modulo_rxn_live === 1 ? 'checked' : '' ?> <?= $crmActual ? '' : 'disabled' ?> data-empresa-subdependiente="crm">
                                        <label class="form-check-label" for="crm_modulo_rxn_live">RXN Live</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rxn-form-actions">
                        <a href="/empresas" class="btn btn-light">Cancelar</a>
                        <button type="submit" class="btn btn-primary px-4">Actualizar Empresa</button>
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

            var rxnLiveToggles = document.querySelectorAll('input[name="modulo_rxn_live"]');
            rxnLiveToggles.forEach(function(toggle) {
                toggle.addEventListener('change', function() {
                    var isChecked = this.checked;
                    rxnLiveToggles.forEach(function(other) {
                        if (!other.disabled) {
                            other.checked = isChecked;
                        }
                    });
                });
            });
        }());
    </script>
    <script src="/js/rxn-shortcuts.js"></script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
