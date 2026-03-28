<!DOCTYPE html>
<html lang="es" <?= \App\Core\Helpers\UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - rxnTiendasIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/rxnTiendasIA/public/css/rxn-theming.css" rel="stylesheet">
</head>
<body class="rxn-page-shell">
    <?php
    $filters = $filters ?? ['search' => '', 'field' => 'all', 'sort' => 'id', 'dir' => 'desc', 'page' => 1];
    $pagination = $pagination ?? ['page' => 1, 'totalPages' => 1, 'hasPrevious' => false, 'hasNext' => false, 'previousPage' => 1, 'nextPage' => 1];
    $search = $filters['search'] ?? '';
    $field = $filters['field'] ?? 'all';
    $sort = $filters['sort'] ?? 'id';
    $dir = $filters['dir'] ?? 'desc';
    $page = (int) ($filters['page'] ?? 1);
    $hasSearch = $search !== '';
    $area = $area ?? 'tiendas';
    $basePath = $basePath ?? '/rxnTiendasIA/public/mi-empresa/usuarios';
    $helpPath = $helpPath ?? '/rxnTiendasIA/public/mi-empresa/ayuda?area=' . urlencode((string) $area);
    $dashboardPath = $dashboardPath ?? '/rxnTiendasIA/public/mi-empresa/dashboard';
    $environmentLabel = $environmentLabel ?? 'Entorno Operativo';
    $isGlobalAdmin = !empty($_SESSION['es_rxn_admin']) && $_SESSION['es_rxn_admin'] == 1;
    $contextLabel = $isGlobalAdmin
        ? 'Vista global RXN sobre todos los usuarios.'
        : $environmentLabel . ' | Empresa #' . (string) \App\Core\Context::getEmpresaId();
    $buildQuery = static function (array $overrides = []) use ($search, $field, $sort, $dir, $page, $area): string {
        $params = [
            'area' => $area,
            'search' => $search,
            'field' => $field,
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
    $sortUrl = static function (string $column) use ($buildQuery, $sort, $dir, $basePath): string {
        $nextDir = ($sort === $column && $dir === 'asc') ? 'desc' : 'asc';

        return $basePath . '?' . $buildQuery([
            'sort' => $column,
            'dir' => $nextDir,
        ]);
    };
    $sortIndicator = static function (string $column) use ($sort, $dir): string {
        if ($sort !== $column) {
            return '';
        }

        return $dir === 'desc' ? '▼' : '▲';
    };
    $sortLink = static function (string $column, string $label) use ($sortUrl, $sortIndicator): string {
        return '<a class="rxn-sort-link" href="' . htmlspecialchars($sortUrl($column)) . '"><span>' . htmlspecialchars($label) . '</span><span class="rxn-sort-indicator">' . htmlspecialchars($sortIndicator($column)) . '</span></a>';
    };
    ?>
    <div class="container mt-5 rxn-responsive-container">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
            <div>
                <h2 class="fw-bold mb-1">Gestión de Usuarios</h2>
                <p class="text-muted mb-0"><?= htmlspecialchars($contextLabel) ?></p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= htmlspecialchars($helpPath) ?>" class="btn btn-outline-info" target="_blank" rel="noopener noreferrer">Ayuda</a>
                <a href="<?= htmlspecialchars($dashboardPath) ?>" class="btn btn-outline-secondary">Volver al Entorno</a>
                <a href="<?= htmlspecialchars($basePath) ?>/crear?<?= htmlspecialchars(http_build_query(['area' => $area])) ?>" class="btn btn-primary fw-bold">+ Nuevo Usuario</a>
            </div>
        </div>

        <?php
        $moduleNotesKey = 'usuarios';
        $moduleNotesLabel = 'Usuarios';
        require BASE_PATH . '/app/shared/views/components/module_notes_panel.php';
        ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_GET['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card rxn-crud-card rxn-crud-toolbar mb-4">
            <div class="card-body">
                <form method="GET" action="<?= htmlspecialchars($basePath) ?>" class="row g-3 align-items-end" data-search-form>
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                    <input type="hidden" name="dir" value="<?= htmlspecialchars($dir) ?>">
                    <input type="hidden" name="area" value="<?= htmlspecialchars((string) $area) ?>">
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>" data-search-hidden>
                    <div class="col-12 col-lg-3 rxn-search-field-wrap">
                        <label for="field" class="form-label">Buscar por</label>
                        <select class="form-select" id="field" name="field" data-search-field>
                            <option value="all" <?= $field === 'all' ? 'selected' : '' ?>>Todos los campos</option>
                            <option value="id" <?= $field === 'id' ? 'selected' : '' ?>>ID</option>
                            <option value="nombre" <?= $field === 'nombre' ? 'selected' : '' ?>>Nombre</option>
                            <option value="email" <?= $field === 'email' ? 'selected' : '' ?>>Email</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-5 rxn-search-input-wrap">
                        <label for="search" class="form-label">Busqueda</label>
                        <input
                            type="search"
                            class="form-control"
                            id="search"
                            value="<?= htmlspecialchars($search) ?>"
                            placeholder="Buscar por nombre o email"
                            autocomplete="off"
                            data-search-input
                            data-suggestions-url="<?= htmlspecialchars($basePath) ?>/sugerencias?<?= htmlspecialchars(http_build_query(['area' => $area])) ?>"
                        >
                        <div class="rxn-search-suggestions d-none" data-search-suggestions></div>
                    </div>
                    <div class="col-12 col-lg-4 d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">Aplicar</button>
                        <?php if ($hasSearch): ?>
                            <a href="<?= htmlspecialchars($basePath) ?>?<?= htmlspecialchars(http_build_query(['area' => $area])) ?>" class="btn btn-outline-secondary">Limpiar filtros</a>
                        <?php endif; ?>
                    </div>
                </form>
                <div class="form-text rxn-search-help">Se sugieren coincidencias mientras escribis, pero el listado solo se filtra al buscar o presionar Enter.</div>
                <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mt-3 small text-muted">
                    <div>
                        Total registrados: <strong><?= htmlspecialchars((string) $totalUsuarios) ?></strong>
                        <?php if ($hasSearch): ?>
                            | Coincidencias: <strong><?= htmlspecialchars((string) $filteredCount) ?></strong>
                        <?php endif; ?>
                    </div>
                    <div>Pagina <strong><?= htmlspecialchars((string) $pagination['page']) ?></strong> de <strong><?= htmlspecialchars((string) $pagination['totalPages']) ?></strong></div>
                </div>
            </div>
        </div>

        <div class="card rxn-crud-card">
            <div class="card-body p-0">
                <?php if (empty($usuarios)): ?>
                    <div class="rxn-empty-state">
                        <h3 class="h5 mb-2"><?= $hasSearch ? 'No se encontraron usuarios' : 'Todavia no hay usuarios disponibles' ?></h3>
                        <p class="text-muted mb-3"><?= $hasSearch ? 'Proba con otro termino de busqueda o limpia el filtro actual.' : 'Cuando se registren usuarios, apareceran en este listado.' ?></p>
                        <div class="d-flex justify-content-center gap-2 flex-wrap">
                            <?php if ($hasSearch): ?>
                                <a href="<?= htmlspecialchars($basePath) ?>?<?= htmlspecialchars(http_build_query(['area' => $area])) ?>" class="btn btn-outline-secondary">Limpiar filtros</a>
                            <?php endif; ?>
                            <a href="<?= htmlspecialchars($basePath) ?>/crear?<?= htmlspecialchars(http_build_query(['area' => $area])) ?>" class="btn btn-primary">Nuevo Usuario</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive rxn-table-responsive">
                        <table class="table table-hover mb-0 align-middle rxn-crud-table">
                            <thead class="table-light">
                                <tr>
                                    <th><?= $sortLink('id', 'ID') ?></th>
                                    <th><?= $sortLink('nombre', 'Nombre') ?></th>
                                    <th><?= $sortLink('email', 'Email') ?></th>
                                    <th><?= $sortLink('es_admin', 'Rol') ?></th>
                                    <th><?= $sortLink('activo', 'Estado') ?></th>
                                    <th class="rxn-row-chevron-col"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $u): ?>
                                    <tr data-row-link="<?= htmlspecialchars($basePath) ?>/<?= htmlspecialchars((string) $u->id) ?>/editar?<?= htmlspecialchars(http_build_query(['area' => $area])) ?>">
                                        <td class="text-muted"><?= htmlspecialchars((string) $u->id) ?></td>
                                        <td class="fw-bold"><?= htmlspecialchars($u->nombre) ?></td>
                                        <td><?= htmlspecialchars($u->email) ?></td>
                                        <td>
                                            <?php if ($u->es_admin == 1): ?>
                                                <span class="badge bg-danger">Admin</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Operador</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($u->activo == 1): ?>
                                                <span class="badge bg-success">Activo</span>
                                            <?php else: ?>
                                                <span class="badge bg-dark">Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="rxn-row-chevron-col">
                                            <a href="<?= htmlspecialchars($basePath) ?>/<?= htmlspecialchars((string) $u->id) ?>/editar?<?= htmlspecialchars(http_build_query(['area' => $area])) ?>" class="btn btn-sm btn-outline-primary px-2 rxn-row-link-action rxn-row-chevron" title="Abrir usuario" aria-label="Abrir usuario" data-row-link-ignore>›</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (($pagination['totalPages'] ?? 1) > 1): ?>
                        <div class="card-footer bg-white border-0 pt-3 pb-3">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                                <div class="rxn-crud-pagination-note">
                                    <?= $hasSearch ? 'La paginacion conserva filtros y orden actuales.' : 'La paginacion conserva el orden actual del listado.' ?>
                                </div>
                                <nav aria-label="Paginacion de usuarios" class="rxn-pagination-wrap">
                                    <ul class="pagination pagination-sm mb-0">
                                        <li class="page-item <?= $pagination['hasPrevious'] ? '' : 'disabled' ?>">
                                            <a class="page-link" href="<?= $pagination['hasPrevious'] ? htmlspecialchars($basePath . '?' . $buildQuery(['page' => $pagination['previousPage']])) : '#' ?>">Anterior</a>
                                        </li>
                                        <?php for ($currentPage = 1; $currentPage <= $pagination['totalPages']; $currentPage++): ?>
                                            <li class="page-item <?= $currentPage === $pagination['page'] ? 'active' : '' ?>">
                                                <a class="page-link" href="<?= htmlspecialchars($basePath . '?' . $buildQuery(['page' => $currentPage])) ?>"><?= htmlspecialchars((string) $currentPage) ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= $pagination['hasNext'] ? '' : 'disabled' ?>">
                                            <a class="page-link" href="<?= $pagination['hasNext'] ? htmlspecialchars($basePath . '?' . $buildQuery(['page' => $pagination['nextPage']])) : '#' ?>">Siguiente</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/rxnTiendasIA/public/js/rxn-crud-search.js"></script>
    <script src="/rxnTiendasIA/public/js/rxn-row-links.js"></script>
</body>
</html>
