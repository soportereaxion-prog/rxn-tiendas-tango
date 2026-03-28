<!DOCTYPE html>
<html lang="es" <?= \App\Core\Helpers\UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Empresa - rxnTiendasIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/rxnTiendasIA/public/css/rxn-theming.css" rel="stylesheet">
</head>
<body class="rxn-page-shell">
    <div class="container mt-5 mb-5 rxn-responsive-container rxn-form-shell">
        <?php
        $activaActual = isset($old) ? isset($old['activa']) : (bool) $empresa->activa;
        $tiendasActual = $activaActual && (isset($old) ? isset($old['modulo_tiendas']) : (bool) ($empresa->modulo_tiendas ?? 0));
        $crmActual = $activaActual && (isset($old) ? isset($old['modulo_crm']) : (bool) ($empresa->modulo_crm ?? 0));
        ?>
        <div class="rxn-module-header mb-4">
            <div>
                <h2 class="mb-1">Editar Empresa</h2>
                <p class="text-muted mb-0">Modificar el registro de <?= htmlspecialchars($empresa->nombre) ?>.</p>
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
                <form action="/rxnTiendasIA/public/empresas/<?= htmlspecialchars((string)$empresa->id) ?>" method="POST">
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

                            <div class="rxn-form-span-6">
                                <label for="razon_social" class="form-label">Razón Social</label>
                                <input type="hidden" name="razon_social" value="<?= htmlspecialchars($old['razon_social'] ?? (string)$empresa->razon_social) ?>">
                                <input type="text" class="form-control" id="razon_social" value="<?= htmlspecialchars($old['razon_social'] ?? (string)$empresa->razon_social) ?>" disabled>
                                <div class="form-text">Reservado para una etapa futura del circuito administrativo.</div>
                            </div>

                            <div class="rxn-form-span-6">
                                <label for="cuit" class="form-label">CUIT</label>
                                <input type="hidden" name="cuit" value="<?= htmlspecialchars($old['cuit'] ?? (string)$empresa->cuit) ?>">
                                <input type="text" class="form-control" id="cuit" value="<?= htmlspecialchars($old['cuit'] ?? (string)$empresa->cuit) ?>" disabled>
                                <div class="form-text">Reservado para una etapa futura del circuito administrativo.</div>
                            </div>
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
                                    <input class="form-check-input" type="checkbox" role="switch" id="modulo_tiendas" name="modulo_tiendas" <?= $tiendasActual ? 'checked' : '' ?> <?= $activaActual ? '' : 'disabled' ?> data-empresa-dependiente>
                                    <label class="form-check-label fw-semibold" for="modulo_tiendas">Tiendas</label>
                                    <div class="form-text mb-0">Habilita el circuito de tienda para esta empresa cuando el tenant esté activo.</div>
                                </div>
                            </div>

                            <div class="rxn-form-switch-card">
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="modulo_crm" name="modulo_crm" <?= $crmActual ? 'checked' : '' ?> <?= $activaActual ? '' : 'disabled' ?> data-empresa-dependiente>
                                    <label class="form-check-label fw-semibold" for="modulo_crm">CRM</label>
                                    <div class="form-text mb-0">Reserva el tenant para futuras funciones de CRM una vez que la empresa esté activa.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rxn-form-actions">
                        <a href="/rxnTiendasIA/public/empresas" class="btn btn-light">Cancelar</a>
                        <button type="submit" class="btn btn-primary px-4">Actualizar Empresa</button>
                    </div>

                </form>
            </div>
        </div>
    </div>
    <script>
        (function () {
            var activa = document.querySelector('[data-empresa-activa-toggle]');
            var dependientes = Array.prototype.slice.call(document.querySelectorAll('[data-empresa-dependiente]'));

            if (!activa || !dependientes.length) {
                return;
            }

            function syncDependientes() {
                var habilitada = activa.checked;
                dependientes.forEach(function (checkbox) {
                    checkbox.disabled = !habilitada;
                    if (!habilitada) {
                        checkbox.checked = false;
                    }
                });
            }

            activa.addEventListener('change', syncDependientes);
            syncDependientes();
        }());
    </script>
</body>
</html>
