<!DOCTYPE html>
<html lang="es" <?= \App\Core\Helpers\UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Empresa - rxnTiendasIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/rxnTiendasIA/public/css/rxn-theming.css" rel="stylesheet">
</head>
<body class="rxn-page-shell">
    <div class="container mt-5 mb-5 rxn-responsive-container rxn-form-shell">
        <div class="rxn-module-header mb-4">
            <div>
                <h2 class="mb-1">Alta de Empresa</h2>
                <p class="text-muted mb-0">Crear un nuevo registro empresarial.</p>
            </div>
            <a href="/rxnTiendasIA/public/empresas" class="btn btn-outline-secondary">Volver al listado</a>
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
                <form action="/rxnTiendasIA/public/empresas" method="POST">
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

                            <div class="rxn-form-span-6">
                                <label for="razon_social" class="form-label">Razón Social</label>
                                <input type="text" class="form-control" id="razon_social" name="razon_social" value="<?= htmlspecialchars($old['razon_social'] ?? '') ?>">
                            </div>

                            <div class="rxn-form-span-6">
                                <label for="cuit" class="form-label">CUIT</label>
                                <input type="text" class="form-control" id="cuit" name="cuit" value="<?= htmlspecialchars($old['cuit'] ?? '') ?>">
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
                        <a href="/rxnTiendasIA/public/empresas" class="btn btn-light">Cancelar</a>
                        <button type="submit" class="btn btn-primary px-4">Guardar Empresa</button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</body>
</html>
