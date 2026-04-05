<?php
$pageTitle = 'RXN Suite';
ob_start();
?>
<?php
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
    <div class="container-fluid mt-2 rxn-responsive-container">
        <div class="rxn-module-header mb-4">
            <div>
                <h2>CRM - Presupuestos</h2>
                
            </div>
            <div class="rxn-module-actions">

                <a href="<?= htmlspecialchars((string) $helpPath) ?>" class="btn btn-outline-info" target="_blank" rel="noopener noreferrer"><i class="bi bi-question-circle"></i> Ayuda</a>
                <a href="/mi-empresa/crm/formularios-impresion/crm_presupuesto" class="btn btn-outline-dark"><i class="bi bi-easel2"></i> Formulario</a>
                <a href="<?= htmlspecialchars((string) $syncCatalogosPath) ?>" class="btn btn-outline-warning" data-rxn-confirm="¿Sincronizar catalogos comerciales CRM para depositos, condiciones, listas, vendedores y transportes?" data-confirm-type="warning"><i class="bi bi-arrow-repeat"></i> Sync Catalogos</a>
                <a href="/mi-empresa/crm/presupuestos/crear" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Nuevo presupuesto</a>
                <a href="<?= htmlspecialchars((string) $dashboardPath) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver al CRM</a>
            </div>
        </div>

        <?php require BASE_PATH . '/app/shared/views/components/module_notes_panel.php'; ?>

        <?php $flash = \App\Core\Flash::get(); ?>
        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars((string) $flash['type']) ?> alert-dismissible fade show shadow-sm mb-4" role="alert">
                <strong><?= $flash['type'] === 'success' ? 'OK' : 'Atencion' ?></strong> <?= htmlspecialchars((string) $flash['message']) ?>
                <?php if (!empty($flash['stats'])): ?>
                    <ul class="mb-0 mt-2 fs-6">
                        <?php foreach ($flash['stats'] as $type => $stat): ?>
                            <?php if (!is_array($stat)) { continue; } ?>
                            <li><?= htmlspecialchars((string) strtoupper((string) $type)) ?>: <b class="text-primary"><?= (int) ($stat['received'] ?? 0) ?></b> recibidos, <b class="text-success"><?= (int) ($stat['inserted'] ?? 0) ?></b> nuevos, <b class="text-info"><?= (int) ($stat['updated'] ?? 0) ?></b> actualizados.</li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card rxn-crud-card">
            <div class="card-body p-4">
                <div class="rxn-toolbar-split mb-4">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="badge bg-dark text-light fs-6 py-2 px-3"><i class="bi bi-receipt-cutoff"></i> Total: <?= (int) $totalItems ?></span>
                        <span class="badge text-bg-light border py-2 px-3">Cliente autocompleta defaults comerciales y cada presupuesto congela snapshots propios.</span>
                    </div>
                    <div class="small text-muted">La lista de precios del presupuesto se resuelve por circuito CRM y no depende de la logica comercial de Tiendas.</div>
                </div>

                <ul class="nav nav-tabs mb-4 border-secondary border-opacity-25" style="border-bottom-width: 2px;">
                    <li class="nav-item">
                        <a class="nav-link <?= $estado !== 'papelera' ? 'active fw-bold border-bottom-0' : 'text-muted border-0' ?>" href="?estado=borrador">Activos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $estado === 'papelera' ? 'active fw-bold border-bottom-0 text-danger' : 'text-danger border-0' ?>" href="?estado=papelera"><i class="bi bi-trash"></i> Papelera</a>
                    </li>
                </ul>

                <div class="rxn-toolbar-split mb-3">
                    <div class="small text-muted">Buscador con sugerencias en vivo; el listado solo se filtra al confirmar.</div>
                    <form action="/mi-empresa/crm/presupuestos" method="GET" class="rxn-filter-form justify-content-end flex-md-nowrap ms-md-auto" style="width: 980px; max-width: 100%;" data-search-form>
                        <input type="hidden" name="search" value="<?= htmlspecialchars((string) $search) ?>" data-search-hidden>
                        <select name="estado" class="form-select form-select-sm border-secondary rxn-filter-compact" style="width: 145px;" onchange="this.form.submit()">
                            <option value="">Todos</option>
                            <option value="borrador" <?= $estado === 'borrador' ? 'selected' : '' ?>>Borrador</option>
                            <option value="emitido" <?= $estado === 'emitido' ? 'selected' : '' ?>>Emitido</option>
                            <option value="anulado" <?= $estado === 'anulado' ? 'selected' : '' ?>>Anulado</option>
                        </select>
                        <select name="limit" class="form-select form-select-sm border-secondary rxn-filter-compact" style="width: 80px;" onchange="this.form.submit()">
                            <option value="25" <?= $limit === 25 ? 'selected' : '' ?>>25</option>
                            <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100</option>
                        </select>
                        <select name="field" class="form-select form-select-sm border-secondary rxn-filter-compact rxn-search-field-wrap" style="width: 165px;" data-search-field>
                            <option value="all" <?= $field === 'all' ? 'selected' : '' ?>>Todos los campos</option>
                            <option value="numero" <?= $field === 'numero' ? 'selected' : '' ?>>Numero</option>
                            <option value="cliente" <?= $field === 'cliente' ? 'selected' : '' ?>>Cliente</option>
                            <option value="usuario" <?= $field === 'usuario' ? 'selected' : '' ?>>Usuario</option>
                            <option value="estado" <?= $field === 'estado' ? 'selected' : '' ?>>Estado</option>
                            <option value="fecha" <?= $field === 'fecha' ? 'selected' : '' ?>>Fecha</option>
                        </select>
                        <div class="rxn-search-input-wrap rxn-filter-grow" style="width: 270px;">
                            <input type="text" class="form-control form-control-sm border-secondary" placeholder='🔎 Presioná F3 o "/" para buscar' value="<?= htmlspecialchars((string) $search) ?>" data-search-input data-suggestions-url="/mi-empresa/crm/presupuestos/sugerencias" autocomplete="off">
                            <div class="rxn-search-suggestions d-none" data-search-suggestions></div>
                        </div>
                        <button type="submit" class="btn btn-secondary btn-sm text-white">Buscar</button>
                        <?php if ($search !== '' || $estado !== ''): ?>
                            <a href="/mi-empresa/crm/presupuestos" class="btn btn-light btn-sm border">Limpiar</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="form-text rxn-search-help text-md-end mb-3">El presupuesto conserva snapshots de cliente, cabecera comercial y renglones para no romper historicos.</div>

                <?php if ($estado !== 'papelera'): ?>
                <div class="mb-3">
                    <button type="submit" form="hiddenFormBulk" formaction="/mi-empresa/crm/presupuestos/eliminar-masivo" class="btn btn-outline-danger btn-sm rxn-confirm-form" data-msg="¿Enviar los presupuestos seleccionados a la papelera?"><i class="bi bi-trash"></i> Eliminar Seleccionados</button>
                </div>
                <?php else: ?>
                <div class="mb-3 d-flex gap-2">
                    <button type="submit" form="hiddenFormBulk" formaction="/mi-empresa/crm/presupuestos/restore-masivo" class="btn btn-outline-success btn-sm rxn-confirm-form" data-msg="¿Restaurar los presupuestos seleccionados?"><i class="bi bi-arrow-counterclockwise"></i> Restaurar Seleccionados</button>
                    <button type="submit" form="hiddenFormBulk" formaction="/mi-empresa/crm/presupuestos/force-delete-masivo" class="btn btn-outline-danger btn-sm rxn-confirm-form" data-msg="⚠️ ATENCIÓN: Acción irreversible. ¿Destruir definitivamente los presupuestos seleccionados?"><i class="bi bi-x-circle"></i> Destruir Seleccionados</button>
                </div>
                <?php endif; ?>

                <form method="POST" id="hiddenFormBulk"></form>
                <div class="table-responsive rxn-table-responsive">
                    <table class="table table-hover align-middle table-sm rxn-crud-table" style="font-size: 0.92rem;">
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
                                <th><?= $sortLink('numero', 'Numero') ?></th>
                                <th><?= $sortLink('fecha', 'Fecha') ?></th>
                                <th><?= $sortLink('cliente_nombre_snapshot', 'Cliente') ?></th>
                                <th>Usuario</th>
                                <th>Items</th>
                                <th><?= $sortLink('total', 'Total') ?></th>
                                <th><?= $sortLink('estado', 'Estado') ?></th>
                                <th class="rxn-row-chevron-col"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($presupuestos === []): ?>
                                <tr>
                                    <td colspan="7" class="rxn-empty-state text-muted">
                                        <div class="mb-2 fs-3"><i class="bi bi-receipt-cutoff"></i></div>
                                        Todavia no hay presupuestos CRM cargados o no existen coincidencias con el filtro actual.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($presupuestos as $presupuesto): ?>
                                    <tr data-row-link="/mi-empresa/crm/presupuestos/<?= (int) $presupuesto['id'] ?>/editar" class="rxn-row-link" onclick="if(event.target.closest('.btn-group, .form-check-input') === null) { window.location.href = this.dataset.rowLink; }">
                                        <td><input type="checkbox" name="ids[]" value="<?= (int) $presupuesto['id'] ?>" class="form-check-input check-item" form="hiddenFormBulk" data-row-link-ignore></td>
                                        <td class="fw-bold text-dark">#<?= (int) $presupuesto['numero'] ?></td>
                                        <td class="text-nowrap"><small><?= htmlspecialchars((string) $presupuesto['fecha']) ?></small></td>
                                        <td class="text-wrap" style="max-width: 260px;"><?= htmlspecialchars((string) ($presupuesto['cliente_nombre_snapshot'] ?? 'Sin cliente')) ?></td>
                                        <td class="text-nowrap" style="max-width: 150px; overflow: hidden; text-overflow: ellipsis;"><small><?= htmlspecialchars((string) ($presupuesto['usuario_nombre'] ?? 'Sin asignar')) ?></small></td>
                                        <td><span class="badge bg-light text-dark border"><?= (int) ($presupuesto['items_count'] ?? 0) ?> reng.</span></td>
                                        <td class="fw-semibold text-success">$<?= number_format((float) ($presupuesto['total'] ?? 0), 2, ',', '.') ?></td>
                                        <td>
                                            <?php $estadoActual = (string) ($presupuesto['estado'] ?? 'borrador'); ?>
                                            <?php if ($estadoActual === 'emitido'): ?>
                                                <span class="badge bg-success">Emitido</span>
                                            <?php elseif ($estadoActual === 'anulado'): ?>
                                                <span class="badge bg-danger">Anulado</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Borrador</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="rxn-row-chevron-col text-end text-nowrap">
                                            <?php if ($estado === 'papelera'): ?>
                                                <form method="POST" action="/mi-empresa/crm/presupuestos/<?= (int) $presupuesto['id'] ?>/restore" class="d-inline" data-row-link-ignore>
                                                    <button type="submit" class="btn btn-sm btn-outline-success py-0 px-2 fw-medium rxn-confirm-form" data-msg="¿Restaurar este presupuesto?" title="Restaurar"><i class="bi bi-arrow-counterclockwise"></i></button>
                                                </form>
                                                <form method="POST" action="/mi-empresa/crm/presupuestos/<?= (int) $presupuesto['id'] ?>/force-delete" class="d-inline" data-row-link-ignore>
                                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2 fw-medium rxn-confirm-form" data-msg="¿Destruir definitivamente este presupuesto?" title="Destruir"><i class="bi bi-x-circle"></i></button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" action="/mi-empresa/crm/presupuestos/<?= (int) $presupuesto['id'] ?>/copiar" class="d-inline" data-row-link-ignore>
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary py-0 px-2 fw-medium" title="Copiar presupuesto (Usa presupuesto como plantilla)"><i class="bi bi-copy"></i></button>
                                                </form>
                                                <form method="POST" action="/mi-empresa/crm/presupuestos/<?= (int) $presupuesto['id'] ?>/eliminar" class="d-inline" data-row-link-ignore>
                                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2 fw-medium rxn-confirm-form" data-msg="¿Enviar este presupuesto a la papelera?" title="Eliminar"><i class="bi bi-trash"></i></button>
                                                </form>
                                            <?php endif; ?>
                                            <a href="/mi-empresa/crm/presupuestos/<?= (int) $presupuesto['id'] ?>/editar" class="btn btn-sm btn-outline-primary py-0 px-2 fw-medium rxn-row-link-action rxn-row-chevron" title="Abrir presupuesto" aria-label="Abrir presupuesto" data-row-link-ignore>›</a>
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
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= htmlspecialchars($buildQuery(['page' => max(1, $page - 1)])) ?>">Anterior</a>
                            </li>
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= htmlspecialchars($buildQuery(['page' => $i])) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
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
    <script src="/js/rxn-confirm-modal.js"></script>
    <script src="/js/rxn-row-links.js"></script>
    <script src="/js/rxn-shortcuts.js"></script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
