<?php
$pageTitle = 'RXN Tiendas IA';
ob_start();
?>
<?php
    $dashboardPath = $dashboardPath ?? '/mi-empresa/dashboard';
    $helpPath = $helpPath ?? '/mi-empresa/ayuda?area=tiendas';
    $formPath = $formPath ?? '/mi-perfil?area=tiendas';
    ?>
    <div class="container mt-5 mb-5 rxn-responsive-container rxn-form-shell" style="max-width: 720px;">
        <div class="rxn-module-header mb-4">
            <h2 class="fw-bold m-0">Mi Perfil B2B</h2>
            <div class="rxn-module-actions">

                <a href="<?= htmlspecialchars($helpPath) ?>" class="btn btn-outline-info btn-sm" target="_blank" rel="noopener noreferrer"><i class="bi bi-question-circle"></i> Ayuda</a>
                <a href="<?= htmlspecialchars($dashboardPath) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
            </div>
        </div>

        <?php
        $moduleNotesKey = 'mi_perfil';
        $moduleNotesLabel = 'Mi Perfil';
        require BASE_PATH . '/app/shared/views/components/module_notes_panel.php';
        ?>

        <?php if (!empty($_GET['success'])): ?>
            <div class="alert alert-success py-2 small"><?= htmlspecialchars($_GET['success']) ?></div>
        <?php endif; ?>

        <div class="card rxn-form-card shadow-sm border-0">
            <div class="card-body p-4 p-lg-5">
            <form action="<?= htmlspecialchars($formPath) ?>" method="POST">
                <div class="rxn-form-section">
                    <div class="rxn-form-section-title">Preferencias visuales</div>
                    <div class="rxn-form-section-text">Ajustes personales del panel administrativo.</div>
                    <div class="rxn-form-grid">
                        <div class="rxn-form-span-6">
                            <label class="form-label fw-medium text-dark">Tema de la Interfaz</label>
                            <select name="preferencia_tema" class="form-select">
                                <option value="light" <?= ($usuario['preferencia_tema'] ?? '') === 'light' ? 'selected' : '' ?>>🌞 Claro (Predeterminado)</option>
                                <option value="dark" <?= ($usuario['preferencia_tema'] ?? '') === 'dark' ? 'selected' : '' ?>>🌙 Oscuro (Dark Mode)</option>
                            </select>
                        </div>

                        <div class="rxn-form-span-6">
                            <label class="form-label fw-medium text-dark">Tamaño de Tipografía</label>
                            <select name="preferencia_fuente" class="form-select">
                                <option value="sm" <?= ($usuario['preferencia_fuente'] ?? '') === 'sm' ? 'selected' : '' ?>>Compacto (sm)</option>
                                <option value="md" <?= ($usuario['preferencia_fuente'] ?? '') === 'md' ? 'selected' : '' ?>>Normal (md)</option>
                                <option value="lg" <?= ($usuario['preferencia_fuente'] ?? '') === 'lg' ? 'selected' : '' ?>>Grande (lg)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info py-2 small mb-4 mt-4">
                    Solo afecta tu experiencia dentro del panel administrativo. No impacta en la portada pública.
                </div>

                <div class="rxn-form-actions">
                    <a href="<?= htmlspecialchars($dashboardPath) ?>" class="btn btn-light">Cancelar</a>
                    <button type="submit" class="btn btn-primary fw-bold py-2 px-4">💾 Guardar Configuración</button>
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
