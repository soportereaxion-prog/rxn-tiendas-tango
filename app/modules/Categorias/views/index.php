<?php
$pageTitle = 'RXN Tiendas IA';
ob_start();
?>
<?php
    $filters = $filters ?? ['search' => '', 'field' => 'all', 'sort' => 'orden_visual', 'dir' => 'asc', 'page' => 1];
    $pagination = $pagination ?? ['page' => 1, 'totalPages' => 1, 'hasPrevious' => false, 'hasNext' => false, 'previousPage' => 1, 'nextPage' => 1];
    $search = $filters['search'] ?? '';
    $field = $filters['field'] ?? 'all';
    $sort = $filters['sort'] ?? 'orden_visual';
    $dir = $filters['dir'] ?? 'asc';
    $page = (int) ($filters['page'] ?? 1);
    $hasSearch = $search !== '';
    $basePath = $basePath ?? '/rxnTiendasIA/public/mi-empresa/categorias';
    $dashboardPath = $dashboardPath ?? '/rxnTiendasIA/public/mi-empresa/dashboard';
    $helpPath = $helpPath ?? '/rxnTiendasIA/public/mi-empresa/ayuda?area=tiendas';
    $buildQuery = static function (array $overrides = []) use ($search, $field, $sort, $dir, $page): string {
        $params = [
            'search' => $search,
            'field' => $field,
            'sort' => $sort,
            'dir' => $dir,
            'page' => $page,
            'status' => $filters['status'] ?? 'activos',
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
        return $basePath . '?' . $buildQuery(['sort' => $column, 'dir' => $nextDir]);
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
                <h2 class="fw-bold mb-1">Categorias del Catalogo</h2>
                
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= htmlspecialchars($helpPath) ?>" class="btn btn-outline-info" target="_blank" rel="noopener noreferrer">Ayuda</a>
                <a href="<?= htmlspecialchars($dashboardPath) ?>" class="btn btn-outline-secondary">Volver al Entorno</a>
                <a href="<?= htmlspecialchars($basePath) ?>/crear" class="btn btn-primary fw-bold">+ Nueva Categoria</a>
            </div>
        </div>

        <?php
        $moduleNotesKey = 'categorias';
        $moduleNotesLabel = 'Categorias';
        require BASE_PATH . '/app/shared/views/components/module_notes_panel.php';
        ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars((string) $_GET['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php
        $status = $filters['status'] ?? 'activos';
        $isPapelera = $status === 'papelera';
        ?>

        <ul class="nav nav-tabs mb-4 border-secondary border-opacity-25" style="border-bottom-width: 2px;">
            <li class="nav-item">
                <a class="nav-link <?= !$isPapelera ? 'active fw-bold bg-dark text-light border-secondary border-bottom-0' : 'text-muted border-0 hover-text-light' ?>" href="<?= htmlspecialchars($basePath) ?>?<?= htmlspecialchars($buildQuery(['status' => 'activos', 'page' => 1])) ?>">
                    Activas
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-danger <?= $isPapelera ? 'active fw-bold bg-dark border-secondary border-bottom-0' : 'border-0 hover-text-danger' ?>" href="<?= htmlspecialchars($basePath) ?>?<?= htmlspecialchars($buildQuery(['status' => 'papelera', 'page' => 1])) ?>">
                    <i class="bi bi-trash"></i> Papelera
                </a>
            </li>
        </ul>

        <div class="card rxn-crud-card rxn-crud-toolbar mb-4">
            <div class="card-body">
                <form method="GET" action="<?= htmlspecialchars($basePath) ?>" class="row g-3 align-items-end" data-search-form>
                    <input type="hidden" name="status" value="<?= htmlspecialchars((string) $status) ?>">
                    <input type="hidden" name="sort" value="<?= htmlspecialchars((string) $sort) ?>">
                    <input type="hidden" name="dir" value="<?= htmlspecialchars((string) $dir) ?>">
                    <input type="hidden" name="search" value="<?= htmlspecialchars((string) $search) ?>" data-search-hidden>
                    <div class="col-12 col-lg-3 rxn-search-field-wrap">
                        <label for="field" class="form-label">Buscar por</label>
                        <select class="form-select" id="field" name="field" data-search-field>
                            <option value="all" <?= $field === 'all' ? 'selected' : '' ?>>Todos los campos</option>
                            <option value="nombre" <?= $field === 'nombre' ? 'selected' : '' ?>>Nombre</option>
                            <option value="slug" <?= $field === 'slug' ? 'selected' : '' ?>>Slug</option>
                            <option value="descripcion_corta" <?= $field === 'descripcion_corta' ? 'selected' : '' ?>>Descripcion</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-5 rxn-search-input-wrap">
                        <label for="search" class="form-label">Busqueda</label>
                        <input
                            type="search"
                            class="form-control"
                            id="search"
                            value="<?= htmlspecialchars((string) $search) ?>"
                            placeholder='🔎 Presioná F3 o "/" para buscar'
                            autocomplete="off"
                            data-search-input
                            data-suggestions-url="/rxnTiendasIA/public/mi-empresa/categorias/sugerencias"
                        >
                        <div class="rxn-search-suggestions d-none" data-search-suggestions></div>
                    </div>
                    <div class="col-12 col-lg-4 d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">Aplicar</button>
                        <?php if ($hasSearch): ?>
                            <a href="<?= htmlspecialchars($basePath) ?>" class="btn btn-outline-secondary">Limpiar filtros</a>
                        <?php endif; ?>
                    </div>
                </form>
                <div class="form-text rxn-search-help">Se sugieren coincidencias mientras escribis, pero el listado solo se filtra al buscar o presionar Enter.</div>
                <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mt-3 small text-muted">
                    <div>
                        Total registradas: <strong><?= htmlspecialchars((string) $totalCategorias) ?></strong>
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
                <?php if (empty($categorias)): ?>
                    <div class="rxn-empty-state">
                        <h3 class="h5 mb-2"><?= $hasSearch ? 'No se encontraron categorias' : 'Todavia no hay categorias configuradas' ?></h3>
                        <p class="text-muted mb-3"><?= $hasSearch ? 'Proba con otro termino de busqueda o limpia el filtro actual.' : 'Crea tu primera categoria para ordenar el catalogo y mostrar bloques en la tienda.' ?></p>
                        <div class="d-flex justify-content-center gap-2 flex-wrap">
                            <?php if ($hasSearch): ?>
                                <a href="<?= htmlspecialchars($basePath) ?>" class="btn btn-outline-secondary">Limpiar filtros</a>
                            <?php endif; ?>
                            <a href="<?= htmlspecialchars($basePath) ?>/crear" class="btn btn-primary">Nueva Categoria</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="p-3 pb-0">
                        <div class="mb-3 d-flex gap-2">
                            <?php if (!$isPapelera): ?>
                                <button type="submit" form="hiddenFormBulk" formaction="<?= htmlspecialchars($basePath) ?>/eliminar-masivo" class="btn btn-outline-danger btn-sm rxn-confirm-form" data-msg="¿Enviar las categorías seleccionadas a la papelera?"><i class="bi bi-trash"></i> Eliminar Seleccionadas</button>
                            <?php else: ?>
                                <button type="submit" form="hiddenFormBulk" formaction="<?= htmlspecialchars($basePath) ?>/restore-masivo" class="btn btn-outline-success btn-sm rxn-confirm-form" data-msg="¿Restaurar las categorías seleccionadas?"><i class="bi bi-arrow-counterclockwise"></i> Restaurar Seleccionadas</button>
                                <button type="submit" form="hiddenFormBulk" formaction="<?= htmlspecialchars($basePath) ?>/force-delete-masivo" class="btn btn-outline-danger btn-sm rxn-confirm-form" data-msg="⚠️ ATENCIÓN: Acción irreversible. ¿Destruir definitivamente las categorías seleccionadas?"><i class="bi bi-x-circle"></i> Destruir Seleccionadas</button>
                            <?php endif; ?>
                        </div>

                        <form id="hiddenFormBulk" method="POST"></form>

                        <div class="table-responsive rxn-table-responsive">
                            <table class="table table-hover mb-0 align-middle rxn-crud-table">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 40px;">
                                            <input type="checkbox" id="checkAll" class="form-check-input" data-row-link-ignore onclick="document.querySelectorAll('.check-item').forEach(e => e.checked = this.checked);">
                                        </th>
                                        <th>Vista</th>
                                    <th><?= $sortLink('nombre', 'Nombre') ?></th>
                                    <th><?= $sortLink('slug', 'Slug') ?></th>
                                    <th>Descripcion</th>
                                    <th><?= $sortLink('orden_visual', 'Orden') ?></th>
                                    <th>Articulos</th>
                                    <th><?= $sortLink('visible_store', 'Store') ?></th>
                                    <th><?= $sortLink('activa', 'Estado') ?></th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categorias as $categoria): ?>
                                    <tr data-row-link="<?= htmlspecialchars($basePath) ?>/<?= htmlspecialchars((string) $categoria->id) ?>/editar">
                                        <td>
                                            <input type="checkbox" name="ids[]" value="<?= (int) $categoria->id ?>" class="form-check-input check-item" form="hiddenFormBulk" data-row-link-ignore>
                                        </td>
                                        <td>
                                            <div class="rounded-3 overflow-hidden border bg-light d-flex align-items-center justify-content-center" style="width: 72px; height: 56px;">
                                                <?php if (!empty($categoria->imagen_portada)): ?>
                                                    <img src="/rxnTiendasIA/public<?= htmlspecialchars((string) $categoria->imagen_portada) ?>" alt="<?= htmlspecialchars((string) $categoria->nombre) ?>" class="w-100 h-100" style="object-fit: cover;">
                                                <?php else: ?>
                                                    <span class="small text-muted">Sin imagen</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="fw-bold"><?= htmlspecialchars((string) $categoria->nombre) ?></td>
                                        <td><code><?= htmlspecialchars((string) $categoria->slug) ?></code></td>
                                        <td class="text-muted" style="max-width: 280px;"><?= htmlspecialchars((string) ($categoria->descripcion_corta ?? 'Sin descripcion')) ?></td>
                                        <td><?= htmlspecialchars((string) $categoria->orden_visual) ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars((string) $categoria->articulos_count) ?></span></td>
                                        <td>
                                            <?php if ((int) $categoria->visible_store === 1): ?>
                                                <span class="badge bg-info text-dark">Visible</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark border">Oculta</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ((int) $categoria->activa === 1): ?>
                                                <span class="badge bg-success">Activa</span>
                                            <?php else: ?>
                                                <span class="badge bg-dark">Inactiva</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <div class="btn-group" data-row-link-ignore>
                                                <?php if (!$isPapelera): ?>
                                                    <form action="<?= htmlspecialchars($basePath) ?>/<?= htmlspecialchars((string) $categoria->id) ?>/copiar" method="POST" class="d-inline m-0 p-0" title="Copiar Categoría">
                                                        <button type="submit" class="btn btn-sm btn-outline-secondary" style="border-top-right-radius: 0; border-bottom-right-radius: 0;">
                                                            <i class="bi bi-copy"></i>
                                                        </button>
                                                    </form>

                                                    <a href="<?= htmlspecialchars($basePath) ?>/<?= htmlspecialchars((string) $categoria->id) ?>/editar" class="btn btn-sm btn-outline-info" style="border-radius: 0; margin-left: -1px;" title="Editar Categoría">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>

                                                    <form action="<?= htmlspecialchars($basePath) ?>/<?= htmlspecialchars((string) $categoria->id) ?>/eliminar" method="POST" class="d-inline m-0 p-0 rxn-confirm-form" data-msg="¿Enviar categoría a la papelera?">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" style="border-top-left-radius: 0; border-bottom-left-radius: 0; border-left: 0; margin-left: -1px;" title="Enviar a Papelera">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form action="<?= htmlspecialchars($basePath) ?>/<?= htmlspecialchars((string) $categoria->id) ?>/restore" method="POST" class="d-inline m-0 p-0 rxn-confirm-form" data-msg="¿Confirma restaurar esta categoría?">
                                                        <button type="submit" class="btn btn-sm btn-outline-success border-end-0" title="Restaurar Categoría">
                                                            <i class="bi bi-arrow-counterclockwise"></i> Restaurar
                                                        </button>
                                                    </form>

                                                    <form action="<?= htmlspecialchars($basePath) ?>/<?= htmlspecialchars((string) $categoria->id) ?>/force-delete" method="POST" class="d-inline m-0 p-0 rxn-confirm-form" data-msg="⚠️ ATENCIÓN: Esta acción es irreversible. ¿Eliminar definitivamente?">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" style="border-top-left-radius: 0; border-bottom-left-radius: 0;" title="Destruir Permanente">
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
                </div>

                <?php if (($pagination['totalPages'] ?? 1) > 1): ?>
                        <div class="card-footer  border-0 pt-3 pb-3">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                                <div class="rxn-crud-pagination-note">
                                    <?= $hasSearch ? 'La paginacion conserva filtros y orden actuales.' : 'La paginacion conserva el orden actual del listado.' ?>
                                </div>
                                <nav aria-label="Paginacion de categorias" class="rxn-pagination-wrap">
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/rxnTiendasIA/public/js/rxn-crud-search.js"></script>
    <script src="/rxnTiendasIA/public/js/rxn-row-links.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const checkAll = document.getElementById('checkAll');
        const checkItems = document.querySelectorAll('.check-item');
        if (checkAll && checkItems.length > 0) {
            checkAll.addEventListener('change', function() {
                checkItems.forEach(item => item.checked = this.checked);
            });
            checkItems.forEach(item => item.addEventListener('change', () => {
                const allChecked = Array.from(checkItems).every(i => i.checked);
                const someChecked = Array.from(checkItems).some(i => i.checked);
                checkAll.checked = allChecked;
                checkAll.indeterminate = someChecked && !allChecked;
            }));
        }
    });
    </script>
    <script src="/rxnTiendasIA/public/js/rxn-shortcuts.js"></script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
