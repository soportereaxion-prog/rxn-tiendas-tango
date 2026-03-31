<!DOCTYPE html>
<html lang="es" <?= \App\Core\Helpers\UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos Web - rxnTiendasIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="/rxnTiendasIA/public/css/rxn-theming.css" rel="stylesheet">
</head>
<body class="rxn-page-shell">
    <?php
    $field = $field ?? 'all';
    $buildQuery = function (array $overrides = []) use ($search, $field, $estado, $limit, $sort, $dir, $page) {
        $params = [
            'search' => $search,
            'field' => $field,
            'estado' => $estado,
            'limit' => $limit,
            'sort' => $sort,
            'dir' => $dir,
            'page' => $page,
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
    <div class="container mt-5 rxn-responsive-container">
        <div class="rxn-module-header mb-4">
            <div>
                <h2>GVA21 — Pedidos Web</h2>
                <p class="text-muted">Monitor de Integración de Pedidos hacia Tango Connect.</p>
            </div>
            <div class="rxn-module-actions">
                <?php require BASE_PATH . '/app/shared/views/components/user_action_menu.php'; ?>
                <a href="/rxnTiendasIA/public/mi-empresa/ayuda" class="btn btn-outline-info" target="_blank" rel="noopener noreferrer"><i class="bi bi-question-circle"></i> Ayuda</a>
                <a href="/rxnTiendasIA/public/mi-empresa/dashboard" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver al Panel</a>
            </div>
        </div>

        <?php
        $moduleNotesKey = 'pedidos_web';
        $moduleNotesLabel = 'Pedidos Web';
        require BASE_PATH . '/app/shared/views/components/module_notes_panel.php';
        ?>

        <?php $flash = \App\Core\Flash::get(); ?>
        <?php if ($flash): ?>
            <div class="rxn-flash-banner rxn-flash-banner-<?= htmlspecialchars((string)$flash['type']) ?> shadow-sm" role="alert">
                <div class="rxn-flash-icon"><i class="bi <?= $flash['type'] === 'success' ? 'bi-check-circle-fill' : ($flash['type'] === 'warning' ? 'bi-exclamation-triangle-fill' : ($flash['type'] === 'danger' ? 'bi-x-circle-fill' : 'bi-info-circle-fill')) ?>"></i></div>
                <div class="flex-grow-1">
                    <div class="fw-bold mb-1"><?= ucfirst((string)$flash['type']) ?></div>
                    <div><?= htmlspecialchars((string)$flash['message']) ?></div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card rxn-crud-card">
            <div class="card-body p-4">
                <div class="rxn-toolbar-split mb-4">
                    <span class="badge bg-dark text-light fs-6 py-2 px-3">📦 Total Pedidos: <?= $totalItems ?></span>
                    <div class="d-flex flex-wrap gap-2">
                        <form action="/rxnTiendasIA/public/mi-empresa/pedidos/reprocesar-pendientes" method="POST">
                            <button type="submit" class="btn btn-outline-success btn-sm" data-rxn-confirm="¿Reenviar todos los pedidos pendientes a Tango?" data-confirm-type="warning">↻ Enviar Pendientes</button>
                        </form>
                        <form action="/rxnTiendasIA/public/mi-empresa/pedidos/reprocesar-seleccionados" method="POST" id="bulk-reprocess-form">
                            <button type="submit" class="btn btn-success btn-sm" id="bulk-reprocess-button" data-rxn-confirm="¿Reenviar los pedidos seleccionados a Tango?" data-confirm-type="warning" disabled>↻ Enviar Seleccionados</button>
                        </form>
                    </div>
                </div>
                
                <div class="rxn-toolbar-split mb-3">
                    <div class="small text-muted">Marca uno o varios pedidos para reenviarlos en lote. También puedes enviar solo los pendientes.</div>
                    <form action="/rxnTiendasIA/public/mi-empresa/pedidos" method="GET" class="rxn-filter-form justify-content-end flex-md-nowrap ms-md-auto" style="width: 860px; max-width: 100%;" data-search-form>
                        <input type="hidden" name="search" value="<?= htmlspecialchars((string) $search) ?>" data-search-hidden>
                        <select name="estado" class="form-select form-select-sm border-secondary rxn-filter-compact" style="width: 150px;" onchange="this.form.submit()">
                            <option value="">Todos los Estados</option>
                            <option value="pendiente_envio_tango" <?= $estado === 'pendiente_envio_tango' ? 'selected' : '' ?>>Pendientes</option>
                            <option value="enviado_tango" <?= $estado === 'enviado_tango' ? 'selected' : '' ?>>Enviados Ok</option>
                            <option value="error_envio_tango" <?= $estado === 'error_envio_tango' ? 'selected' : '' ?>>Con Error</option>
                        </select>
                        <select name="limit" class="form-select form-select-sm border-secondary rxn-filter-compact" style="width: 80px;" onchange="this.form.submit()">
                            <option value="25" <?= $limit === 25 ? 'selected' : '' ?>>25</option>
                            <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100</option>
                        </select>
                        <select name="field" class="form-select form-select-sm border-secondary rxn-filter-compact rxn-search-field-wrap" style="width: 150px;" data-search-field>
                            <option value="all" <?= $field === 'all' ? 'selected' : '' ?>>Todos los campos</option>
                            <option value="id" <?= $field === 'id' ? 'selected' : '' ?>>Pedido</option>
                            <option value="cliente" <?= $field === 'cliente' ? 'selected' : '' ?>>Cliente</option>
                            <option value="email" <?= $field === 'email' ? 'selected' : '' ?>>Email</option>
                            <option value="estado" <?= $field === 'estado' ? 'selected' : '' ?>>Estado</option>
                        </select>
                        <div class="rxn-search-input-wrap rxn-filter-grow" style="width: 250px;">
                            <input type="text" class="form-control form-control-sm border-secondary" placeholder="🔎 Buscar pedido, cliente, email..." value="<?= htmlspecialchars((string)$search) ?>" data-search-input data-suggestions-url="/rxnTiendasIA/public/mi-empresa/pedidos/sugerencias" autocomplete="off">
                            <div class="rxn-search-suggestions d-none" data-search-suggestions></div>
                        </div>
                        <button type="submit" class="btn btn-secondary btn-sm text-white">Buscar</button>
                        <?php if($search || $estado): ?>
                            <a href="/rxnTiendasIA/public/mi-empresa/pedidos" class="btn btn-light btn-sm border">Limpiar</a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="form-text rxn-search-help text-md-end">Se sugieren coincidencias mientras escribis, pero el listado solo se filtra al buscar o presionar Enter.</div>

                <div class="table-responsive rxn-table-responsive">
                    <table class="table table-hover align-middle table-sm rxn-crud-table" style="font-size: 0.9rem;">
                        <thead class="table-light">
                            <?php
                            $sortLink = function(string $fieldName, string $label) use ($buildQuery, $sort, $dir) {
                                $newDir = ($sort === $fieldName && $dir === 'ASC') ? 'DESC' : 'ASC';
                                $icon = ($sort === $fieldName) ? ($dir === 'ASC' ? '▲' : '▼') : '';
                                $href = '?' . $buildQuery(['sort' => $fieldName, 'dir' => $newDir]);
                                return "<a href=\"{$href}\" class=\"rxn-sort-link\"><span>{$label}</span><span class=\"rxn-sort-indicator\">{$icon}</span></a>";
                            };
                            ?>
                            <tr>
                                <th class="text-center" style="width: 42px;">
                                    <input type="checkbox" class="form-check-input" id="check-all-pedidos" data-check-all>
                                </th>
                                <th><?= $sortLink('p.id', '# Orden') ?></th>
                                <th><?= $sortLink('p.created_at', 'Fecha') ?></th>
                                <th><?= $sortLink('cliente_nombre', 'Cliente') ?></th>
                                <th>Email</th>
                                <th>Cód. Tango Asignado</th>
                                <th class="text-nowrap"><?= $sortLink('p.total', 'Total ($)') ?></th>
                                <th>Estado Integración</th>
                                <th class="rxn-row-chevron-col"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($pedidos)): ?>
                                <tr>
                                    <td colspan="9" class="rxn-empty-state text-muted">
                                        <div class="mb-2 fs-3">🛒</div>
                                        Aún no hay pedidos registrados o no hay coincidencias con tu búsqueda.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($pedidos as $p): ?>
                                    <tr data-row-link="/rxnTiendasIA/public/mi-empresa/pedidos/<?= $p['id'] ?>">
                                        <td class="text-center" data-row-link-ignore>
                                            <input type="checkbox" name="selected_ids[]" value="<?= (int)$p['id'] ?>" class="form-check-input pedido-checkbox" form="bulk-reprocess-form" data-row-link-ignore>
                                        </td>
                                        <td class="fw-bold text-dark">#<?= $p['id'] ?></td>
                                        <td class="text-nowrap"><small class="text-muted"><?= htmlspecialchars((string)$p['created_at']) ?></small></td>
                                        <td class="text-wrap" style="max-width: 200px;">
                                            <?= htmlspecialchars((string)trim($p['cliente_nombre'] . ' ' . $p['cliente_apellido'])) ?>
                                        </td>
                                        <td><small><?= htmlspecialchars((string)$p['cliente_email']) ?></small></td>
                                        <td>
                                            <span class="badge bg-light text-dark border font-monospace">
                                                <?= htmlspecialchars((string)($p['codigo_cliente_tango_usado'] ?: 'No Definido')) ?>
                                            </span>
                                        </td>
                                        <td class="fw-semibold text-success text-nowrap">$<?= number_format((float)$p['total'], 2, ',', '.') ?></td>
                                        <td>
                                            <?php if($p['estado_tango'] === 'enviado_tango'): ?>
                                                <span class="badge bg-success bg-opacity-75">Enviado Ok</span>
                                            <?php elseif($p['estado_tango'] === 'error_envio_tango'): ?>
                                                <span class="badge bg-danger bg-opacity-75" title="<?= htmlspecialchars((string)$p['mensaje_error']) ?>">Error Integración</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning bg-opacity-75 text-dark">Pendiente</span>
                                            <?php endif; ?>
                                            <div class="mt-1">
                                                <span class="badge <?= ((int)($p['intentos_envio_tango'] ?? 0) > 0 ? ($p['estado_tango'] === 'enviado_tango' ? 'bg-success' : ($p['estado_tango'] === 'error_envio_tango' ? 'bg-danger' : 'bg-secondary')) : 'bg-secondary') ?> bg-opacity-75">
                                                    Envíos: <?= (int)($p['intentos_envio_tango'] ?? 0) ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="rxn-row-chevron-col">
                                            <a href="/rxnTiendasIA/public/mi-empresa/pedidos/<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2 fw-medium rxn-row-link-action rxn-row-chevron" title="Abrir pedido" aria-label="Abrir pedido" data-row-link-ignore>›</a>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/rxnTiendasIA/public/js/rxn-crud-search.js"></script>
    <script src="/rxnTiendasIA/public/js/rxn-row-links.js"></script>
    <script src="/rxnTiendasIA/public/js/rxn-confirm-modal.js"></script>
    <script>
        (function () {
            var checkAll = document.querySelector('[data-check-all]');
            var checks = Array.prototype.slice.call(document.querySelectorAll('.pedido-checkbox'));
            var bulkButton = document.getElementById('bulk-reprocess-button');

            if (!checkAll || !checks.length) {
                return;
            }

            function syncBulkState() {
                var selectedCount = checks.filter(function (checkbox) {
                    return checkbox.checked;
                }).length;

                if (bulkButton) {
                    bulkButton.disabled = selectedCount === 0;
                }

                checkAll.checked = selectedCount > 0 && selectedCount === checks.length;
                checkAll.indeterminate = selectedCount > 0 && selectedCount < checks.length;
            }

            checkAll.addEventListener('change', function () {
                checks.forEach(function (checkbox) {
                    checkbox.checked = checkAll.checked;
                });
                syncBulkState();
            });

            checks.forEach(function (checkbox) {
                checkbox.addEventListener('change', syncBulkState);
            });

            syncBulkState();
        }());
    </script>
</body>
</html>
