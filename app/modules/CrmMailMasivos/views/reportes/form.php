<?php
$pageTitle = ($mode === 'edit' ? 'Editar Reporte' : 'Nuevo Reporte') . ' - rxn_suite';
ob_start();
?>
<div class="container-fluid mt-2 mb-5 rxn-responsive-container rxn-form-shell">
    <div class="rxn-module-header mb-4">
        <div>
            <h2 class="fw-bold mb-1">
                <i class="bi bi-diagram-3"></i>
                <?= $mode === 'edit' ? 'Editar Reporte' : 'Nuevo Reporte' ?>
            </h2>
            <p class="text-muted mb-0">
                <strong>Fase 2a — form temporal.</strong>
                El diseñador visual "Links" llega en Fase 2b. Por ahora editás el JSON crudo del diseño y probás el preview.
            </p>
        </div>
        <a href="/mi-empresa/crm/mail-masivos/reportes" class="btn btn-outline-secondary btn-sm" title="Volver al listado de Reportes"><i class="bi bi-arrow-left"></i> Volver</a>
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
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card rxn-form-card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="rxn-form-section">
                            <div class="rxn-form-section-title">Identificación</div>

                            <div class="mb-3">
                                <label for="nombre" class="form-label">Nombre</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required
                                       value="<?= htmlspecialchars($report['nombre'] ?? '') ?>"
                                       placeholder="Ej. Clientes con presupuestos pendientes">
                            </div>

                            <div class="mb-3">
                                <label for="descripcion" class="form-label">Descripción <small class="text-muted">(opcional)</small></label>
                                <textarea class="form-control" id="descripcion" name="descripcion" rows="2"><?= htmlspecialchars($report['descripcion'] ?? '') ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="root_entity" class="form-label">Entidad raíz</label>
                                <select class="form-select" id="root_entity" name="root_entity" required>
                                    <option value="">— Elegí una —</option>
                                    <?php foreach ($entities as $key => $def): ?>
                                        <option value="<?= htmlspecialchars($key) ?>" <?= ($report['root_entity'] ?? '') === $key ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($def['label'] ?? $key) ?>
                                            <?= !empty($def['mail_field']) ? ' — mail: ' . htmlspecialchars($def['mail_field']) : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Define la tabla "base" del diseño. Los destinatarios salen de acá (o de una relación prendida).</div>
                            </div>
                        </div>

                        <div class="rxn-form-section mt-4">
                            <div class="rxn-form-section-title">Diseño (JSON)</div>
                            <div class="rxn-form-section-text">
                                Este JSON es lo que el diseñador visual va a producir en Fase 2b. Por ahora lo editás a mano.
                                Usá el panel de la derecha como referencia para los nombres de entidades, campos y relaciones.
                            </div>
                            <textarea class="form-control font-monospace" id="config_json" name="config_json" rows="22" style="font-size: 0.82rem; background-color: #0f1116; color: #d0d0d0;"><?= htmlspecialchars($report['config_json'] ?? '') ?></textarea>
                        </div>

                        <div class="rxn-form-actions mt-4">
                            <a href="/mi-empresa/crm/mail-masivos/reportes" class="btn btn-light">Cancelar</a>
                            <button type="button" id="btn-preview" class="btn btn-outline-info">
                                <i class="bi bi-eye"></i> Previsualizar
                            </button>
                            <button type="submit" class="btn btn-primary fw-bold px-4">
                                <i class="bi bi-save"></i> Guardar Reporte
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body p-3">
                        <h6 class="fw-bold mb-3"><i class="bi bi-book"></i> Referencia del metamodelo</h6>
                        <p class="small text-muted mb-3">
                            Entidades y relaciones disponibles. El diseñador visual (Fase 2b) va a usar exactamente esto.
                        </p>
                        <div class="accordion" id="metamodelAcc">
                            <?php foreach ($entities as $key => $def): ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed py-2 small" type="button" data-bs-toggle="collapse" data-bs-target="#ent-<?= htmlspecialchars($key) ?>">
                                            <strong><?= htmlspecialchars($key) ?></strong>
                                            <span class="text-muted ms-2">— <?= htmlspecialchars($def['label']) ?></span>
                                        </button>
                                    </h2>
                                    <div id="ent-<?= htmlspecialchars($key) ?>" class="accordion-collapse collapse" data-bs-parent="#metamodelAcc">
                                        <div class="accordion-body small">
                                            <?php if (!empty($def['mail_field'])): ?>
                                                <div class="mb-2"><span class="badge bg-success">mail_field</span> <code><?= htmlspecialchars($def['mail_field']) ?></code></div>
                                            <?php endif; ?>

                                            <div class="fw-semibold mb-1">Campos:</div>
                                            <ul class="list-unstyled mb-2" style="max-height: 220px; overflow-y: auto;">
                                                <?php foreach ($def['fields'] as $fname => $fdef): ?>
                                                    <li>
                                                        <code><?= htmlspecialchars($fname) ?></code>
                                                        <span class="text-muted">(<?= htmlspecialchars($fdef['type'] ?? 'string') ?>)</span>
                                                        <?= !empty($fdef['is_mail_target']) ? '<span class="badge bg-success">mail</span>' : '' ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>

                                            <?php if (!empty($def['relations'])): ?>
                                                <div class="fw-semibold mb-1">Relaciones:</div>
                                                <ul class="list-unstyled mb-0">
                                                    <?php foreach ($def['relations'] as $rname => $rdef): ?>
                                                        <li>
                                                            <code><?= htmlspecialchars($rname) ?></code>
                                                            <span class="text-muted">
                                                                <?= htmlspecialchars($rdef['type'] ?? 'hasMany') ?> →
                                                                <?= htmlspecialchars($rdef['target_entity'] ?? $rname) ?>
                                                            </span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <hr class="my-3">

                        <details class="small">
                            <summary class="fw-semibold text-primary" style="cursor:pointer;">Ejemplo de JSON</summary>
                            <pre class="mt-2 p-2 rounded" style="background:#0f1116; color:#d0d0d0; font-size:0.78rem;">{
  "root_entity": "CrmClientes",
  "relations": [
    { "from": "CrmClientes", "relation": "CrmPresupuestos" }
  ],
  "fields": [
    { "entity": "CrmClientes", "field": "razon_social" },
    { "entity": "CrmClientes", "field": "email" },
    { "entity": "CrmPresupuestos", "field": "total" }
  ],
  "filters": [
    { "entity": "CrmClientes", "field": "activo",
      "op": "=", "value": 1 }
  ]
}</pre>
                        </details>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Modal de Preview -->
    <div id="preview-panel" class="card border-0 shadow-sm mt-4" style="display: none;">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3"><i class="bi bi-eye"></i> Preview del diseño</h5>
            <div id="preview-content"></div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
