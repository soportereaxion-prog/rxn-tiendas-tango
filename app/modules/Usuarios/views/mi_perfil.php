<!DOCTYPE html>
<html lang="es" <?= \App\Core\Helpers\UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - rxnTiendasIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/rxnTiendasIA/public/css/rxn-theming.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5" style="max-width: 500px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold m-0">Mi Perfil B2B</h2>
            <a href="/rxnTiendasIA/public/mi-empresa/dashboard" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        </div>

        <?php if (!empty($_GET['success'])): ?>
            <div class="alert alert-success py-2 small"><?= htmlspecialchars($_GET['success']) ?></div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 p-4">
            <form action="/rxnTiendasIA/public/mi-perfil" method="POST">
                
                <h6 class="text-secondary fw-bold text-uppercase mb-3 pb-2 border-bottom">Preferencias Visuales</h6>
                
                <div class="mb-3">
                    <label class="form-label fw-medium text-dark">Tema de la Interfaz</label>
                    <select name="preferencia_tema" class="form-select">
                        <option value="light" <?= ($usuario['preferencia_tema'] ?? '') === 'light' ? 'selected' : '' ?>>🌞 Claro (Predeterminado)</option>
                        <option value="dark" <?= ($usuario['preferencia_tema'] ?? '') === 'dark' ? 'selected' : '' ?>>🌙 Oscuro (Dark Mode)</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-medium text-dark">Tamaño de Tipografía</label>
                    <select name="preferencia_fuente" class="form-select">
                        <option value="sm" <?= ($usuario['preferencia_fuente'] ?? '') === 'sm' ? 'selected' : '' ?>>Compacto (sm)</option>
                        <option value="md" <?= ($usuario['preferencia_fuente'] ?? '') === 'md' ? 'selected' : '' ?>>Normal (md)</option>
                        <option value="lg" <?= ($usuario['preferencia_fuente'] ?? '') === 'lg' ? 'selected' : '' ?>>Grande (lg)</option>
                    </select>
                </div>

                <div class="alert alert-info py-2 small mb-4">
                    Solo afecta tu experiencia dentro del panel administrativo. No impacta en la portada pública.
                </div>

                <button type="submit" class="btn btn-primary w-100 fw-bold py-2">💾 Guardar Configuración</button>
            </form>
        </div>
    </div>
</body>
</html>
