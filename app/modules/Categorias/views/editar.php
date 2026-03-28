<!DOCTYPE html>
<html lang="es" <?= \App\Core\Helpers\UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Categoria - rxnTiendasIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/rxnTiendasIA/public/css/rxn-theming.css" rel="stylesheet">
</head>
<body class="rxn-page-shell">
    <?php $basePath = $basePath ?? '/rxnTiendasIA/public/mi-empresa/categorias'; ?>
    <div class="container mt-5 mb-5 rxn-responsive-container rxn-form-shell">
        <div class="rxn-module-header mb-4">
            <div>
                <h2 class="fw-bold mb-1">Editar Categoria</h2>
                <p class="text-muted mb-0">Ajusta la visibilidad publica y la portada usada en la tienda.</p>
            </div>
            <a href="<?= htmlspecialchars($basePath) ?>" class="btn btn-outline-secondary">Volver al listado</a>
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
</body>
</html>
