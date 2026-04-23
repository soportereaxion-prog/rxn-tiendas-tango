<?php
$pageTitle = 'RXN Suite';
ob_start();
?>
<?php $basePath = $basePath ?? '/mi-empresa/crm/clientes'; ?>
    <div class="container-fluid mt-2 mb-5 rxn-responsive-container rxn-form-shell">
        <div class="rxn-module-header mb-4">
            <div>
                <h2 class="fw-bold mb-1"><?= htmlspecialchars((string) ($editTitle ?? 'Modificar Cliente CRM')) ?></h2>
                
            </div>
            <div class="d-flex gap-2">
                <a href="<?= htmlspecialchars($basePath) ?>" class="btn btn-outline-secondary btn-sm" title="Volver al listado de Clientes">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
                <?php if (!empty($cliente['id'])): ?>
                    <button type="button" id="btn-form-push"
                        class="btn btn-outline-warning"
                        title="Push → Tango"
                        data-id="<?= (int)$cliente['id'] ?>"
                        data-entidad="cliente"
                        data-nombre="<?= htmlspecialchars((string)($cliente['razon_social'] ?? '')) ?>"
                        data-base="<?= htmlspecialchars($basePath) ?>">
                        <i class="bi bi-cloud-upload"></i> Push
                    </button>
                    <button type="button" id="btn-form-pull"
                        class="btn btn-outline-info"
                        title="Pull ← Tango"
                        data-id="<?= (int)$cliente['id'] ?>"
                        data-entidad="cliente"
                        data-nombre="<?= htmlspecialchars((string)($cliente['razon_social'] ?? '')) ?>">
                        <i class="bi bi-cloud-download"></i> Pull
                    </button>
                    <button type="button" id="btn-form-info"
                        class="btn btn-outline-secondary"
                        title="Ver último payload Tango"
                        data-id="<?= (int)$cliente['id'] ?>"
                        data-entidad="cliente">
                        <i class="bi bi-info-circle"></i>
                    </button>
                    <form action="<?= htmlspecialchars($basePath) ?>/<?= (int) $cliente['id'] ?>/copiar" method="POST" class="d-inline">
                        <button type="submit" class="btn btn-outline-success" title="Duplicar">
                            <i class="bi bi-copy"></i>
                        </button>
                    </form>
                    <form action="<?= htmlspecialchars($basePath) ?>/eliminar-masivo" method="POST" class="d-inline rxn-confirm-form" data-msg="¿Enviar cliente a la papelera?">
                        <input type="hidden" name="ids[]" value="<?= (int) $cliente['id'] ?>">
                        <button type="submit" class="btn btn-outline-danger" title="Enviar a papelera">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php require BASE_PATH . '/app/shared/views/components/module_notes_panel.php'; ?>

        <?php $flash = \App\Core\Flash::get(); ?>
        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars((string) $flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
                <strong><?= $flash['type'] === 'success' ? 'OK' : 'Atención' ?></strong> <?= htmlspecialchars((string) $flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card rxn-form-card border-0 shadow-sm">
            <div class="card-body p-4 p-lg-5">
                <form action="<?= htmlspecialchars($basePath) ?>/editar?id=<?= (int) ($cliente['id'] ?? 0) ?>" method="POST">
                    <div class="rxn-form-section">
                        <div class="rxn-form-section-title">Identificadores Tango</div>
                        <div class="rxn-form-grid">
                            <div class="rxn-form-span-4">
                                <label class="form-label text-muted">ID local</label>
                                <input type="text" class="form-control" value="<?= (int) ($cliente['id'] ?? 0) ?>" disabled>
                            </div>
                            <div class="rxn-form-span-4">
                                <label class="form-label text-muted">ID GVA14 Tango</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars((string) ($cliente['id_gva14_tango'] ?? '')) ?>" disabled>
                            </div>
                            <div class="rxn-form-span-4">
                                <label class="form-label text-muted">Codigo Tango</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars((string) ($cliente['codigo_tango'] ?? '')) ?>" disabled>
                            </div>
                        </div>
                    </div>

                    <div class="rxn-form-section">
                        <div class="rxn-form-section-title">Ficha local</div>
                        <div class="rxn-form-grid">
                            <div class="rxn-form-span-8">
                                <label for="razon_social" class="form-label">Razon social</label>
                                <input type="text" class="form-control" id="razon_social" name="razon_social" value="<?= htmlspecialchars((string) ($cliente['razon_social'] ?? '')) ?>" required>
                            </div>
                            <div class="rxn-form-span-4">
                                <label for="documento" class="form-label">CUIT / Documento</label>
                                <input type="text" class="form-control" id="documento" name="documento" value="<?= htmlspecialchars((string) ($cliente['documento'] ?? '')) ?>">
                            </div>
                            <div class="rxn-form-span-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars((string) ($cliente['email'] ?? '')) ?>">
                            </div>
                            <div class="rxn-form-span-6">
                                <label for="telefono" class="form-label">Telefono</label>
                                <input type="text" class="form-control" id="telefono" name="telefono" value="<?= htmlspecialchars((string) ($cliente['telefono'] ?? '')) ?>">
                            </div>
                            <div class="rxn-form-span-12">
                                <label for="direccion" class="form-label">Direccion</label>
                                <input type="text" class="form-control" id="direccion" name="direccion" value="<?= htmlspecialchars((string) ($cliente['direccion'] ?? '')) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="rxn-form-section bg-light p-3 p-lg-4 rounded border border-warning border-opacity-25">
                        <div class="rxn-form-grid">
                            <div class="rxn-form-span-8">
                                <label class="form-label text-warning fw-bold mb-1">Ultima sincronizacion</label>
                                <input type="text" class="form-control " id="fecha_ultima_sync" value="<?= htmlspecialchars((string) ($cliente['fecha_ultima_sync'] ?? '')) ?>" disabled>
                                <div class="form-text text-muted mt-2"><small>El origen maestro sigue siendo Tango/Connect. Esta ficha solo permite ajustes locales sobre la cache operativa.</small></div>
                            </div>
                            <div class="rxn-form-span-4 d-flex align-items-end">
                                <div class="rxn-form-switch-card w-100">
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" <?= !empty($cliente['activo']) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-semibold" for="activo">Cliente activo</label>
                                        <div class="form-text mb-0">Controla si el cliente queda disponible para operar en CRM.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rxn-form-actions mt-4">
                        <a href="<?= htmlspecialchars($basePath) ?>" class="btn btn-light border">Cancelar</a>
                        <button type="submit" class="btn btn-primary px-4 fw-bold">Guardar Modificaciones Locales</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
<?php
$content = ob_get_clean();
ob_start();
?>
<script src="/js/rxn-confirm-modal.js"></script>
<script src="/js/rxn-shortcuts.js"></script>
<?php if (!empty($cliente['id'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var syncBase = '/mi-empresa/crm/rxn-sync';

    function renderJsonBlock(label, data) {
        if (!data) return '';
        var json = JSON.stringify(data, null, 2).replace(/</g, '&lt;').replace(/>/g, '&gt;');
        return '<details><summary style="font-size:.8rem;color:#aaa;cursor:pointer;">' + label + '</summary>'
            + '<pre style="font-size:.7rem;max-height:200px;overflow:auto;background:#111;color:#0f0;'
            + 'padding:8px;border-radius:4px;margin-top:4px;text-align:left;">' + json + '</pre></details>';
    }

    function payloadHtml(meta, snapshot, summaryLabel) {
        var lines = [
            '<strong>Estado:</strong> ' + (meta.estado || '—'),
            '<strong>Dirección:</strong> ' + (meta.direccion || '—').toUpperCase() +
                ' <span class="badge bg-' + (meta.resultado === 'ok' ? 'success' : 'danger') + ' ms-1">' +
                (meta.resultado || '—').toUpperCase() + '</span>',
            '<strong>Tango ID:</strong> #' + (meta.tango_id || '?'),
            '<strong>Fecha:</strong> ' + (meta.fecha || '—'),
        ];
        if (meta.error) lines.push('<strong class="text-danger">Error:</strong> ' + meta.error);
        var html = '<div style="font-size:.85rem;margin-bottom:8px;">' + lines.join('<br>') + '</div>';
        if (snapshot) {
            html += renderJsonBlock(summaryLabel || 'Snapshot Tango', snapshot);
        }
        return html;
    }

    function syncResultHtml(endpoint, payload) {
        if (!payload) return '';
        var html = payloadHtml(
            { tango_id: payload.tango_id, estado: 'vinculado', direccion: endpoint, resultado: 'ok' },
            endpoint === 'push' ? payload.payload_enviado : payload.snapshot_tango,
            endpoint === 'push' ? 'Payload enviado' : 'Snapshot Tango'
        );
        if (endpoint === 'push' && payload.response_tango) {
            html += renderJsonBlock('Respuesta API', payload.response_tango);
        }
        return html;
    }

    function fetchJson(url, options) {
        return fetch(url, options).then(function(r) {
            return r.text().then(function(text) {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    var raw = (text || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
                    throw new Error(raw || 'La respuesta no vino en JSON válido.');
                }
            });
        });
    }

    function applyPulledData(payload) {
        if (!payload || !payload.local_actualizado) return;
        var local = payload.local_actualizado;
        var syncAt = document.getElementById('fecha_ultima_sync');
        if (document.getElementById('razon_social') && typeof local.razon_social !== 'undefined') document.getElementById('razon_social').value = local.razon_social || '';
        if (document.getElementById('documento') && typeof local.documento !== 'undefined') document.getElementById('documento').value = local.documento || '';
        if (document.getElementById('email') && typeof local.email !== 'undefined') document.getElementById('email').value = local.email || '';
        if (document.getElementById('telefono') && typeof local.telefono !== 'undefined') document.getElementById('telefono').value = local.telefono || '';
        if (document.getElementById('direccion') && typeof local.direccion !== 'undefined') document.getElementById('direccion').value = local.direccion || '';
        if (document.getElementById('activo') && typeof local.activo !== 'undefined') document.getElementById('activo').checked = !!Number(local.activo);
        if (syncAt) syncAt.value = new Date().toLocaleString('sv-SE').replace('T', ' ');

        var pushBtn = document.getElementById('btn-form-push');
        var pullBtn = document.getElementById('btn-form-pull');
        if (local.razon_social) {
            if (pushBtn) pushBtn.dataset.nombre = local.razon_social;
            if (pullBtn) pullBtn.dataset.nombre = local.razon_social;
        }
    }

    function doSync(endpoint, id, entidad, btn, base) {
        btn.disabled = true;
        var url = syncBase + '/' + endpoint;
        var options = { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } };

        if (endpoint === 'push' && base) {
            url = base + '/' + id + '/push-tango';
        } else {
            var form = new FormData();
            form.append('id', id);
            form.append('entidad', entidad);
            options.body = form;
        }

        fetchJson(url, options)
            .then(function(d) {
                var msg = d.message || '';
                if (d.success && d.payload) {
                    msg += '<br>' + syncResultHtml(endpoint, d.payload);
                    if (endpoint === 'pull') applyPulledData(d.payload);
                }
                window.rxnAlert(msg, d.success ? 'success' : 'danger',
                    d.success ? endpoint.toUpperCase() + ' OK' : 'Error');
                if (!d.success) btn.disabled = false;
            }).catch(function(e) {
                window.rxnAlert(e.message || 'Error de red', 'danger', 'Error');
                btn.disabled = false;
            });
    }

    var pushBtn = document.getElementById('btn-form-push');
    var pullBtn = document.getElementById('btn-form-pull');
    var infoBtn = document.getElementById('btn-form-info');

    if (pushBtn) {
        pushBtn.addEventListener('click', function() {
            var self = this;
            var id = self.dataset.id, nombre = self.dataset.nombre, entidad = self.dataset.entidad, base = self.dataset.base;
            window.rxnConfirm({
                message: '¿Sincronizar "' + nombre + '" hacia Tango?',
                type: 'warning', title: 'Confirmar Push',
                okText: 'Push', okClass: 'btn-warning',
                onConfirm: function() { doSync('push', id, entidad, self, base); }
            });
        });
    }
    if (pullBtn) {
        pullBtn.addEventListener('click', function() {
            var self = this;
            var id = self.dataset.id, nombre = self.dataset.nombre, entidad = self.dataset.entidad;
            window.rxnConfirm({
                message: '¿Traer datos de "' + nombre + '" desde Tango? Los datos locales se actualizarán.',
                type: 'info', title: 'Confirmar Pull',
                okText: 'Pull', okClass: 'btn-info',
                onConfirm: function() { doSync('pull', id, entidad, self); }
            });
        });
    }
    if (infoBtn) {
        infoBtn.addEventListener('click', function() {
            var id = this.dataset.id, entidad = this.dataset.entidad;
            fetchJson(syncBase + '/payload?entidad=' + entidad + '&id=' + id, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(d) {
                if (!d.success) { window.rxnAlert(d.message, 'warning', 'Sin historial'); return; }
                window.rxnAlert(payloadHtml(d.meta, d.snapshot), 'info',
                    'Historial Tango — ID #' + (d.meta.tango_id || '?'));
            }).catch(function(e) {
                window.rxnAlert(e.message || 'Error de red', 'danger', 'Error');
            });
        });
    }
});
</script>
<?php endif; ?>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>

