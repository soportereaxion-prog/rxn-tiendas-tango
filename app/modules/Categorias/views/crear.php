<?php
$pageTitle = 'RXN Suite';
ob_start();
?>
<?php $basePath = $basePath ?? '/mi-empresa/categorias'; ?>
    <div class="container-fluid mt-2 mb-5 rxn-responsive-container rxn-form-shell">
        <div class="rxn-module-header mb-4">
            <div>
                <h2 class="fw-bold mb-1">Nueva Categoria</h2>
                
            </div>
            <a href="<?= htmlspecialchars($basePath) ?>" class="btn btn-outline-secondary btn-sm" title="Volver al listado de Categorías"><i class="bi bi-arrow-left"></i> Volver</a>
        </div>

        <?php
        $moduleNotesKey = 'categorias';
        $moduleNotesLabel = 'Categorias';
        require BASE_PATH . '/app/shared/views/components/module_notes_panel.php';
        ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars((string) $error) ?></div>
        <?php endif; ?>

        <div class="card rxn-form-card border-0 shadow-sm">
            <div class="card-body p-4 p-lg-5">
                <form action="<?= htmlspecialchars($basePath) ?>" method="POST" enctype="multipart/form-data">
                    <?php require __DIR__ . '/form_fields.php'; ?>

                    <div class="rxn-form-actions">
                        <a href="<?= htmlspecialchars($basePath) ?>" class="btn btn-light border">Cancelar</a>
                        <button type="submit" class="btn btn-primary px-4 fw-bold">Crear Categoria</button>
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
