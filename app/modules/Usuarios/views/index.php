<?php
$pageTitle = 'RXN Suite';
ob_start();
?>
<?php
    $filters = $filters ?? ['search' => '', 'field' => 'all', 'sort' => 'id', 'dir' => 'desc', 'page' => 1];
    $pagination = $pagination ?? ['page' => 1, 'totalPages' => 1, 'hasPrevious' => false, 'hasNext' => false, 'previousPage' => 1, 'nextPage' => 1];
    $search = $filters['search'] ?? '';
    $field = $filters['field'] ?? 'all';
    $sort = $filters['sort'] ?? 'id';
    $dir = $filters['dir'] ?? 'desc';
    $page = (int) ($filters['page'] ?? 1);
    $status = $filters['status'] ?? 'activos';
    $isPapelera = $status === 'papelera';
    $hasSearch = $search !== '';
    $area = $area ?? 'tiendas';
    $basePath = $basePath ?? '/mi-empresa/usuarios';
    $helpPath = $helpPath ?? '/mi-empresa/ayuda?area=' . urlencode((string) $area);
    $dashboardPath = $dashboardPath ?? '/mi-empresa/dashboard';
    $environmentLabel = $environmentLabel ?? 'Entorno Operativo';
    $isGlobalAdmin = !empty($_SESSION['es_rxn_admin']) && $_SESSION['es_rxn_admin'] == 1;
    $contextLabel = $isGlobalAdmin
        ? 'Vista global RXN sobre todos los usuarios.'
        : $environmentLabel . ' | Empresa #' . (string) \App\Core\Context::getEmpresaId();
    $buildQuery = static function (array $overrides = []) use ($search, $field, $sort, $dir, $page, $area, $status): string {
        $params = [
            'area' => $area,
            'search' => $search,
            'field' => $field,
            'sort' => $sort,
            'dir' => $dir,
            'page' => $page,
            'status' => $status,
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
    <div class="container-fluid mt-2 rxn-responsive-container">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
            <div>
                <h2 class="fw-bold mb-1">Gestión de Usuarios</h2>
                
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= htmlspecialchars($helpPath) ?>" class="btn btn-outline-info btn-sm" target="_blank" rel="noopener noreferrer" title="Ayuda"><i class="bi bi-question-circle"></i> Ayuda</a>
                <a href="<?= htmlspecialchars($basePath) ?>/crear?<?= htmlspecialchars(http_build_query(['area' => $area])) ?>" class="btn btn-primary btn-sm fw-bold"><i class="bi bi-plus-lg"></i> Nuevo Usuario</a>
                <a href="<?= htmlspecialchars($dashboardPath) ?>" class="btn btn-outline-secondary btn-sm" title="Volver al Entorno"><i class="bi bi-arrow-left"></i> Volver</a>
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
                    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
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
                            placeholder='🔎 Presioná F3 o "/" para buscar'
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
                <?php if (!$isPapelera): ?>
                <div class="mb-3">
                    <button type="submit" form="hiddenFormBulk" formaction="<?= htmlspecialchars($basePath) ?>/eliminar-masivo" class="btn btn-outline-danger btn-sm rxn-confirm-form" data-msg="¿Enviar los usuarios seleccionados a la papelera?"><i class="bi bi-trash"></i> Eliminar Seleccionados</button>
                </div>
                <?php else: ?>
                <div class="mb-3 d-flex gap-2">
                    <button type="submit" form="hiddenFormBulk" formaction="<?= htmlspecialchars($basePath) ?>/restore-masivo" class="btn btn-outline-success btn-sm rxn-confirm-form" data-msg="¿Restaurar los usuarios seleccionados?"><i class="bi bi-arrow-counterclockwise"></i> Restaurar Seleccionados</button>
                    <button type="submit" form="hiddenFormBulk" formaction="<?= htmlspecialchars($basePath) ?>/force-delete-masivo" class="btn btn-outline-danger btn-sm rxn-confirm-form" data-msg="⚠️ ATENCIÓN: Acción irreversible. ¿Destruir definitivamente los usuarios seleccionados?"><i class="bi bi-x-circle"></i> Destruir Seleccionados</button>
                </div>
                <?php endif; ?>

                <form id="hiddenFormBulk" method="POST">
                    <input type="hidden" name="area" value="<?= htmlspecialchars((string) $area) ?>">

                    <div class="table-responsive rxn-table-responsive">
                        <table class="table table-hover mb-0 align-middle rxn-crud-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;" class="text-center">
                                        <input type="checkbox" class="form-check-input" id="selectAllCheckbox" onclick="document.querySelectorAll('.row-checkbox').forEach(e => e.checked = this.checked);">
                                    </th>
                                    <th class="rxn-filter-col" data-filter-field="id"><?= $sortLink('id', 'ID') ?></th>
                                    <th class="rxn-filter-col" data-filter-field="nombre"><?= $sortLink('nombre', 'Nombre') ?></th>
                                    <th class="rxn-filter-col" data-filter-field="email"><?= $sortLink('email', 'Email') ?></th>
                                    <th class="rxn-filter-col" data-filter-field="es_admin"><?= $sortLink('es_admin', 'Rol') ?></th>
                                    <th class="rxn-filter-col" data-filter-field="activo"><?= $sortLink('activo', 'Estado') ?></th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $u): ?>
                                    <tr data-row-link="<?= htmlspecialchars($basePath) ?>/<?= htmlspecialchars((string) $u->id) ?>/editar?<?= htmlspecialchars(http_build_query(['area' => $area])) ?>">
                                        <td class="text-center" data-row-link-ignore>
                                            <input type="checkbox" class="form-check-input row-checkbox" name="ids[]" value="<?= htmlspecialchars((string) $u->id) ?>" form="hiddenFormBulk">
                                        </td>
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
                                        <td class="text-end text-nowrap">
                                            <div class="btn-group" data-row-link-ignore>
                                                <?php if (!$isPapelera): ?>
                                                    <a href="<?= htmlspecialchars($basePath) ?>/<?= htmlspecialchars((string) $u->id) ?>/editar?<?= htmlspecialchars(http_build_query(['area' => $area])) ?>" class="btn btn-sm btn-outline-info" title="Editar"><i class="bi bi-pencil"></i></a>
                                                    
                                                    <form action="<?= htmlspecialchars($basePath) ?>/<?= htmlspecialchars((string) $u->id) ?>/copiar?<?= htmlspecialchars(http_build_query(['area' => $area])) ?>" method="POST" class="d-inline m-0 p-0 rxn-confirm-form" data-msg="¿Copiar usuario (duplicar registro)?">
                                                        <button type="submit" class="btn btn-sm btn-outline-success" style="border-radius: 0;" title="Copiar">
                                                            <i class="bi bi-files"></i>
                                                        </button>
                                                    </form>
                                                    
                                                    <form action="<?= htmlspecialchars($basePath) ?>/<?= htmlspecialchars((string) $u->id) ?>/eliminar?<?= htmlspecialchars(http_build_query(['area' => $area])) ?>" method="POST" class="d-inline m-0 p-0 rxn-confirm-form" data-msg="¿Enviar usuario a la papelera?">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" style="border-top-left-radius: 0; border-bottom-left-radius: 0;" title="Eliminar (Papelera)">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form action="<?= htmlspecialchars($basePath) ?>/<?= htmlspecialchars((string) $u->id) ?>/restore?<?= htmlspecialchars(http_build_query(['area' => $area])) ?>" method="POST" class="d-inline m-0 p-0 rxn-confirm-form" data-msg="¿Confirma restaurar este usuario?">
                                                        <button type="submit" class="btn btn-sm btn-outline-success border-end-0" title="Restaurar Usuario">
                                                            <i class="bi bi-arrow-counterclockwise"></i> Restaurar
                                                        </button>
                                                    </form>

                                                    <form action="<?= htmlspecialchars($basePath) ?>/<?= htmlspecialchars((string) $u->id) ?>/force-delete?<?= htmlspecialchars(http_build_query(['area' => $area])) ?>" method="POST" class="d-inline m-0 p-0 rxn-confirm-form" data-msg="⚠️ ATENCIÓN: Esta acción es irreversible. ¿Eliminar definitivamente?">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" style="border-top-left-radius: 0; border-bottom-left-radius: 0;" title="Eliminar Definitivamente">
                                                            <i class="bi bi-x-circle"></i> Destruir
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    </form>
                    <?php if (($pagination['totalPages'] ?? 1) > 1): ?>
                        <div class="card-footer  border-0 pt-3 pb-3">
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
    
<?php
$content = ob_get_clean();
ob_start();
?>
<script src="/js/rxn-advanced-filters.js"></script>
<script src="/js/rxn-crud-search.js"></script>
    <script src="/js/rxn-confirm-modal.js"></script>
    <script src="/js/rxn-row-links.js"></script>

    <script src="/js/rxn-shortcuts.js"></script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
