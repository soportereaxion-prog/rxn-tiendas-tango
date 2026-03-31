<!DOCTYPE html>
<html lang="es" <?= \App\Core\Helpers\UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presupuestos CRM - rxnTiendasIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="/rxnTiendasIA/public/css/rxn-theming.css" rel="stylesheet">
</head>
<body class="rxn-page-shell">
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
    <div class="container mt-5 rxn-responsive-container">
        <div class="rxn-module-header mb-4">
            <div>
                <h2>CRM - Presupuestos</h2>
                <p class="text-muted mb-0">Cabecera comercial, renglones acumulables y snapshots locales para preparar el circuito documental sin depender del browser contra Connect.</p>
            </div>
            <div class="rxn-module-actions">
                <?php require BASE_PATH . '/app/shared/views/components/user_action_menu.php'; ?>
                <a href="<?= htmlspecialchars((string) $helpPath) ?>" class="btn btn-outline-info" target="_blank" rel="noopener noreferrer"><i class="bi bi-question-circle"></i> Ayuda</a>
                <a href="/rxnTiendasIA/public/mi-empresa/crm/formularios-impresion/crm_presupuesto" class="btn btn-outline-dark"><i class="bi bi-easel2"></i> Formulario</a>
                <a href="<?= htmlspecialchars((string) $syncCatalogosPath) ?>" class="btn btn-outline-warning" data-rxn-confirm="¿Sincronizar catalogos comerciales CRM para depositos, condiciones, listas, vendedores y transportes?" data-confirm-type="warning"><i class="bi bi-arrow-repeat"></i> Sync Catalogos</a>
                <a href="/rxnTiendasIA/public/mi-empresa/crm/presupuestos/crear" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Nuevo presupuesto</a>
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

                <div class="rxn-toolbar-split mb-3">
                    <div class="small text-muted">Buscador con sugerencias en vivo; el listado solo se filtra al confirmar.</div>
                    <form action="/rxnTiendasIA/public/mi-empresa/crm/presupuestos" method="GET" class="rxn-filter-form justify-content-end flex-md-nowrap ms-md-auto" style="width: 980px; max-width: 100%;" data-search-form>
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
                            <option value="estado" <?= $field === 'estado' ? 'selected' : '' ?>>Estado</option>
                            <option value="fecha" <?= $field === 'fecha' ? 'selected' : '' ?>>Fecha</option>
                        </select>
                        <div class="rxn-search-input-wrap rxn-filter-grow" style="width: 270px;">
                            <input type="text" class="form-control form-control-sm border-secondary" placeholder="Buscar por numero, cliente, fecha..." value="<?= htmlspecialchars((string) $search) ?>" data-search-input data-suggestions-url="/rxnTiendasIA/public/mi-empresa/crm/presupuestos/sugerencias" autocomplete="off">
                            <div class="rxn-search-suggestions d-none" data-search-suggestions></div>
                        </div>
                        <button type="submit" class="btn btn-secondary btn-sm text-white">Buscar</button>
                        <?php if ($search !== '' || $estado !== ''): ?>
                            <a href="/rxnTiendasIA/public/mi-empresa/crm/presupuestos" class="btn btn-light btn-sm border">Limpiar</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="form-text rxn-search-help text-md-end mb-3">El presupuesto conserva snapshots de cliente, cabecera comercial y renglones para no romper historicos.</div>

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
                                <th><?= $sortLink('numero', 'Numero') ?></th>
                                <th><?= $sortLink('fecha', 'Fecha') ?></th>
                                <th><?= $sortLink('cliente_nombre_snapshot', 'Cliente') ?></th>
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
                                    <tr data-row-link="/rxnTiendasIA/public/mi-empresa/crm/presupuestos/<?= (int) $presupuesto['id'] ?>/editar" class="rxn-row-link">
                                        <td class="fw-bold text-dark">#<?= (int) $presupuesto['numero'] ?></td>
                                        <td class="text-nowrap"><small><?= htmlspecialchars((string) $presupuesto['fecha']) ?></small></td>
                                        <td class="text-wrap" style="max-width: 260px;"><?= htmlspecialchars((string) ($presupuesto['cliente_nombre_snapshot'] ?? 'Sin cliente')) ?></td>
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
                                            <form method="POST" action="/rxnTiendasIA/public/mi-empresa/crm/presupuestos/<?= (int) $presupuesto['id'] ?>/copiar" class="d-inline" data-row-link-ignore>
                                                <button type="submit" class="btn btn-sm btn-outline-secondary py-0 px-2 fw-medium" title="Copiar presupuesto (Usa presupuesto como plantilla)"><i class="bi bi-copy"></i></button>
                                            </form>
                                            <a href="/rxnTiendasIA/public/mi-empresa/crm/presupuestos/<?= (int) $presupuesto['id'] ?>/editar" class="btn btn-sm btn-outline-primary py-0 px-2 fw-medium rxn-row-link-action rxn-row-chevron" title="Abrir presupuesto" aria-label="Abrir presupuesto" data-row-link-ignore>›</a>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/rxnTiendasIA/public/js/rxn-crud-search.js"></script>
    <script src="/rxnTiendasIA/public/js/rxn-confirm-modal.js"></script>
    <script src="/rxnTiendasIA/public/js/rxn-row-links.js"></script>
    <script src="/rxnTiendasIA/public/js/rxn-shortcuts.js"></script>
</body>
</html>

