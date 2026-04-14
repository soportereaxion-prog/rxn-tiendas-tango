<?php
/**
 * Administración cross-user de vistas guardadas de RXN Live.
 *
 * Variables recibidas:
 *   - $vistas (array): lista de vistas con datos del dueño
 *   - $datasets (array): map [key => ['name' => '...', 'description' => '...']]
 *   - $filterDataset (string): filtro activo por dataset (puede ser '')
 *   - $success (string|null)
 *   - $error (string|null)
 */
ob_start();
?>
<div class="container-xl mt-5 mb-5 rxn-responsive-container rxn-form-shell" style="max-width: 1280px;">
    <div class="mb-4 rxn-module-header">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-collection-fill text-primary me-2"></i>Vistas Guardadas (Admin)</h2>
            <p class="text-muted small mb-0">Gestión cross-user de configuraciones de RXN Live &mdash; útil para destrabar datasets con vistas corruptas.</p>
        </div>
        <div class="rxn-module-actions">
            <a href="/rxn_live" class="btn btn-outline-secondary shadow-sm">
                <i class="bi bi-arrow-left"></i> Volver a RXN Live
            </a>
        </div>
    </div>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?= $success /* ya viene html-escaped desde controller */ ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- BARRA DE ACCIONES -->
    <div class="card rxn-form-card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="d-flex flex-column flex-md-row gap-3 justify-content-between align-items-md-center">
                <form method="GET" action="/admin/rxn_live/vistas" class="d-flex gap-2 align-items-center">
                    <label class="text-muted small mb-0">Filtrar por dataset:</label>
                    <select name="dataset" class="form-select form-select-sm" style="max-width: 260px;" onchange="this.form.submit()">
                        <option value="">Todos los datasets</option>
                        <?php foreach ($datasets as $k => $ds): ?>
                            <option value="<?= htmlspecialchars($k) ?>" <?= $filterDataset === $k ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ds['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($filterDataset !== ''): ?>
                        <a href="/admin/rxn_live/vistas" class="btn btn-sm btn-outline-secondary" title="Limpiar filtro">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </form>

                <div class="d-flex gap-2">
                    <a href="/admin/rxn_live/vistas/exportar<?= $filterDataset !== '' ? '?dataset=' . urlencode($filterDataset) : '' ?>"
                       class="btn btn-sm btn-outline-primary"
                       title="Descargar JSON con <?= $filterDataset !== '' ? 'vistas del dataset' : 'todas las vistas' ?>">
                        <i class="bi bi-download me-1"></i>
                        Exportar <?= $filterDataset !== '' ? 'Dataset' : 'Todo' ?>
                    </a>
                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#importVistasModal">
                        <i class="bi bi-upload me-1"></i> Importar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLA DE VISTAS -->
    <div class="card rxn-form-card shadow-sm border-0">
        <div class="card-body p-0">
            <?php if (empty($vistas)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                    <div>No hay vistas guardadas<?= $filterDataset !== '' ? ' para este dataset' : '' ?>.</div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="width: 70px;">ID</th>
                                <th>Dataset</th>
                                <th>Nombre</th>
                                <th>Dueño</th>
                                <th style="width: 170px;">Creada</th>
                                <th class="text-end pe-3" style="width: 200px;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vistas as $v):
                                $dsName = $datasets[$v['dataset']]['name'] ?? $v['dataset'];
                            ?>
                                <tr data-view-id="<?= (int)$v['id'] ?>">
                                    <td class="ps-3 text-muted small"><?= (int)$v['id'] ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($dsName) ?></span>
                                    </td>
                                    <td class="fw-semibold"><?= htmlspecialchars($v['nombre']) ?></td>
                                    <td>
                                        <?php if (!empty($v['usuario_nombre'])): ?>
                                            <div class="small"><?= htmlspecialchars($v['usuario_nombre']) ?></div>
                                            <div class="text-muted small"><?= htmlspecialchars($v['usuario_email'] ?? '') ?></div>
                                        <?php else: ?>
                                            <span class="text-muted small">Usuario #<?= (int)$v['usuario_id'] ?> (eliminado)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small"><?= htmlspecialchars($v['created_at'] ?? '') ?></td>
                                    <td class="text-end pe-3">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-outline-info" title="Ver JSON config"
                                                    onclick="verVistaConfig(<?= (int)$v['id'] ?>)">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <a class="btn btn-outline-primary" title="Exportar esta vista"
                                               href="/admin/rxn_live/vistas/exportar?ids=<?= (int)$v['id'] ?>">
                                                <i class="bi bi-download"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger" title="Eliminar vista"
                                                    onclick="eliminarVista(<?= (int)$v['id'] ?>, '<?= htmlspecialchars(addslashes($v['nombre']), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($dsName), ENT_QUOTES) ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <p class="text-muted small mt-3 mb-0">
        <i class="bi bi-info-circle me-1"></i>
        Tip: si un dataset queda titilando por una vista corrupta, abrí el dataset con <code>?safe_mode=1</code> y desde acá eliminá la vista que la rompe.
    </p>
</div>

<!-- MODAL: Ver JSON config -->
<div class="modal fade" id="verVistaModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-code-square me-2 text-info"></i>Config de Vista</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="verVistaMeta" class="mb-3 small text-muted"></div>
                <pre id="verVistaJson" class="bg-dark text-light p-3 rounded small" style="max-height: 480px; overflow: auto;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="copiarConfigPortapapeles()">
                    <i class="bi bi-clipboard"></i> Copiar JSON
                </button>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Importar -->
<div class="modal fade" id="importVistasModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="/admin/rxn_live/vistas/importar" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-upload me-2 text-success"></i>Importar Vistas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Archivo JSON</label>
                        <input type="file" name="archivo" accept=".json,application/json" class="form-control" required>
                        <div class="form-text">Formato soportado: exportación generada por esta herramienta, o un objeto con <code>dataset</code>, <code>nombre</code> y <code>config</code>.</div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Reasignar dueño (opcional)</label>
                        <input type="number" name="owner_id" class="form-control" placeholder="ID de usuario — vacío = mantener dueño original">
                        <div class="form-text">Si dejás vacío, las vistas se importan con el <code>usuario_id</code> del JSON. Si no hay, quedan a tu nombre.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="bi bi-upload me-1"></i> Importar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let verVistaModalInstance = null;
let currentConfigJson = '';

function verVistaConfig(id) {
    fetch('/admin/rxn_live/vistas/ver?id=' + encodeURIComponent(id))
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                (window.rxnAlert || alert)('Error: ' + (res.message || 'No se pudo leer'), 'danger');
                return;
            }
            const v = res.vista;
            const meta = `<strong>#${v.id}</strong> · ${escapeHtml(v.nombre)} · dataset: <code>${escapeHtml(v.dataset)}</code>`
                + (v.usuario_email ? ` · dueño: ${escapeHtml(v.usuario_email)}` : ` · usuario #${v.usuario_id}`);
            document.getElementById('verVistaMeta').innerHTML = meta;
            currentConfigJson = JSON.stringify(v.config, null, 2);
            document.getElementById('verVistaJson').textContent = currentConfigJson;
            if (!verVistaModalInstance) {
                verVistaModalInstance = new bootstrap.Modal(document.getElementById('verVistaModal'));
            }
            verVistaModalInstance.show();
        })
        .catch(e => {
            console.error(e);
            (window.rxnAlert || alert)('Error de red al leer la vista.', 'danger');
        });
}

