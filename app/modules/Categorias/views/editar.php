<?php
$pageTitle = 'RXN Suite';
ob_start();
?>
<?php $basePath = $basePath ?? '/mi-empresa/categorias'; ?>
    <div class="container mt-5 mb-5 rxn-responsive-container rxn-form-shell">
        <div class="rxn-module-header mb-4">
            <div>
                <h2 class="fw-bold mb-1">Editar Categoria</h2>
                
            </div>
            <div class="d-flex gap-2">
                <a href="<?= htmlspecialchars($basePath) ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Volver a Categorias
                </a>
                <form action="<?= htmlspecialchars($basePath) ?>/<?= htmlspecialchars((string) $categoria->id) ?>/copiar" method="POST" class="d-inline">
                    <button type="submit" class="btn btn-outline-success" title="Duplicar">
                        <i class="bi bi-copy"></i>
                    </button>
                </form>
                <form action="<?= htmlspecialchars($basePath) ?>/eliminar-masivo" method="POST" class="d-inline" onsubmit="return confirm('¿Enviar categoria a la papelera?');">
                    <input type="hidden" name="ids[]" value="<?= htmlspecialchars((string) $categoria->id) ?>">
                    <button type="submit" class="btn btn-outline-danger" title="Enviar a papelera">
                        <i class="bi bi-trash"></i>
                    </button>
                </form>
            </div>
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
                <form action="<?= htmlspecialchars($basePath) ?>/<?= htmlspecialchars((string) $categoria->id) ?>" method="POST" enctype="multipart/form-data">
                    <?php require __DIR__ . '/form_fields.php'; ?>

                    <div class="rxn-form-actions">
                        <a href="<?= htmlspecialchars($basePath) ?>" class="btn btn-light border">Cancelar</a>
                        <button type="submit" class="btn btn-primary px-4 fw-bold">Guardar Cambios</button>
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
