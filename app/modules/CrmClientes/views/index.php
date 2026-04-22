<?php
$pageTitle = 'RXN Suite';
ob_start();
?>
<?php
    $field = $field ?? 'all';
    $basePath = $basePath ?? '/mi-empresa/crm/clientes';
    $buildQuery = function (array $overrides = []) use ($search, $field, $limit, $sort, $dir, $page) {
        $params = [
            'search' => $search,
            'field' => $field,
            'limit' => $limit,
            'sort' => $sort,
            'dir' => $dir,
            'page' => $page,
            'status' => $status ?? 'activos',
        ];

        foreach ($overrides as $key => $value) {
            if ($value === null || $value === '') {
                unset($params[$key]);
                continue;
            }
            $params[$key] = $value;
        }

        return http_build_query($params);
    };
    ?>
    <div class="container-fluid mt-2 rxn-responsive-container">
        <div class="rxn-module-header mb-4">
            <div>
                <h2><?= htmlspecialchars((string) ($headerTitle ?? 'Directorio de Clientes CRM')) ?></h2>
                <p class="text-muted"><?= htmlspecialchars((string) ($headerDescription ?? 'Gestion de clientes CRM.')) ?></p>
            </div>
            <div class="rxn-module-actions">

                <a href="<?= htmlspecialchars((string) ($helpPath ?? '')) ?>" class="btn btn-outline-info btn-sm" target="_blank" rel="noopener noreferrer" title="Ayuda"><i class="bi bi-question-circle"></i> Ayuda</a>
                <a href="<?= htmlspecialchars((string) ($dashboardPath ?? '')) ?>" class="btn btn-outline-secondary btn-sm" title="Volver al CRM"><i class="bi bi-arrow-left"></i> Volver</a>
            </div>
        </div>

        <?php require BASE_PATH . '/app/shared/views/components/module_notes_panel.php'; ?>

        <?php $flash = \App\Core\Flash::get(); ?>
        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars((string) $flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
                <strong><?= $flash['type'] === 'success' ? 'OK' : 'Atencion' ?></strong> <?= htmlspecialchars((string) $flash['message']) ?>
                <?php if (!empty($flash['stats'])): ?>
                    <ul class="mb-0 mt-2 fs-6">
                        <li>Recibidos en capa de red: <b class="text-primary"><?= (int) ($flash['stats']['recibidos'] ?? 0) ?></b></li>
                        <li>Nuevos localmente: <b class="text-success"><?= (int) ($flash['stats']['insertados'] ?? 0) ?></b></li>
                        <li>Actualizados: <b class="text-info"><?= (int) ($flash['stats']['actualizados'] ?? 0) ?></b></li>
                        <li>Omitidos: <b class="text-secondary"><?= (int) ($flash['stats']['omitidos'] ?? 0) ?></b></li>
                    </ul>
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php 
        $status = $status ?? 'activos';
        $isPapelera = $status === 'papelera';
        ?>

        <ul class="nav nav-tabs mb-4 rxn-crud-tabs">
            <li class="nav-item">
                <a class="nav-link <?= !$isPapelera ? 'active fw-bold' : '' ?>" href="<?= htmlspecialchars($basePath) ?>?<?= htmlspecialchars($buildQuery(['status' => 'activos', 'page' => 1])) ?>">
                    Activos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-danger <?= $isPapelera ? 'active fw-bold' : '' ?>" href="<?= htmlspecialchars($basePath) ?>?<?= htmlspecialchars($buildQuery(['status' => 'papelera', 'page' => 1])) ?>">
                    <i class="bi bi-trash"></i> Papelera
                </a>
            </li>
        </ul>

        <div class="card rxn-crud-card">
            <div class="card-body p-4">
                <div class="rxn-toolbar-split mb-4">
                    <span class="badge bg-primary text-light fs-6 py-2 px-3"><?= htmlspecialchars((string) ($totalBadgeLabel ?? 'Total CRM')) ?>: <?= (int) $totalItems ?></span>
                    <div class="rxn-toolbar-actions">
                        <form action="<?= htmlspecialchars($basePath) ?>/purgar" method="POST" class="d-inline">
                            <button type="submit" class="btn btn-danger btn-sm fw-bold shadow-sm" data-rxn-confirm="¿Purgar toda la cache local de clientes CRM?" data-confirm-type="danger">Purgar Todo</button>
                        </form>
                        <a href="/mi-empresa/crm/rxn-sync" class="btn btn-warning btn-sm fw-bold shadow-sm text-dark">
                            <i class="fas fa-sync me-1"></i> Auditoría RXN Sync
                        </a>
                    </div>
                </div>

                <div class="rxn-toolbar-split mb-3">
                    <div class="small text-muted">Buscador con sugerencias en vivo; el listado solo se filtra al confirmar.</div>
                    <form action="<?= htmlspecialchars($basePath) ?>" method="GET" class="rxn-filter-form justify-content-end flex-md-nowrap ms-md-auto" style="width: 860px; max-width: 100%;" data-search-form>
                        <input type="hidden" name="status" value="<?= htmlspecialchars((string) $status) ?>">
                        <input type="hidden" name="search" value="<?= htmlspecialchars((string) $search) ?>" data-search-hidden>
                        <select name="limit" class="form-select form-select-sm border-info rxn-filter-compact" style="width: 80px;" onchange="this.form.submit()">
                            <option value="25" <?= $limit === 25 ? 'selected' : '' ?>>25</option>
                            <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100</option>
                        </select>
                        <select name="field" class="form-select form-select-sm border-info rxn-filter-compact rxn-search-field-wrap" style="width: 165px;" data-search-field>
                            <option value="all" <?= $field === 'all' ? 'selected' : '' ?>>Todos los campos</option>
                            <option value="id" <?= $field === 'id' ? 'selected' : '' ?>>ID</option>
                            <option value="codigo_tango" <?= $field === 'codigo_tango' ? 'selected' : '' ?>>Codigo Tango</option>
                            <option value="razon_social" <?= $field === 'razon_social' ? 'selected' : '' ?>>Razon social</option>
                            <option value="documento" <?= $field === 'documento' ? 'selected' : '' ?>>CUIT / Doc</option>
                            <option value="email" <?= $field === 'email' ? 'selected' : '' ?>>Email</option>
                            <option value="telefono" <?= $field === 'telefono' ? 'selected' : '' ?>>Telefono</option>
                        </select>
                        <div class="rxn-search-input-wrap rxn-filter-grow">
                            <input type="text" class="form-control form-control-sm border-info" placeholder='🔎 Presioná F3 o "/" para buscar' value="<?= htmlspecialchars((string) $search) ?>" data-search-input data-suggestions-url="<?= htmlspecialchars($basePath) ?>/sugerencias" autocomplete="off">
                            <div class="rxn-search-suggestions d-none" data-search-suggestions></div>
                        </div>
                        <button type="submit" class="btn btn-info btn-sm text-white">Buscar</button>
                    </form>
                </div>
                <div class="form-text rxn-search-help text-md-end mb-3">Se sugieren coincidencias mientras escribis, pero el listado solo se filtra al buscar o presionar Enter.</div>

                <?php if (!$isPapelera): ?>
                <div class="mb-3">
                    <button type="submit" form="hiddenFormBulk" formaction="<?= htmlspecialchars($basePath) ?>/eliminar-masivo" class="btn btn-outline-danger btn-sm rxn-confirm-form" data-msg="¿Enviar los clientes seleccionados a la papelera?"><i class="bi bi-trash"></i> Eliminar Seleccionados</button>
                </div>
                <?php else: ?>
                <div class="mb-3 d-flex gap-2">
                    <button type="submit" form="hiddenFormBulk" formaction="<?= htmlspecialchars($basePath) ?>/restore-masivo" class="btn btn-outline-success btn-sm rxn-confirm-form" data-msg="¿Restaurar los clientes seleccionados?"><i class="bi bi-arrow-counterclockwise"></i> Restaurar Seleccionados</button>
                    <button type="submit" form="hiddenFormBulk" formaction="<?= htmlspecialchars($basePath) ?>/force-delete-masivo" class="btn btn-outline-danger btn-sm rxn-confirm-form" data-msg="⚠️ ATENCIÓN: Acción irreversible. ¿Destruir definitivamente los clientes seleccionados?"><i class="bi bi-x-circle"></i> Destruir Seleccionados</button>
                </div>
                <?php endif; ?>

                <form method="POST" id="hiddenFormBulk"></form>
                    <div class="table-responsive rxn-table-responsive">
                        <table class="table table-hover align-middle table-sm rxn-crud-table" style="font-size: 0.9rem;">
                            <thead class="table-light">
                                <?php
                                $sortLink = function (string $fieldName, string $label) use ($buildQuery, $sort, $dir) {
                                    $newDir = ($sort === $fieldName && $dir === 'ASC') ? 'DESC' : 'ASC';
                                    $icon = ($sort === $fieldName) ? ($dir === 'ASC' ? '▲' : '▼') : '';
                                    $href = '?' . $buildQuery(['sort' => $fieldName, 'dir' => $newDir]);
                                    return '<a href="' . $href . '" class="rxn-sort-link"><span>' . $label . '</span><span class="rxn-sort-indicator">' . $icon . '</span></a>';
                                };
                                ?>
                                <tr>
                                    <th style="width: 40px;"><input type="checkbox" class="form-check-input" onclick="document.querySelectorAll('.check-item').forEach(e => e.checked = this.checked);"></th>
                                    <th class="rxn-filter-col rxn-hide-mobile" data-filter-field="codigo_tango"><?= $sortLink('codigo_tango', 'Codigo Tango') ?></th>
                                    <th class="rxn-filter-col" data-filter-field="razon_social"><?= $sortLink('razon_social', 'Razon Social') ?></th>
                                    <th class="rxn-filter-col rxn-hide-mobile" data-filter-field="documento"><?= $sortLink('documento', 'CUIT / Doc') ?></th>
                                    <th class="rxn-filter-col rxn-hide-mobile" data-filter-field="email"><?= $sortLink('email', 'Email') ?></th>
                                    <th class="rxn-filter-col rxn-hide-mobile" data-filter-field="telefono"><?= $sortLink('telefono', 'Telefono') ?></th>
                                    <th><?= $sortLink('activo', 'Estado') ?></th>
                                    <th class="rxn-hide-mobile"><?= $sortLink('fecha_ultima_sync', 'Ultima Sync') ?></th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($clientes === []): ?>
                                    <tr>
                                        <td colspan="9" class="rxn-empty-state text-muted">
                                            <div class="mb-2">-</div>
                                            <?= htmlspecialchars((string) ($emptyStateTitle ?? 'No hay clientes CRM sincronizados.')) ?><br>
                                            <?php if (!empty($emptyStateHint)): ?><small><?= htmlspecialchars((string) $emptyStateHint) ?></small><?php endif; ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($clientes as $cliente): ?>
                                        <tr data-row-link="<?= htmlspecialchars($basePath) ?>/editar?id=<?= (int) $cliente['id'] ?>">
                                            <td><input type="checkbox" name="ids[]" value="<?= (int) $cliente['id'] ?>" class="form-check-input check-item" form="hiddenFormBulk" data-row-link-ignore></td>
                                            <td class="text-nowrap rxn-hide-mobile"><span class="badge bg-secondary text-start" style="white-space: pre; font-family: monospace; font-size: 0.85rem;"><?= htmlspecialchars((string) ($cliente['codigo_tango'] ?? '')) ?></span></td>
                                            <td class="fw-bold text-dark text-wrap" style="max-width: 260px;">
                                                <?= htmlspecialchars((string) ($cliente['razon_social'] ?? 'Cliente')) ?>
                                                <div class="small text-muted">ID GVA14: <?= htmlspecialchars((string) ($cliente['id_gva14_tango'] ?? '--')) ?></div>
                                            </td>
                                            <td class="rxn-hide-mobile"><?= htmlspecialchars((string) ($cliente['documento'] ?? '--')) ?></td>
                                            <td class="rxn-hide-mobile"><?= htmlspecialchars((string) ($cliente['email'] ?? '--')) ?></td>
                                            <td class="rxn-hide-mobile"><?= htmlspecialchars((string) ($cliente['telefono'] ?? '--')) ?></td>
                                            <td>
                                                <?php if (!empty($cliente['activo'])): ?>
                                                    <span class="badge bg-success bg-opacity-75">Activo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger bg-opacity-75">Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-nowrap rxn-hide-mobile"><small class="text-secondary"><?= htmlspecialchars((string) ($cliente['fecha_ultima_sync'] ?? '--')) ?></small></td>
                                            <td class="text-end text-nowrap">
                                                <div class="btn-group" data-row-link-ignore>
                                                     <?php if (!$isPapelera): ?>
                                                        <a href="<?= htmlspecialchars($basePath) ?>/editar?id=<?= (int) $cliente['id'] ?>" class="btn btn-sm btn-outline-info" title="Editar"><i class="bi bi-pencil"></i></a>

                                                        <button type="button"
                                                            class="btn btn-sm btn-outline-warning btn-push-tango-row"
                                                            title="Push → Tango"
                                                            data-id="<?= (int) $cliente['id'] ?>"
                                                            data-entidad="cliente"
                                                            data-nombre="<?= htmlspecialchars((string)$cliente['razon_social']) ?>"
                                                            data-base="<?= htmlspecialchars($basePath) ?>">
                                                            <i class="bi bi-cloud-upload"></i>
                                                        </button>
                                                        <button type="button"
                                                            class="btn btn-sm btn-outline-info btn-pull-tango-row"
                                                            title="Pull ← Tango"
                                                            data-id="<?= (int) $cliente['id'] ?>"
                                                            data-entidad="cliente"
                                                            data-nombre="<?= htmlspecialchars((string)$cliente['razon_social']) ?>">
                                                            <i class="bi bi-cloud-download"></i>
                                                        </button>
                                                        <button type="button"
                                                            class="btn btn-sm btn-outline-secondary btn-payload-info"
                                                            title="Ver último payload Tango"
                                                            data-id="<?= (int) $cliente['id'] ?>"
                                                            data-entidad="cliente">
                                                            <i class="bi bi-info-circle"></i>
                                                        </button>

                                                        <form action="<?= htmlspecialchars($basePath) ?>/<?= (int) $cliente['id'] ?>/eliminar" method="POST" class="d-inline m-0 p-0 rxn-confirm-form" data-msg="¿Enviar cliente a la papelera?">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" style="border-top-left-radius: 0; border-bottom-left-radius: 0;" title="Eliminar (Papelera)">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form action="<?= htmlspecialchars($basePath) ?>/<?= (int) $cliente['id'] ?>/restore" method="POST" class="d-inline m-0 p-0 rxn-confirm-form" data-msg="¿Confirma restaurar este cliente?">
                                                            <button type="submit" class="btn btn-sm btn-outline-success border-end-0" title="Restaurar Cliente">
                                                                <i class="bi bi-arrow-counterclockwise"></i> Restaurar
                                                            </button>
                                                        </form>

                                                        <form action="<?= htmlspecialchars($basePath) ?>/force-delete?id=<?= (int) $cliente['id'] ?>" method="POST" class="d-inline m-0 p-0 rxn-confirm-form" data-msg="⚠️ ATENCIÓN: Esta acción es irreversible. ¿Eliminar definitivamente?">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" style="border-top-left-radius: 0; border-bottom-left-radius: 0;" title="Eliminar Definitivamente">
                                                                <i class="bi bi-x-circle"></i> Destruir
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                <?php if ($totalPages > 1): ?>
                    <nav class="mt-4 rxn-pagination-wrap">
                        <ul class="pagination justify-content-center pagination-sm">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= htmlspecialchars($buildQuery(['page' => max(1, $page - 1)])) ?>">Anterior</a>
                            </li>
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= htmlspecialchars($buildQuery(['page' => $i])) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= htmlspecialchars($buildQuery(['page' => min($totalPages, $page + 1)])) ?>">Siguiente</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
<?php
$content = ob_get_clean();
ob_start();
?>
<script src="/js/rxn-advanced-filters.js"></script>
    <script src="/js/rxn-crud-search.js"></script>
    <script src="/js/rxn-confirm-modal.js"></script>
    <script src="/js/rxn-row-links.js"></script>
    <script src="/js/rxn-shortcuts.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var syncBase = '/mi-empresa/crm/rxn-sync';

    function confirmarAccion(mensaje, tipo, cbOk) {
        if (typeof window.rxnConfirm === 'function') {
            window.rxnConfirm({ message: mensaje, type: tipo || 'warning',
                title: 'Confirmar Sync', okText: 'Confirmar',
                okClass: 'btn-' + (tipo || 'warning'), onConfirm: cbOk });
        } else { if (confirm(mensaje)) cbOk(); }
    }

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

    function doSync(endpoint, id, entidad, btn, resetIcon, base) {
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
                }
                window.rxnAlert(msg, d.success ? 'success' : 'danger',
                    d.success ? endpoint.toUpperCase() + ' OK' : 'Error');
                if (!d.success) btn.disabled = false;
            }).catch(function(e) {
                window.rxnAlert(e.message || 'Error de red', 'danger', 'Error');
                btn.disabled = false;
            });
    }

    document.querySelectorAll('.btn-push-tango-row').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var self = this;
            var id = self.dataset.id, nombre = self.dataset.nombre, entidad = self.dataset.entidad, base = self.dataset.base;
            confirmarAccion('¿Sincronizar "' + nombre + '" hacia Tango?', 'warning', function() {
                doSync('push', id, entidad, self, 'bi bi-cloud-upload', base);
            });
        });
    });

    document.querySelectorAll('.btn-pull-tango-row').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var self = this;
            var id = self.dataset.id, nombre = self.dataset.nombre, entidad = self.dataset.entidad;
            confirmarAccion('¿Traer datos de "' + nombre + '" desde Tango? Los datos locales se actualizarán.', 'info', function() {
                doSync('pull', id, entidad, self, 'bi bi-cloud-download');
            });
        });
    });

    document.querySelectorAll('.btn-payload-info').forEach(function(btn) {
        btn.addEventListener('click', function() {
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
    });
});
</script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>

