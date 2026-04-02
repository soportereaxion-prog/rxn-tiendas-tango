<?php
$pageTitle = 'RXN Tiendas IA';
ob_start();
?>
<?php
    $filters = $filters ?? ['search' => '', 'field' => 'all', 'sort' => 'nombre', 'dir' => 'asc', 'page' => 1];
    $pagination = $pagination ?? ['page' => 1, 'totalPages' => 1, 'hasPrevious' => false, 'hasNext' => false, 'previousPage' => 1, 'nextPage' => 1];
    $search = $filters['search'] ?? '';
    $field = $filters['field'] ?? 'all';
    $sort = $filters['sort'] ?? 'nombre';
    $dir = $filters['dir'] ?? 'asc';
    $page = (int) ($filters['page'] ?? 1);
    $status = $filters['status'] ?? 'activos';
    $isPapelera = $status === 'papelera';
    $hasSearch = $search !== '';
    $buildQuery = static function (array $overrides = []) use ($search, $field, $sort, $dir, $page, $status): string {
        $params = [
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
                    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
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
                            placeholder='🔎 Presioná F3 o "/" para buscar'
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


        <ul class="nav nav-tabs mb-4 rxn-crud-tabs">
            <li class="nav-item">
                <a class="nav-link <?= !$isPapelera ? 'active fw-bold' : '' ?>" href="/rxnTiendasIA/public/empresas?<?= htmlspecialchars($buildQuery(['status' => 'activos', 'page' => 1])) ?>">
                    Activos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-danger <?= $isPapelera ? 'active fw-bold' : '' ?>" href="/rxnTiendasIA/public/empresas?<?= htmlspecialchars($buildQuery(['status' => 'papelera', 'page' => 1])) ?>">
                    <i class="bi bi-trash"></i> Papelera
                </a>
            </li>
        </ul>

        <?php if (!$isPapelera): ?>
        <div class="mb-3">
            <button type="submit" form="hiddenFormBulk" formaction="/rxnTiendasIA/public/empresas/eliminar-masivo" class="btn btn-outline-danger btn-sm rxn-confirm-form" data-msg="¿Enviar las empresas seleccionadas a la papelera?"><i class="bi bi-trash"></i> Eliminar Seleccionadas</button>
        </div>
        <?php else: ?>
        <div class="mb-3 d-flex gap-2">
            <button type="submit" form="hiddenFormBulk" formaction="/rxnTiendasIA/public/empresas/restore-masivo" class="btn btn-outline-success btn-sm rxn-confirm-form" data-msg="¿Restaurar las empresas seleccionadas?"><i class="bi bi-arrow-counterclockwise"></i> Restaurar Seleccionadas</button>
            <button type="submit" form="hiddenFormBulk" formaction="/rxnTiendasIA/public/empresas/force-delete-masivo" class="btn btn-outline-danger btn-sm rxn-confirm-form" data-msg="⚠️ ATENCIÓN: Acción irreversible. ¿Destruir definitivamente las empresas seleccionadas?"><i class="bi bi-x-circle"></i> Destruir Seleccionadas</button>
        </div>
        <?php endif; ?>

        <div class="card rxn-crud-card">
            <div class="card-body p-0">
                <form id="hiddenFormBulk" method="POST">
                </form>
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
                                    <th style="width: 40px;">
                                        <input class="form-check-input ms-2" type="checkbox" id="check-all" onclick="document.querySelectorAll('.check-item').forEach(e => e.checked = this.checked);">
                                    </th>
                                    <th><?= $sortLink('id', 'ID') ?></th>
                                    <th><?= $sortLink('codigo', 'Codigo') ?></th>
                                    <th><?= $sortLink('nombre', 'Nombre') ?></th>
                                    <th><?= $sortLink('slug', 'Slug') ?></th>
                                    <th><?= $sortLink('razon_social', 'Razon Social') ?></th>
                                    <th><?= $sortLink('cuit', 'CUIT') ?></th>
                                    <th><?= $sortLink('activa', 'Estado') ?></th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($empresas as $empresa): ?>
                                <tr data-row-link="/rxnTiendasIA/public/empresas/<?= htmlspecialchars((string) $empresa->id) ?>/editar">
                                    <td>
                                        <input type="checkbox" name="ids[]" class="form-check-input ms-2 check-item" value="<?= htmlspecialchars((string)$empresa->id) ?>" form="hiddenFormBulk" data-row-link-ignore>
                                    </td>
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
                                            <span class="badge text-bg-success d-inline-flex align-items-center gap-2"><span class="status-dot "></span>Activa</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-secondary d-inline-flex align-items-center gap-2"><span class="status-dot "></span>Inactiva</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <div class="btn-group" data-row-link-ignore>
                                            <?php if (!$isPapelera): ?>
                                                <a href="/rxnTiendasIA/public/empresas/<?= htmlspecialchars((string) $empresa->id) ?>/editar" class="btn btn-sm btn-outline-info" title="Editar"><i class="bi bi-pencil"></i></a>
                                                
                                                <form action="/rxnTiendasIA/public/empresas/<?= htmlspecialchars((string) $empresa->id) ?>/copiar" method="POST" class="d-inline m-0 p-0 rxn-confirm-form" data-msg="¿Copiar empresa (duplicar registro)?">
                                                    <button type="submit" class="btn btn-sm btn-outline-success" style="border-radius: 0;" title="Copiar">
                                                        <i class="bi bi-files"></i>
                                                    </button>
                                                </form>
                                                
                                                <form action="/rxnTiendasIA/public/empresas/<?= htmlspecialchars((string) $empresa->id) ?>/eliminar" method="POST" class="d-inline m-0 p-0 rxn-confirm-form" data-msg="¿Enviar empresa a la papelera?">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" style="border-top-left-radius: 0; border-bottom-left-radius: 0;" title="Eliminar (Papelera)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form action="/rxnTiendasIA/public/empresas/<?= htmlspecialchars((string) $empresa->id) ?>/restore" method="POST" class="d-inline m-0 p-0 rxn-confirm-form" data-msg="¿Confirma restaurar esta empresa?">
                                                    <button type="submit" class="btn btn-sm btn-outline-success border-end-0" title="Restaurar Empresa">
                                                        <i class="bi bi-arrow-counterclockwise"></i> Restaurar
                                                    </button>
                                                </form>

                                                <form action="/rxnTiendasIA/public/empresas/<?= htmlspecialchars((string) $empresa->id) ?>/force-delete" method="POST" class="d-inline m-0 p-0 rxn-confirm-form" data-msg="⚠️ ATENCIÓN: Esta acción es irreversible. ¿Eliminar definitivamente?">
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
                    <?php if (($pagination['totalPages'] ?? 1) > 1): ?>
                        <div class="card-footer  border-0 pt-3 pb-3">
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
    
<?php
$content = ob_get_clean();
ob_start();
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/rxnTiendasIA/public/js/rxn-crud-search.js"></script>
    <script src="/rxnTiendasIA/public/js/rxn-confirm-modal.js"></script>
    <script src="/rxnTiendasIA/public/js/rxn-row-links.js"></script>
    <script src="/rxnTiendasIA/public/js/rxn-shortcuts.js"></script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
