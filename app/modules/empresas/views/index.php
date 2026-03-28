<!DOCTYPE html>
<html lang="es" <?= \App\Core\Helpers\UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Módulo Empresas - rxnTiendasIA</title>
    <!-- CSS Bootstrap 5 CDN para pruebas rápidas como base -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }
    </style>
    <link href="/rxnTiendasIA/public/css/rxn-theming.css" rel="stylesheet">
</head>
<body class="rxn-page-shell">
    <?php
    $filters = $filters ?? ['search' => '', 'field' => 'all', 'sort' => 'nombre', 'dir' => 'asc', 'page' => 1];
    $pagination = $pagination ?? ['page' => 1, 'totalPages' => 1, 'hasPrevious' => false, 'hasNext' => false, 'previousPage' => 1, 'nextPage' => 1];
    $search = $filters['search'] ?? '';
    $field = $filters['field'] ?? 'all';
    $sort = $filters['sort'] ?? 'nombre';
    $dir = $filters['dir'] ?? 'asc';
    $page = (int) ($filters['page'] ?? 1);
    $hasSearch = $search !== '';
    $buildQuery = static function (array $overrides = []) use ($search, $field, $sort, $dir, $page): string {
        $params = [
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
    $sortUrl = static function (string $column) use ($buildQuery, $sort, $dir): string {
        $nextDir = ($sort === $column && $dir === 'asc') ? 'desc' : 'asc';

        return '/rxnTiendasIA/public/empresas?' . $buildQuery([
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
                <h2 class="mb-1">Gestión de Empresas</h2>
                <p class="text-muted mb-0">Listado centralizado del backoffice multiempresa.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="/rxnTiendasIA/public/" class="btn btn-outline-secondary">Volver al Inicio</a>
                <a href="/rxnTiendasIA/public/empresas/crear" class="btn btn-primary fw-bold shadow-sm">+ Nueva Empresa</a>
            </div>
        </div>

        <?php
        $moduleNotesKey = 'empresas';
        $moduleNotesLabel = 'Empresas';
        require BASE_PATH . '/app/shared/views/components/module_notes_panel.php';
        ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Operación exitosa: <?= htmlspecialchars($_GET['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                Error: <?= htmlspecialchars($_GET['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card rxn-crud-card rxn-crud-toolbar mb-4">
            <div class="card-body">
                <form method="GET" action="/rxnTiendasIA/public/empresas" class="row g-3 align-items-end" data-search-form>
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                    <input type="hidden" name="dir" value="<?= htmlspecialchars($dir) ?>">
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>" data-search-hidden>
                    <div class="col-12 col-lg-3 rxn-search-field-wrap">
                        <label for="field" class="form-label">Buscar por</label>
                        <select class="form-select" id="field" name="field" data-search-field>
                            <option value="all" <?= $field === 'all' ? 'selected' : '' ?>>Todos los campos</option>
                            <option value="id" <?= $field === 'id' ? 'selected' : '' ?>>ID</option>
                            <option value="codigo" <?= $field === 'codigo' ? 'selected' : '' ?>>Codigo</option>
                            <option value="nombre" <?= $field === 'nombre' ? 'selected' : '' ?>>Nombre</option>
                            <option value="slug" <?= $field === 'slug' ? 'selected' : '' ?>>Slug</option>
                            <option value="razon_social" <?= $field === 'razon_social' ? 'selected' : '' ?>>Razon social</option>
                            <option value="cuit" <?= $field === 'cuit' ? 'selected' : '' ?>>CUIT</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-6 rxn-search-input-wrap">
                        <label for="search" class="form-label">Busqueda</label>
                        <input
                            type="search"
                            class="form-control"
                            id="search"
                            value="<?= htmlspecialchars($search) ?>"
                            placeholder="Escribi para filtrar empresas"
                            autocomplete="off"
                            data-search-input
                            data-suggestions-url="/rxnTiendasIA/public/empresas/sugerencias"
                        >
                        <div class="rxn-search-suggestions d-none" data-search-suggestions></div>
                    </div>
                    <div class="col-12 col-lg-3 d-flex flex-wrap gap-2 align-self-end">
                        <button type="submit" class="btn btn-primary">Aplicar</button>
                        <?php if ($hasSearch): ?>
                            <a href="/rxnTiendasIA/public/empresas" class="btn btn-outline-secondary">Limpiar filtros</a>
                        <?php endif; ?>
                    </div>
                </form>
                <div class="form-text rxn-search-help">Se sugieren coincidencias mientras escribis, pero el listado solo se filtra al buscar o presionar Enter.</div>
                <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mt-3 small text-muted">
                    <div>
                        Total registradas: <strong><?= htmlspecialchars((string) $totalEmpresas) ?></strong>
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
                <?php if (empty($empresas)): ?>
                    <div class="rxn-empty-state">
                        <div class="rxn-empty-state-icon mb-3"><?= $hasSearch ? '0' : '+' ?></div>
                        <h3 class="h5 mb-2"><?= $hasSearch ? 'No se encontraron empresas' : 'Todavia no hay empresas registradas' ?></h3>
                        <p class="text-muted mb-3"><?= $hasSearch ? 'Proba con otro campo o ajusta el termino de busqueda.' : 'Crea la primera empresa para empezar a operar el entorno multiempresa.' ?></p>
                        <div class="d-flex justify-content-center gap-2 flex-wrap">
                            <?php if ($hasSearch): ?>
                                <a href="/rxnTiendasIA/public/empresas" class="btn btn-outline-secondary">Limpiar filtros</a>
                            <?php endif; ?>
                            <a href="/rxnTiendasIA/public/empresas/crear" class="btn btn-primary">Nueva Empresa</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive rxn-table-responsive">
                        <table class="table table-hover mb-0 align-middle rxn-crud-table">
                            <thead class="table-light">
                                <tr>
                                    <th><?= $sortLink('id', 'ID') ?></th>
                                    <th><?= $sortLink('codigo', 'Codigo') ?></th>
                                    <th><?= $sortLink('nombre', 'Nombre') ?></th>
                                    <th><?= $sortLink('slug', 'Slug') ?></th>
                                    <th><?= $sortLink('razon_social', 'Razon Social') ?></th>
                                    <th><?= $sortLink('cuit', 'CUIT') ?></th>
                                    <th><?= $sortLink('activa', 'Estado') ?></th>
                                    <th class="rxn-row-chevron-col"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($empresas as $empresa): ?>
                                <tr data-row-link="/rxnTiendasIA/public/empresas/<?= htmlspecialchars((string) $empresa->id) ?>/editar">
                                    <td class="text-muted"><?= htmlspecialchars((string) $empresa->id) ?></td>
                                    <td><span class="badge bg-secondary-subtle text-secondary-emphasis border"><?= htmlspecialchars($empresa->codigo) ?></span></td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($empresa->nombre) ?></div>
                                    </td>
                                    <td>
                                        <?php if (!empty($empresa->slug)): ?>
                                            <code><?= htmlspecialchars((string) $empresa->slug) ?></code>
                                        <?php else: ?>
                                            <span class="text-muted">Sin slug</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars((string) $empresa->razon_social) ?></td>
                                    <td><?= htmlspecialchars((string) $empresa->cuit) ?></td>
                                    <td>
                                        <?php if ($empresa->activa): ?>
                                            <span class="badge text-bg-success d-inline-flex align-items-center gap-2"><span class="status-dot bg-white"></span>Activa</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-secondary d-inline-flex align-items-center gap-2"><span class="status-dot bg-white"></span>Inactiva</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="rxn-row-chevron-col">
                                        <a href="/rxnTiendasIA/public/empresas/<?= htmlspecialchars((string) $empresa->id) ?>/editar" class="btn btn-sm btn-outline-primary px-2 rxn-row-link-action rxn-row-chevron" title="Abrir empresa" aria-label="Abrir empresa" data-row-link-ignore>›</a>
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
                                <nav aria-label="Paginacion de empresas" class="rxn-pagination-wrap">
                                    <ul class="pagination pagination-sm mb-0">
                                        <li class="page-item <?= $pagination['hasPrevious'] ? '' : 'disabled' ?>">
                                            <a class="page-link" href="<?= $pagination['hasPrevious'] ? htmlspecialchars('/rxnTiendasIA/public/empresas?' . $buildQuery(['page' => $pagination['previousPage']])) : '#' ?>">Anterior</a>
                                        </li>
                                        <?php for ($currentPage = 1; $currentPage <= $pagination['totalPages']; $currentPage++): ?>
                                            <li class="page-item <?= $currentPage === $pagination['page'] ? 'active' : '' ?>">
                                                <a class="page-link" href="<?= htmlspecialchars('/rxnTiendasIA/public/empresas?' . $buildQuery(['page' => $currentPage])) ?>"><?= htmlspecialchars((string) $currentPage) ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= $pagination['hasNext'] ? '' : 'disabled' ?>">
                                            <a class="page-link" href="<?= $pagination['hasNext'] ? htmlspecialchars('/rxnTiendasIA/public/empresas?' . $buildQuery(['page' => $pagination['nextPage']])) : '#' ?>">Siguiente</a>
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
