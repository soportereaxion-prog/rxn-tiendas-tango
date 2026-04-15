<?php
$pageTitle = ($mode === 'edit' ? 'Editar' : 'Nueva') . ' Plantilla - Mail Masivos';
ob_start();

$flash = \App\Core\Flash::get();
$template = $template ?? [];
$reports = $reports ?? [];
$errors = $errors ?? [];

$reportId = (int) ($template['report_id'] ?? 0);
$nombre = (string) ($template['nombre'] ?? '');
$descripcion = (string) ($template['descripcion'] ?? '');
$asunto = (string) ($template['asunto'] ?? '');
$bodyHtml = (string) ($template['body_html'] ?? '');
?>
<link rel="stylesheet" href="/css/mail-masivos-template-editor.css">

<div class="container-fluid px-4 mt-4 mb-5">
    <div class="rxn-tpl-header mb-3">
        <div class="rxn-tpl-header-info">
            <h2 class="fw-bold mb-1"><i class="bi bi-file-earmark-text-fill"></i>
                <?= $mode === 'edit' ? 'Editar Plantilla' : 'Nueva Plantilla' ?>
            </h2>
            <p class="text-muted mb-0 small">
                Armá el HTML, insertá variables del reporte asociado y mirá el preview en vivo con un registro de muestra.
            </p>
        </div>
        <div class="rxn-tpl-header-actions">
            <a href="/mi-empresa/crm/mail-masivos/plantillas" class="btn btn-outline-secondary btn-sm">← Volver</a>
            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-refresh-preview">
                <i class="bi bi-arrow-clockwise"></i> Refrescar preview
            </button>
            <button type="submit" form="tpl-form" class="btn btn-primary btn-sm fw-bold">
                <i class="bi bi-save"></i> <?= $mode === 'edit' ? 'Guardar cambios' : 'Crear plantilla' ?>
            </button>
        </div>
    </div>

    <?php if ($flash): ?>
        <?php
            $flashClass = match ($flash['type'] ?? 'info') {
                'success' => 'alert-success',
                'error', 'danger' => 'alert-danger',
                'warning' => 'alert-warning',
                default => 'alert-info',
            };
        ?>
        <div class="alert <?= $flashClass ?> py-2 small"><?= htmlspecialchars($flash['message'] ?? '') ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger py-2 small">
            <strong>Revisá los siguientes errores:</strong>
            <ul class="mb-0 mt-1 ps-3">
                <?php foreach ($errors as $field => $msg): ?>
                    <li><?= htmlspecialchars($msg) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form id="tpl-form" method="post" action="<?= htmlspecialchars($formAction) ?>">
        <!-- Fila 1: metadatos -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Nombre *</label>
                        <input type="text" name="nombre" class="form-control form-control-sm<?= isset($errors['nombre']) ? ' is-invalid' : '' ?>"
                               value="<?= htmlspecialchars($nombre) ?>" required maxlength="180">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold">Descripción</label>
                        <input type="text" name="descripcion" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($descripcion) ?>"
                               placeholder="Opcional — para identificarla rápido en el listado">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Reporte asociado</label>
                        <select name="report_id" id="select-report" class="form-select form-select-sm<?= isset($errors['report_id']) ? ' is-invalid' : '' ?>">
                            <option value="">— Sin reporte —</option>
                            <?php foreach ($reports as $r): ?>
                                <option value="<?= (int) $r['id'] ?>" <?= $reportId === (int) $r['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($r['nombre']) ?> (<?= htmlspecialchars($r['root_entity']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fila 2: asunto con variables -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <label class="form-label small fw-semibold mb-1">
                    Asunto del mail *
                    <span class="text-muted fw-normal">· soporta variables <code>{{Entity.field}}</code></span>
                </label>
                <input type="text" name="asunto" id="input-asunto"
                       class="form-control form-control-sm<?= isset($errors['asunto']) ? ' is-invalid' : '' ?>"
                       value="<?= htmlspecialchars($asunto) ?>" required maxlength="255"
                       placeholder="Ej: Hola {{CrmClientes.nombre}}, tenemos novedades para vos">
            </div>
        </div>

        <!-- Fila 3: editor + preview side-by-side -->
        <div class="rxn-tpl-editor-grid">
            <!-- Panel izquierdo: variables + textarea HTML -->
            <div class="rxn-tpl-panel">
                <div class="rxn-tpl-panel-header">
                    <div class="rxn-tpl-panel-title">
                        <i class="bi bi-code-slash"></i> HTML
                    </div>
                    <div class="rxn-tpl-panel-hint" id="tpl-last-focus-hint">
                        <i class="bi bi-info-circle"></i> Click en una variable para insertarla donde tengas el cursor
                    </div>
                </div>

                <div class="rxn-tpl-vars" id="tpl-vars">
                    <?php if ($reportId <= 0): ?>
                        <div class="rxn-tpl-vars-empty">
                            <i class="bi bi-arrow-up"></i> Seleccioná un reporte arriba para ver sus variables disponibles.
                        </div>
                    <?php else: ?>
                        <div class="rxn-tpl-vars-empty">
                            <i class="bi bi-hourglass-split"></i> Cargando variables...
                        </div>
                    <?php endif; ?>
                </div>

                <div class="rxn-tpl-editor-wrap">
                    <textarea name="body_html" id="textarea-html"
                              class="rxn-tpl-textarea<?= isset($errors['body_html']) ? ' is-invalid' : '' ?>"
                              spellcheck="false"><?= htmlspecialchars($bodyHtml) ?></textarea>
                </div>
            </div>

            <!-- Panel derecho: preview -->
            <div class="rxn-tpl-panel">
                <div class="rxn-tpl-panel-header">
                    <div class="rxn-tpl-panel-title">
                        <i class="bi bi-eye-fill"></i> Preview en vivo
                    </div>
                    <div class="rxn-tpl-panel-hint" id="tpl-preview-status">
                        <span class="text-muted">esperando…</span>
                    </div>
                </div>

                <div class="rxn-tpl-preview-subject" id="preview-subject-wrap">
                    <span class="rxn-tpl-preview-label">Asunto:</span>
                    <span class="rxn-tpl-preview-subject-text" id="preview-subject">—</span>
                </div>

                <div class="rxn-tpl-preview-wrap">
                    <iframe id="preview-iframe" sandbox="" title="Preview HTML" loading="lazy"></iframe>
                </div>

                <div class="rxn-tpl-preview-footer" id="preview-footer">
                    <!-- missing tokens / notes se inyectan por JS -->
                </div>
            </div>
        </div>

        <!-- available_vars snapshot: se serializa al guardar -->
        <input type="hidden" name="available_vars_json" id="hidden-available-vars" value="<?= htmlspecialchars((string) ($template['available_vars_json'] ?? '')) ?>">
    </form>
</div>

<script>
window.MailTemplateEditor = {
    apiAvailableVars: '/mi-empresa/crm/mail-masivos/plantillas/available-vars/',
    apiPreviewRender: '/mi-empresa/crm/mail-masivos/plantillas/preview-render',
    initialReportId: <?= (int) ($reportId ?? 0) ?>,
};
</script>
<script src="/js/mail-masivos-template-editor.js" defer></script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
