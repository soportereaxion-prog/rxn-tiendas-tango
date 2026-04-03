<?php
$pageTitle = 'RXN Tiendas IA';
ob_start();
?>
<div class="container mt-5 mb-5 rxn-responsive-container rxn-form-shell">
        <div class="rxn-module-header mb-4">
            <div>
                <h2 class="mb-1">Alta de Empresa</h2>
                
            </div>
            <a href="/empresas" class="btn btn-outline-secondary">Volver al listado</a>
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
                        </div>
                    </div>

                    <div class="rxn-form-section">
                        <div class="rxn-form-section-title">Estado operativo</div>
                        <div class="rxn-form-switches">
                            <div class="rxn-form-switch-card">
                                <div class="form-check form-switch m-0">
                                    <?php $activada = isset($old) ? isset($old['activa']) : true; ?>
                                    <input class="form-check-input" type="checkbox" role="switch" id="activa" name="activa" <?= $activada ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="activa">Empresa activa</label>
                                    <div class="form-text mb-0">Si está apagada, queda fuera del circuito operativo.</div>
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
<script src="/js/rxn-shortcuts.js"></script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
