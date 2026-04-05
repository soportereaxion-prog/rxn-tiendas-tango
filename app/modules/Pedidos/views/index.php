<?php
$pageTitle = 'RXN Suite';
ob_start();
?>
<?php
    $field = $field ?? 'all';
    $buildQuery = function (array $overrides = []) use ($search, $field, $estado, $limit, $sort, $dir, $status, $page) {
        $params = [
            'search' => $search,
            'field' => $field,
            'estado' => $estado,
            'limit' => $limit,
            'sort' => $sort,
            'dir' => $dir,
            'status' => $status,
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
    <div class="container-fluid mt-2 rxn-responsive-container">
        <div class="rxn-module-header mb-4">
            <div>
                <h2>GVA21 — Pedidos Web</h2>
                <p class="text-muted">Monitor de Integración de Pedidos hacia Tango Connect.</p>
            </div>
            <div class="rxn-module-actions">

                <a href="/mi-empresa/ayuda" class="btn btn-outline-info" target="_blank" rel="noopener noreferrer"><i class="bi bi-question-circle"></i> Ayuda</a>
                <a href="/mi-empresa/dashboard" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver al Panel</a>
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

        <?php $isPapelera = ($status ?? 'activos') === 'papelera'; ?>
        
        <ul class="nav nav-tabs mb-4 rxn-crud-tabs">
            <li class="nav-item">
                <a class="nav-link <?= !$isPapelera ? 'active fw-bold' : '' ?>" href="<?= htmlspecialchars((string) ($ui['basePath'] ?? '/mi-empresa/pedidos')) ?>?<?= htmlspecialchars($buildQuery(['status' => 'activos', 'page' => 1])) ?>">
                    Activos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-danger <?= $isPapelera ? 'active fw-bold' : '' ?>" href="<?= htmlspecialchars((string) ($ui['basePath'] ?? '/mi-empresa/pedidos')) ?>?<?= htmlspecialchars($buildQuery(['status' => 'papelera', 'page' => 1])) ?>">
                    <i class="bi bi-trash"></i> Papelera
                </a>
            </li>
        </ul>

        <div class="card rxn-crud-card">
            <div class="card-body p-4">
                <div class="rxn-toolbar-split mb-3">
                    <span class="badge bg-dark text-light fs-6 py-2 px-3">📦 Total Pedidos: <?= $totalItems ?></span>
                    <form action="/mi-empresa/pedidos" method="GET" class="rxn-filter-form justify-content-end flex-md-nowrap ms-md-auto" style="width: 860px; max-width: 100%;" data-search-form>
                        <input type="hidden" name="search" value="<?= htmlspecialchars((string) $search) ?>" data-search-hidden>
                        <input type="hidden" name="status" value="<?= htmlspecialchars((string) $status) ?>">
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
                            <input type="text" class="form-control form-control-sm border-secondary" placeholder='🔎 Presioná F3 o "/" para buscar' value="<?= htmlspecialchars((string)$search) ?>" data-search-input data-suggestions-url="/mi-empresa/pedidos/sugerencias" autocomplete="off">
                            <div class="rxn-search-suggestions d-none" data-search-suggestions></div>
                        </div>
                        <button type="submit" class="btn btn-secondary btn-sm text-white">Buscar</button>
                        <?php if($search || $estado): ?>
                            <a href="/mi-empresa/pedidos" class="btn btn-light btn-sm border">Limpiar</a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="form-text rxn-search-help text-md-end mb-3">Se sugieren coincidencias mientras escribis, pero el listado solo se filtra al buscar o presionar Enter.</div>

                <form id="hiddenFormBulk" method="POST">
                <?php if (!$isPapelera): ?>
                <div class="mb-3 d-flex gap-2">
                    <button type="submit" formaction="/mi-empresa/pedidos/eliminar-masivo" class="btn btn-outline-danger btn-sm" data-rxn-confirm="¿Enviar los pedidos seleccionados a la papelera?"><i class="bi bi-trash"></i> Eliminar Seleccionados</button>
                    <button type="submit" formaction="/mi-empresa/pedidos/reprocesar-seleccionados" class="btn btn-success btn-sm" data-rxn-confirm="¿Reenviar los pedidos seleccionados a Tango?" data-confirm-type="warning" id="bulk-reprocess-button" disabled>↻ Enviar Seleccionados</button>
                    <button type="submit" formaction="/mi-empresa/pedidos/reprocesar-pendientes" class="btn btn-outline-success btn-sm" data-rxn-confirm="¿Reenviar todos los pedidos pendientes a Tango?" data-confirm-type="warning">↻ Enviar Pendientes</button>
                </div>
                <?php else: ?>
                <div class="mb-3 d-flex gap-2">
                    <button type="submit" formaction="/mi-empresa/pedidos/restore-masivo" class="btn btn-outline-success btn-sm" data-rxn-confirm="¿Restaurar los pedidos seleccionados?"><i class="bi bi-arrow-counterclockwise"></i> Restaurar Seleccionados</button>
                    <button type="submit" formaction="/mi-empresa/pedidos/force-delete-masivo" class="btn btn-outline-danger btn-sm" data-rxn-confirm="⚠️ ATENCIÓN: Acción irreversible. ¿Destruir definitivamente los pedidos seleccionados?"><i class="bi bi-x-circle"></i> Destruir Seleccionados</button>
                </div>
                <?php endif; ?>

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
                                <th style="width: 40px;" class="text-center">
                                    <input type="checkbox" class="form-check-input" id="bulk-select-all" aria-label="Seleccionar todos" onclick="document.querySelectorAll('.rxn-bulk-checkbox').forEach(e => { e.checked = this.checked; e.dispatchEvent(new Event('change')); });">
                                </th>
                                <th><?= $sortLink('p.id', '# Orden') ?></th>
                                <th><?= $sortLink('p.created_at', 'Fecha') ?></th>
                                <th><?= $sortLink('cliente_nombre', 'Cliente') ?></th>
                                <th>Email</th>
                                <th>Cód. Tango Asignado</th>
                                <th class="text-nowrap"><?= $sortLink('p.total', 'Total ($)') ?></th>
                                <th>Estado Integración</th>
                                <th class="rxn-actions-col text-end">Acciones</th>
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
                                    <tr data-row-link="/mi-empresa/pedidos/<?= $p['id'] ?>" class="<?= $isPapelera ? 'rxn-row-deleted' : '' ?>">
                                        <td class="text-center" data-row-link-ignore>
                                            <input class="form-check-input rxn-bulk-checkbox pedido-checkbox" type="checkbox" name="ids[]" value="<?= $p['id'] ?>" form="hiddenFormBulk" aria-label="Seleccionar fila" data-row-link-ignore>
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
                                        <td class="rxn-actions-col text-end" data-row-link-ignore>
                                            <div class="d-inline-flex gap-1 align-items-center">
                                                <a href="/mi-empresa/pedidos/<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary" title="Abrir pedido">
                                                    <i class="bi bi-box-arrow-up-right"></i>
                                                </a>

                                                <?php if (!$isPapelera): ?>
                                                    <form method="POST" action="/mi-empresa/pedidos/<?= $p['id'] ?>/eliminar" class="d-inline">
                                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Mover a Papelera" data-rxn-confirm="¿Mover a la papelera?"><i class="bi bi-trash"></i></button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" action="/mi-empresa/pedidos/<?= $p['id'] ?>/restore" class="d-inline">
                                                        <button type="button" class="btn btn-sm btn-outline-success" title="Restaurar" data-rxn-confirm="¿Restaurar este pedido?"><i class="bi bi-arrow-counterclockwise"></i></button>
                                                    </form>
                                                    <form method="POST" action="/mi-empresa/pedidos/<?= $p['id'] ?>/force-delete" class="d-inline">
                                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Destruir" data-rxn-confirm="¿Destruir definitivamente este pedido? Esta acción no se puede deshacer."><i class="bi bi-x-circle"></i></button>
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
<script src="/js/rxn-crud-search.js"></script>
    <script src="/js/rxn-row-links.js"></script>
    <script src="/js/rxn-confirm-modal.js"></script>
    <script>
        (function () {
            var checks = Array.prototype.slice.call(document.querySelectorAll('.pedido-checkbox'));
            var bulkButton = document.getElementById('bulk-reprocess-button');

            if (!checks.length) {
                return;
            }

            function syncBulkState() {
                var selectedCount = checks.filter(function (checkbox) {
                    return checkbox.checked;
                }).length;

                if (bulkButton) {
                    bulkButton.disabled = selectedCount === 0;
                }
            }

            checks.forEach(function (checkbox) {
                checkbox.addEventListener('change', syncBulkState);
            });

            syncBulkState();
        }());
    </script>
    <script src="/js/rxn-shortcuts.js"></script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