ob_start();
?>
<script>
(function () {
    const btn = document.getElementById('btn-preview');
    const panel = document.getElementById('preview-panel');
    const out = document.getElementById('preview-content');
    const jsonTa = document.getElementById('config_json');

    if (!btn || !panel || !out || !jsonTa) return;

    btn.addEventListener('click', async () => {
        let config;
        try {
            config = JSON.parse(jsonTa.value);
        } catch (e) {
            panel.style.display = '';
            out.innerHTML = '<div class="alert alert-danger mb-0">JSON inválido: ' + escapeHtml(e.message) + '</div>';
            return;
        }

        panel.style.display = '';
        out.innerHTML = '<div class="text-info">⏳ Ejecutando preview...</div>';
        btn.disabled = true;

        try {
            const res = await fetch('/mi-empresa/crm/mail-masivos/reportes/preview', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(config),
            });
            const data = await res.json();

            if (!data.success) {
                out.innerHTML = '<div class="alert alert-danger">'
                    + '<strong>' + (data.kind === 'validation' ? 'Error de validación' : 'Error del servidor') + ':</strong> '
                    + escapeHtml(data.message || 'sin detalle')
                    + '</div>';
                return;
            }

            let html = '<div class="alert alert-success py-2 small mb-3">';
            html += '<strong>✓ OK</strong> — ' + data.row_count + ' fila(s), '
                  + data.mail_count + ' mail(s) único(s). '
                  + 'Campo destinatario: <code>' + escapeHtml(data.mail_target.entity + '.' + data.mail_target.field) + '</code>';
            html += '</div>';

            if (data.mails.length > 0) {
                html += '<div class="mb-3"><strong>Mails:</strong><br><code>' + data.mails.map(escapeHtml).join(', ') + '</code></div>';
            }

            if (data.rows.length > 0) {
                const cols = Object.keys(data.rows[0]);
                html += '<div class="table-responsive"><table class="table table-sm table-striped"><thead><tr>';
                cols.forEach(c => { html += '<th>' + escapeHtml(c) + '</th>'; });
                html += '</tr></thead><tbody>';
                data.rows.forEach(row => {
                    html += '<tr>';
                    cols.forEach(c => {
                        html += '<td>' + escapeHtml(row[c] == null ? '' : String(row[c])) + '</td>';
                    });
                    html += '</tr>';
                });
                html += '</tbody></table></div>';
            }

            html += '<details class="mt-3"><summary class="small fw-semibold">SQL generado (debug)</summary>';
            html += '<pre class="p-2 mt-2 rounded" style="background:#0f1116; color:#d0d0d0; font-size:0.78rem;">'
                  + escapeHtml(data.sql_debug) + '</pre>';
            html += '<div class="small text-muted mt-1">Params: <code>' + escapeHtml(JSON.stringify(data.params_debug)) + '</code></div>';
            html += '</details>';

            out.innerHTML = html;
        } catch (e) {
            out.innerHTML = '<div class="alert alert-danger mb-0">Error de red: ' + escapeHtml(e.message) + '</div>';
        } finally {
            btn.disabled = false;
        }
    });

    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }
})();
</script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
