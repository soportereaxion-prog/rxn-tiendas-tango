<?php
$pageTitle = 'RXN Suite';
ob_start();
?>
<?php
    $field = $field ?? 'all';
    $categoriaId = $categoriaId ?? null;
    $basePath = $basePath ?? '/mi-empresa/articulos';
    $dashboardPath = $dashboardPath ?? '/mi-empresa/dashboard';
    $helpPath = $helpPath ?? '/mi-empresa/ayuda?area=tiendas';
    $moduleNotesKey = $moduleNotesKey ?? 'articulos';
    $moduleNotesLabel = $moduleNotesLabel ?? 'Articulos';
    $showCategories = $showCategories ?? true;
    $showSyncActions = $showSyncActions ?? true;
    $syncTodoPath = $syncTodoPath ?? '/mi-empresa/sync/todo';
    $syncStockPath = $syncStockPath ?? '/mi-empresa/sync/stock';
    $syncPreciosPath = $syncPreciosPath ?? '/mi-empresa/sync/precios';
    $syncArticulosPath = $syncArticulosPath ?? '/mi-empresa/sync/articulos';
    $totalBadgeLabel = $totalBadgeLabel ?? 'Total en BD Local';
    $emptyStateTitle = $emptyStateTitle ?? 'No hay articulos cargados.';
    $emptyStateHint = $emptyStateHint ?? '';

    $buildQuery = function (array $overrides = []) use ($search, $field, $categoriaId, $limit, $sort, $dir, $page) {
        $params = [
            'search' => $search,
            'field' => $field,
            'categoria_id' => $categoriaId,
            'limit' => $limit,
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
                <h2><?= htmlspecialchars((string) ($headerTitle ?? 'Directorio de Articulos')) ?></h2>
                <p class="text-muted"><?= htmlspecialchars((string) ($headerDescription ?? 'Gestion de articulos.')) ?></p>
            </div>
            <div class="rxn-module-actions">

                <a href="<?= htmlspecialchars($helpPath) ?>" class="btn btn-outline-info btn-sm" target="_blank" rel="noopener noreferrer" title="Ayuda"><i class="bi bi-question-circle"></i> Ayuda</a>
                <a href="<?= htmlspecialchars($dashboardPath) ?>" class="btn btn-outline-secondary btn-sm" title="Volver al Panel"><i class="bi bi-arrow-left"></i> Volver</a>
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
                        <?php if (isset($flash['stats']['sin_match'])): ?>
                            <li>Sin match local: <b class="text-warning"><?= (int) ($flash['stats']['sin_match'] ?? 0) ?></b></li>
                        <?php endif; ?>
                    </ul>
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php 
        $status = $status ?? 'activos';
        $isPapelera = $status === 'papelera';
        ?>

        <ul class="nav nav-tabs mb-4 border-secondary border-opacity-25" style="border-bottom-width: 2px;">
            <li class="nav-item">
                <a class="nav-link <?= !$isPapelera ? 'active fw-bold bg-dark text-light border-secondary border-bottom-0' : 'text-muted border-0 hover-text-light' ?>" href="<?= htmlspecialchars($basePath) ?>?<?= htmlspecialchars($buildQuery(['status' => 'activos', 'page' => 1])) ?>">
                    Activos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-danger <?= $isPapelera ? 'active fw-bold bg-dark border-secondary border-bottom-0' : 'border-0 hover-text-danger' ?>" href="<?= htmlspecialchars($basePath) ?>?<?= htmlspecialchars($buildQuery(['status' => 'papelera', 'page' => 1])) ?>">
                    <i class="bi bi-trash"></i> Papelera
                </a>
            </li>
        </ul>

        <div class="card rxn-crud-card">
            <div class="card-body p-4">
                <div class="rxn-toolbar-split mb-4">
                    <span class="badge bg-primary text-light fs-6 py-2 px-3"><?= htmlspecialchars((string) $totalBadgeLabel) ?>: <?= (int) $totalItems ?></span>
                    <div class="rxn-toolbar-actions">
                        <?php if ($showSyncActions): ?>
                            <a href="<?= htmlspecialchars((string) (strpos($basePath, '/crm/') !== false ? '/mi-empresa/crm/rxn-sync' : '/mi-empresa/rxn-sync')) ?>" class="btn btn-warning btn-sm fw-bold shadow-sm text-dark border-0" style="background: linear-gradient(135deg, #f7b733 0%, #fc4a1a 100%);">
                                <i class="fas fa-sync me-1"></i> Auditoría RXN Sync
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!$showSyncActions): ?>
                    <div class="alert alert-secondary border-0 rounded-4 shadow-sm mb-4">
                        Este listado usa tablas propias de CRM. La carga automatica todavia no fue definida, pero el circuito queda aislado del catalogo de Tiendas.
                    </div>
                <?php endif; ?>

                <div class="rxn-toolbar-split mb-3">
                    <?php if (!$isPapelera): ?>
                    <button type="submit" form="hiddenFormBulk" formaction="<?= htmlspecialchars($basePath) ?>/eliminar-masivo" class="btn btn-outline-danger btn-sm rxn-confirm-form" data-msg="¿Confirma enviar los elementos seleccionados a la papelera?">Eliminar Seleccionados</button>
                    <?php else: ?>
                    <div class="d-flex gap-2">
                        <button type="submit" form="hiddenFormBulk" formaction="<?= htmlspecialchars($basePath) ?>/restore-masivo" class="btn btn-outline-success btn-sm rxn-confirm-form" data-msg="¿Restaurar los elementos seleccionados?">Restaurar Seleccionados</button>
                        <button type="submit" form="hiddenFormBulk" formaction="<?= htmlspecialchars($basePath) ?>/force-delete-masivo" class="btn btn-outline-danger btn-sm rxn-confirm-form" data-msg="⚠️ ATENCIÓN: ¿Destruir definitivamente los elementos seleccionados?">Destruir Seleccionados</button>
                    </div>
                    <?php endif; ?>

                    <form action="<?= htmlspecialchars($basePath) ?>" method="GET" class="rxn-filter-form justify-content-end flex-md-nowrap ms-md-auto" style="width: 860px; max-width: 100%;" data-search-form>
                        <input type="hidden" name="status" value="<?= htmlspecialchars((string) $status) ?>">
                        <input type="hidden" name="search" value="<?= htmlspecialchars((string) $search) ?>" data-search-hidden>
                        <select name="limit" class="form-select form-select-sm border-info rxn-filter-compact" style="width: 80px;" onchange="this.form.submit()">
                            <option value="25" <?= $limit === 25 ? 'selected' : '' ?>>25</option>
                            <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100</option>
                        </select>
                        <select name="field" class="form-select form-select-sm border-info rxn-filter-compact rxn-search-field-wrap" style="width: 150px;" data-search-field>
                            <option value="all" <?= $field === 'all' ? 'selected' : '' ?>>Todos los campos</option>
                            <option value="id" <?= $field === 'id' ? 'selected' : '' ?>>ID</option>
                            <option value="codigo_externo" <?= $field === 'codigo_externo' ? 'selected' : '' ?>>SKU</option>
                            <option value="nombre" <?= $field === 'nombre' ? 'selected' : '' ?>>Nombre</option>
                            <option value="descripcion" <?= $field === 'descripcion' ? 'selected' : '' ?>>Descripcion</option>
                        </select>
                        <?php if ($showCategories): ?>
                            <select name="categoria_id" class="form-select form-select-sm border-info rxn-filter-compact" style="width: 180px;" onchange="this.form.submit()">
                                <option value="">Todas las categorias</option>
                                <?php foreach (($categorias ?? []) as $categoria): ?>
                                    <option value="<?= (int) $categoria->id ?>" <?= (int) ($categoriaId ?? 0) === (int) $categoria->id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string) $categoria->nombre) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        <div class="rxn-search-input-wrap rxn-filter-grow">
                            <input type="text" class="form-control form-control-sm border-info" placeholder='🔎 Presioná F3 o "/" para buscar' value="<?= htmlspecialchars((string) $search) ?>" data-search-input data-suggestions-url="<?= htmlspecialchars($basePath) ?>/sugerencias" autocomplete="off">
                            <div class="rxn-search-suggestions d-none" data-search-suggestions></div>
                        </div>
                        <button type="submit" class="btn btn-info btn-sm text-white">Buscar</button>
                    </form>
                </div>
                <div class="form-text rxn-search-help text-md-end">Se sugieren coincidencias mientras escribis, pero el listado solo se filtra al buscar o presionar Enter.</div>

                <form method="POST" id="hiddenFormBulk">
                    <div class="table-responsive rxn-table-responsive">
                        <table class="table table-hover align-middle table-sm rxn-crud-table" style="font-size: 0.9rem;">
                            <thead class="table-light">
                                <?php
                                $sortLink = function (string $fieldName, string $label) use ($buildQuery, $sort, $dir) {
                                    $newDir = ($sort === $fieldName && $dir === 'ASC') ? 'DESC' : 'ASC';
                                    $icon = ($sort === $fieldName) ? ($dir === 'ASC' ? '▲' : '▼') : '';
                                    $href = '?' . $buildQuery(['sort' => $fieldName, 'dir' => $newDir]);
                                    return "<a href=\"{$href}\" class=\"rxn-sort-link\"><span>{$label}</span><span class=\"rxn-sort-indicator\">{$icon}</span></a>";
                                };
                                ?>
                                <tr>
                                    <th style="width: 40px;"><input type="checkbox" class="form-check-input" id="check-all" onclick="document.querySelectorAll('.check-item').forEach(e => e.checked = this.checked);"></th>
                                    <th class="rxn-filter-col rxn-hide-mobile" data-filter-field="codigo_externo"><?= $sortLink('codigo_externo', 'Codigo / SKU') ?></th>
                                    <th class="rxn-filter-col" data-filter-field="nombre"><?= $sortLink('nombre', 'Descripcion') ?></th>
                                    <?php if ($showCategories): ?><th class="rxn-filter-col rxn-hide-mobile" data-filter-field="categoria_nombre"><?= $sortLink('categoria_nombre', 'Categoria') ?></th><?php endif; ?>
                                    <th class="rxn-hide-mobile">Descripcion Adicional</th>
                                    <th class="text-nowrap rxn-filter-col rxn-hide-mobile" data-filter-field="precio_lista_1"><?= $sortLink('precio_lista_1', 'P. L1 ($)') ?></th>
                                    <th class="text-nowrap rxn-filter-col rxn-hide-mobile" data-filter-field="precio_lista_2"><?= $sortLink('precio_lista_2', 'P. L2 ($)') ?></th>
                                    <th class="rxn-filter-col" data-filter-field="stock_actual"><?= $sortLink('stock_actual', 'Stock') ?></th>
                                    <th class="rxn-filter-col" data-filter-field="activo"><?= $sortLink('activo', 'Estado') ?></th>
                                    <th class="rxn-filter-col rxn-hide-mobile" data-filter-field="fecha_ultima_sync"><?= $sortLink('fecha_ultima_sync', 'Ultima Sincro') ?></th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($articulos)): ?>
                                    <tr>
                                        <td colspan="<?= $showCategories ? '11' : '10' ?>" class="rxn-empty-state text-muted">
                                            <div class="mb-2">-</div>
                                            <?= htmlspecialchars((string) $emptyStateTitle) ?><br>
                                            <?php if ($emptyStateHint !== ''): ?><small><?= htmlspecialchars((string) $emptyStateHint) ?></small><?php endif; ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($articulos as $art): ?>
                                        <tr data-row-link="<?= htmlspecialchars($basePath) ?>/editar?id=<?= (int) $art['id'] ?>">
                                            <td><input type="checkbox" name="ids[]" form="hiddenFormBulk" value="<?= (int) $art['id'] ?>" class="form-check-input check-item" data-row-link-ignore></td>
                                            <td class="text-nowrap rxn-hide-mobile"><span class="badge bg-secondary text-start" style="white-space: pre; font-family: monospace; font-size: 0.85rem;"><?= htmlspecialchars((string) $art['codigo_externo']) ?></span></td>
                                            <td class="fw-bold text-dark text-wrap" style="max-width: 250px;"><?= htmlspecialchars((string) $art['nombre']) ?></td>
                                            <?php if ($showCategories): ?>
                                                <td class="rxn-hide-mobile">
                                                    <?php if (!empty($art['categoria_nombre'])): ?>
                                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars((string) $art['categoria_nombre']) ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted small">Sin categoria</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
                                            <td class="text-wrap rxn-hide-mobile" style="max-width: 200px;"><small class="text-muted"><?= htmlspecialchars((string) ($art['descripcion'] ?? '---')) ?></small></td>
                                            <td class="fw-semibold text-primary text-nowrap rxn-hide-mobile">$<?= $art['precio_lista_1'] !== null ? number_format((float) $art['precio_lista_1'], 2, ',', '.') : '--' ?></td>
                                            <td class="fw-semibold text-success text-nowrap rxn-hide-mobile">$<?= $art['precio_lista_2'] !== null ? number_format((float) $art['precio_lista_2'], 2, ',', '.') : '--' ?></td>
                                            <td class="fw-bold text-nowrap"><?= $art['stock_actual'] !== null ? (float) $art['stock_actual'] : '--' ?></td>
                                            <td>
                                                <?php if ($art['activo']): ?>
                                                    <span class="badge bg-success bg-opacity-75">Activo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger bg-opacity-75">Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-nowrap rxn-hide-mobile"><small class="text-secondary"><?= htmlspecialchars((string) $art['fecha_ultima_sync']) ?></small></td>
                                            <td class="text-end text-nowrap">
                                                <div class="btn-group" data-row-link-ignore>
                                                        <?php if (!$isPapelera): ?>
                                                        <a href="<?= htmlspecialchars($basePath) ?>/editar?id=<?= (int) $art['id'] ?>" class="btn btn-sm btn-outline-info" title="Editar"><i class="bi bi-pencil"></i></a>
                                                        
                                                        <?php if (($showSyncActions ?? false) && !empty($art['codigo_externo'])): ?>
                                                        <button type="button"
                                                            class="btn btn-sm btn-outline-warning btn-push-tango-row"
                                                            title="Push → Tango"
                                                            data-id="<?= (int) $art['id'] ?>"
                                                            data-base="<?= htmlspecialchars($basePath) ?>"
                                                            data-nombre="<?= htmlspecialchars((string)$art['nombre']) ?>">
                                                            <i class="bi bi-cloud-upload"></i>
                                                        </button>
                                                        <button type="button"
                                                            class="btn btn-sm btn-outline-info btn-pull-tango-row"
                                                            title="Pull ← Tango"
                                                            data-id="<?= (int) $art['id'] ?>"
                                                            data-entidad="articulo"
                                                            data-base="<?= htmlspecialchars($basePath) ?>"
                                                            data-nombre="<?= htmlspecialchars((string)$art['nombre']) ?>">
                                                            <i class="bi bi-cloud-download"></i>
                                                        </button>
                                                        <button type="button"
                                                            class="btn btn-sm btn-outline-secondary btn-payload-info"
                                                            title="Ver último payload Tango"
                                                            data-id="<?= (int) $art['id'] ?>"
                                                            data-entidad="articulo">
                                                            <i class="bi bi-info-circle"></i>
                                                        </button>
                                                        <?php endif; ?>

                                                        <form action="<?= htmlspecialchars($basePath) ?>/<?= (int) $art['id'] ?>/eliminar" method="POST" class="d-inline m-0 p-0 rxn-confirm-form" data-msg="¿Enviar artículo a la papelera?">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" style="border-top-left-radius: 0; border-bottom-left-radius: 0;" title="Eliminar (Papelera)">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>

                                                    <?php else: ?>
                                                        <form action="<?= htmlspecialchars($basePath) ?>/<?= (int) $art['id'] ?>/restore" method="POST" class="d-inline m-0 p-0 rxn-confirm-form" data-msg="¿Confirma restaurar este artículo?">
                                                            <button type="submit" class="btn btn-sm btn-outline-success border-end-0" title="Restaurar Artículo">
                                                                <i class="bi bi-arrow-counterclockwise"></i> Restaurar
                                                            </button>
                                                        </form>

                                                        <form action="<?= htmlspecialchars($basePath) ?>/force-delete?id=<?= (int) $art['id'] ?>" method="POST" class="d-inline m-0 p-0 rxn-confirm-form" data-msg="⚠️ ATENCIÓN: Esta acción es irreversible. ¿Eliminar definitivamente?">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" style="border-top-left-radius: 0; border-bottom-left-radius: 0;" title="Destruir">
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
                </form>

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
<?php if (($showSyncActions ?? false)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Resuelve la ruta de sync según el contexto (CRM vs Tiendas)
    var isCrm    = window.location.pathname.includes('/crm/');
    var syncBase = isCrm ? '/mi-empresa/crm/rxn-sync' : '/mi-empresa/rxn-sync';

    // ── Helper de confirmación ────────────────────────────────────────
    function confirmarAccion(mensaje, tipo, cbOk) {
        if (typeof window.rxnConfirm === 'function') {
            window.rxnConfirm({ message: mensaje, type: tipo || 'warning',
                title: 'Confirmar Sync', okText: 'Confirmar', okClass: 'btn-warning',
                onConfirm: cbOk });
        } else {
            if (confirm(mensaje)) cbOk();
        }
    }

    // ── Helpers de payload formateado ─────────────────────────────────
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

    // ── Push Individual ──────────────────────────────────────────────
    document.querySelectorAll('.btn-push-tango-row').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id     = this.getAttribute('data-id');
            var nombre = this.getAttribute('data-nombre');
            var base   = this.getAttribute('data-base');
            var self   = this;

            confirmarAccion('¿Sincronizar "' + nombre + '" hacia Tango?', 'warning', function () {
                self.disabled = true;

                fetchJson(base + '/' + id + '/push-tango', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function (data) {
                    var msg = data.message || '';
                    if (data.success && data.payload) {
                        msg += '<br>' + syncResultHtml('push', data.payload);
                    }
                    window.rxnAlert(msg, data.success ? 'success' : 'danger',
                        data.success ? 'Push OK' : 'Error de Push');
                    if (!data.success) self.disabled = false;
                })
                .catch(function (err) {
                    window.rxnAlert(err.message || 'Error de red', 'danger', 'Error de Push');
                    self.disabled = false;
                });
            });
        });
    });

    // ── Pull Individual ──────────────────────────────────────────────
    document.querySelectorAll('.btn-pull-tango-row').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id     = this.getAttribute('data-id');
            var nombre = this.getAttribute('data-nombre');
            var self   = this;

            confirmarAccion('¿Traer datos de "' + nombre + '" desde Tango? Sobreescribirá los datos locales.', 'info', function () {
                self.disabled = true;

                var form = new FormData();
                form.append('id', id);
                form.append('entidad', 'articulo');

                fetchJson(syncBase + '/pull', {
                    method: 'POST',
                    body: form,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function (data) {
                    var msg = data.message || '';
                    if (data.success && data.payload) {
                        msg += '<br>' + syncResultHtml('pull', data.payload);
                    }
                    window.rxnAlert(msg, data.success ? 'success' : 'danger',
                        data.success ? 'Pull OK' : 'Error de Pull');
                    if (!data.success) self.disabled = false;
                })
                .catch(function (err) {
                    window.rxnAlert(err.message || 'Error de red', 'danger', 'Error de Pull');
                    self.disabled = false;
                });
            });
        });
    });

    // ── Botón Info (último payload guardado) ─────────────────────────
    document.querySelectorAll('.btn-payload-info').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id      = this.getAttribute('data-id');
            var entidad = this.getAttribute('data-entidad');

            fetchJson(syncBase + '/payload?entidad=' + entidad + '&id=' + id, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (data) {
                if (!data.success) {
                    window.rxnAlert(data.message, 'warning', 'Sin historial');
                    return;
                }
                var html = payloadHtml(data.meta, data.snapshot);
                window.rxnAlert(html, 'info', 'Historial Tango — ID #' + (data.meta.tango_id || '?'));
            })
            .catch(function (err) {
                window.rxnAlert(err.message || 'Error de red', 'danger', 'Error');
            });
        });
    });
});
</script>
<?php endif; ?>


<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
