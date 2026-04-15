<?php
$pageTitle = ($mode === 'edit' ? 'Editar Reporte' : 'Nuevo Reporte') . ' - rxn_suite';
ob_start();
?>
<link rel="stylesheet" href="/css/mail-masivos-designer.css">

<div class="container-fluid mt-4 mb-5 rxn-responsive-container">
    <div class="rxn-module-header mb-3">
        <div>
            <h2 class="fw-bold mb-1">
                <i class="bi bi-diagram-3"></i>
                <?= $mode === 'edit' ? 'Editar Reporte' : 'Nuevo Reporte' ?>
            </h2>
            <p class="text-muted mb-0 small">
                Arrastrá entidades al canvas, conectálas por sus relaciones y marcá los campos que querés usar como variables.
                El destinatario del mail se define con el ícono del sobre <i class="bi bi-envelope"></i>.
            </p>
        </div>
        <div class="rxn-module-actions d-flex flex-wrap gap-2">
            <a href="/mi-empresa/crm/mail-masivos/reportes" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <button type="button" id="mm-preview-btn" class="btn btn-outline-info btn-sm">
                <i class="bi bi-eye"></i> Previsualizar
            </button>
            <button type="button" id="mm-save-btn" class="btn btn-primary btn-sm fw-bold">
                <i class="bi bi-save"></i> Guardar Reporte
            </button>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong>Hay errores en el diseño:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach ($errors as $field => $msg): ?>
                    <li><strong><?= htmlspecialchars($field) ?>:</strong> <?= htmlspecialchars($msg) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialchars($formAction) ?>">

        <!-- Metadata del reporte -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-3">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="nombre" class="form-label small fw-semibold">Nombre</label>
                        <input type="text" class="form-control form-control-sm" id="nombre" name="nombre" required
                               value="<?= htmlspecialchars($report['nombre'] ?? '') ?>"
                               placeholder="Ej. Clientes con presupuestos pendientes">
                    </div>
                    <div class="col-md-5">
                        <label for="descripcion" class="form-label small fw-semibold">Descripción <span class="text-muted fw-normal">(opcional)</span></label>
                        <input type="text" class="form-control form-control-sm" id="descripcion" name="descripcion"
                               value="<?= htmlspecialchars($report['descripcion'] ?? '') ?>"
                               placeholder="Breve descripción del propósito">
                    </div>
                    <div class="col-md-3">
                        <label for="mm-root-entity" class="form-label small fw-semibold">Entidad raíz</label>
                        <select class="form-select form-select-sm" id="mm-root-entity">
                            <!-- Poblado por JS -->
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Diseñador visual -->
        <div id="mm-designer"
             data-metamodel-url="/mi-empresa/crm/mail-masivos/reportes/metamodel"
             data-preview-url="/mi-empresa/crm/mail-masivos/reportes/preview"
             data-initial='<?= htmlspecialchars($report['config_json'] ?? '', ENT_QUOTES) ?>'>

            <div class="mm-designer-shell">

                <!-- Sidebar -->
                <aside class="mm-designer-sidebar">
                    <div class="mm-sidebar-section">
                        <h6><i class="bi bi-collection"></i> Agregar entidad</h6>
                        <p class="small text-muted mb-2">
                            Click en una entidad para ponerla en el canvas.
                            Las prendidas aparecen con opacidad reducida.
                        </p>
                        <div id="mm-sidebar-entities">
                            <!-- Poblado por JS -->
                        </div>
                    </div>

                    <div class="mm-sidebar-section">
                        <h6><i class="bi bi-info-circle"></i> Tips</h6>
                        <ul class="small text-muted ps-3 mb-0">
                            <li>Los nodos se arrastran desde su header.</li>
                            <li>Click en los <em>chips</em> de relación del footer del nodo para conectar entidades.</li>
                            <li>El ícono del sobre marca qué campo se usa como destinatario del mail.</li>
                            <li>Los filtros se aplican a cualquier entidad del diseño.</li>
                        </ul>
                    </div>
                </aside>

                <!-- Main: canvas + filtros -->
                <div class="mm-designer-main">

                    <!-- Canvas -->
                    <div class="mm-canvas-wrap">
                        <div id="mm-canvas" class="mm-canvas">
                            <svg id="mm-canvas-svg" class="mm-canvas-svg"></svg>
                            <div id="mm-canvas-empty" class="mm-canvas-empty">
                                <i class="bi bi-diagram-3"></i>
                                <div>Elegí una entidad raíz para comenzar</div>
                            </div>
                        </div>
                    </div>

                    <!-- Filtros -->
                    <div class="mm-filters-panel">
                        <h6>
                            <i class="bi bi-funnel"></i> Filtros
                            <button type="button" id="mm-filter-add" class="btn btn-sm btn-outline-primary ms-auto">
                                <i class="bi bi-plus-lg"></i> Agregar filtro
                            </button>
                        </h6>
                        <div id="mm-filters-list"></div>
                    </div>

                </div>
            </div>

            <!-- Panel JSON (modo experto, colapsable) -->
            <div class="mt-3">
                <button type="button" id="mm-json-toggle" class="btn btn-sm btn-link text-muted p-0">
                    <i class="bi bi-code-slash"></i> Ver/ocultar JSON del diseño
                </button>
                <div id="mm-json-panel" class="mm-json-panel mt-2" style="display:none;">
                    <pre></pre>
                </div>
            </div>

            <!-- Inputs hidden para el submit -->
            <input type="hidden" id="config_json" name="config_json" value="<?= htmlspecialchars($report['config_json'] ?? '') ?>">
            <input type="hidden" id="root_entity" name="root_entity" value="<?= htmlspecialchars($report['root_entity'] ?? '') ?>">
        </div>

        <!-- Panel de Preview (aparece al hacer click en el botón de arriba) -->
        <div id="mm-preview-panel" class="card border-0 shadow-sm mt-3" style="display: none;">
            <div class="card-body p-3">
                <h5 class="fw-bold mb-3"><i class="bi bi-eye"></i> Preview del diseño</h5>
                <div id="mm-preview-content"></div>
            </div>
        </div>

    </form>
</div>

<?php
$content = ob_get_clean();
ob_start();
?>
<script src="/js/mail-masivos-designer.js" defer></script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