function copiarConfigPortapapeles() {
    if (!currentConfigJson) return;
    navigator.clipboard.writeText(currentConfigJson).then(() => {
        (window.rxnAlert || alert)('Config copiada al portapapeles.', 'success');
    }).catch(() => {
        (window.rxnAlert || alert)('No se pudo copiar. Seleccioná y copiá manualmente.', 'warning');
    });
}

function eliminarVista(id, nombre, dataset) {
    const doDelete = () => {
        const fd = new FormData();
        fd.append('id', id);
        fetch('/admin/rxn_live/vistas/eliminar', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    const row = document.querySelector(`tr[data-view-id="${id}"]`);
                    if (row) row.remove();
                    (window.rxnAlert || alert)('Vista eliminada.', 'success');
                } else {
                    (window.rxnAlert || alert)('No se pudo eliminar: ' + (res.message || 'error'), 'danger');
                }
            })
            .catch(e => {
                console.error(e);
                (window.rxnAlert || alert)('Error de red al eliminar.', 'danger');
            });
    };

    const msg = `Se va a eliminar la vista "${nombre}" del dataset ${dataset}. Esta acción no se puede deshacer.`;
    if (window.rxnConfirm) {
        window.rxnConfirm({
            title: 'Atención',
            message: msg,
            type: 'danger',
            okText: 'Eliminar',
            okClass: 'btn-danger',
            onConfirm: doDelete,
        });
    } else if (confirm(msg)) {
        doDelete();
    }
}

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' })[c]);
}
</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
