<?php
$pageTitle = 'RXN Tiendas IA';
ob_start();
?>
<?php
    $field = $field ?? 'all';
    $categoriaId = $categoriaId ?? null;
    $basePath = $basePath ?? '/rxnTiendasIA/public/mi-empresa/articulos';
    $dashboardPath = $dashboardPath ?? '/rxnTiendasIA/public/mi-empresa/dashboard';
    $helpPath = $helpPath ?? '/rxnTiendasIA/public/mi-empresa/ayuda?area=tiendas';
    $moduleNotesKey = $moduleNotesKey ?? 'articulos';
    $moduleNotesLabel = $moduleNotesLabel ?? 'Articulos';
    $showCategories = $showCategories ?? true;
    $showSyncActions = $showSyncActions ?? true;
    $syncTodoPath = $syncTodoPath ?? '/rxnTiendasIA/public/mi-empresa/sync/todo';
    $syncStockPath = $syncStockPath ?? '/rxnTiendasIA/public/mi-empresa/sync/stock';
    $syncPreciosPath = $syncPreciosPath ?? '/rxnTiendasIA/public/mi-empresa/sync/precios';
    $syncArticulosPath = $syncArticulosPath ?? '/rxnTiendasIA/public/mi-empresa/sync/articulos';
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
    <div class="container mt-5 rxn-responsive-container">
        <div class="rxn-module-header mb-4">
            <div>
                <h2><?= htmlspecialchars((string) ($headerTitle ?? 'Directorio de Articulos')) ?></h2>
                <p class="text-muted"><?= htmlspecialchars((string) ($headerDescription ?? 'Gestion de articulos.')) ?></p>
            </div>
            <div class="rxn-module-actions">
                <?php require BASE_PATH . '/app/shared/views/components/user_action_menu.php'; ?>
                <a href="<?= htmlspecialchars($helpPath) ?>" class="btn btn-outline-info" target="_blank" rel="noopener noreferrer"><i class="bi bi-question-circle"></i> Ayuda</a>
                <a href="<?= htmlspecialchars($dashboardPath) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver al Panel</a>
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
                        <form action="<?= htmlspecialchars($basePath) ?>/purgar" method="POST" class="d-inline">
                            <button type="submit" class="btn btn-danger btn-sm fw-bold shadow-sm">Purgar Todo</button>
                        </form>
                        <?php if ($showSyncActions): ?>
                            <a href="<?= htmlspecialchars($syncTodoPath) ?>" class="btn btn-sm fw-bold shadow-sm text-dark border-0" style="background: linear-gradient(135deg, #f7b733 0%, #fc4a1a 100%);" data-rxn-confirm="¿Ejecutar sincronizacion total? Esto encadena Articulos + Precios + Stock." data-confirm-type="warning">Sync Total</a>
                            <a href="<?= htmlspecialchars($syncStockPath) ?>" class="btn btn-outline-info btn-sm fw-bold shadow-sm" data-rxn-confirm="¿Forzar sincronizacion de stock?" data-confirm-type="info">Sync Stock</a>
                            <a href="<?= htmlspecialchars($syncPreciosPath) ?>" class="btn btn-outline-success btn-sm fw-bold shadow-sm" data-rxn-confirm="¿Forzar sincronizacion de precios?" data-confirm-type="success">Sync Precios</a>
                            <a href="<?= htmlspecialchars($syncArticulosPath) ?>" class="btn btn-warning btn-sm fw-bold shadow-sm" data-rxn-confirm="¿Forzar sincronizacion del maestro de articulos?" data-confirm-type="warning">Sync Articulos</a>
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
                                    <th><?= $sortLink('codigo_externo', 'Codigo / SKU') ?></th>
                                    <th><?= $sortLink('nombre', 'Descripcion') ?></th>
                                    <?php if ($showCategories): ?><th><?= $sortLink('categoria_nombre', 'Categoria') ?></th><?php endif; ?>
                                    <th>Descripcion Adicional</th>
                                    <th class="text-nowrap"><?= $sortLink('precio_lista_1', 'P. L1 ($)') ?></th>
                                    <th class="text-nowrap"><?= $sortLink('precio_lista_2', 'P. L2 ($)') ?></th>
                                    <th><?= $sortLink('stock_actual', 'Stock') ?></th>
                                    <th><?= $sortLink('activo', 'Estado') ?></th>
                                    <th><?= $sortLink('fecha_ultima_sync', 'Ultima Sincro') ?></th>
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
                                            <td class="text-nowrap"><span class="badge bg-secondary text-start" style="white-space: pre; font-family: monospace; font-size: 0.85rem;"><?= htmlspecialchars((string) $art['codigo_externo']) ?></span></td>
                                            <td class="fw-bold text-dark text-wrap" style="max-width: 250px;"><?= htmlspecialchars((string) $art['nombre']) ?></td>
                                            <?php if ($showCategories): ?>
                                                <td>
                                                    <?php if (!empty($art['categoria_nombre'])): ?>
                                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars((string) $art['categoria_nombre']) ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted small">Sin categoria</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
                                            <td class="text-wrap" style="max-width: 200px;"><small class="text-muted"><?= htmlspecialchars((string) ($art['descripcion'] ?? '---')) ?></small></td>
                                            <td class="fw-semibold text-primary text-nowrap">$<?= $art['precio_lista_1'] !== null ? number_format((float) $art['precio_lista_1'], 2, ',', '.') : '--' ?></td>
                                            <td class="fw-semibold text-success text-nowrap">$<?= $art['precio_lista_2'] !== null ? number_format((float) $art['precio_lista_2'], 2, ',', '.') : '--' ?></td>
                                            <td class="fw-bold text-nowrap"><?= $art['stock_actual'] !== null ? (float) $art['stock_actual'] : '--' ?></td>
                                            <td>
                                                <?php if ($art['activo']): ?>
                                                    <span class="badge bg-success bg-opacity-75">Activo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger bg-opacity-75">Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-nowrap"><small class="text-secondary"><?= htmlspecialchars((string) $art['fecha_ultima_sync']) ?></small></td>
                                            <td class="text-end text-nowrap">
                                                <div class="btn-group" data-row-link-ignore>
                                                    <?php if (!$isPapelera): ?>
                                                        <a href="<?= htmlspecialchars($basePath) ?>/editar?id=<?= (int) $art['id'] ?>" class="btn btn-sm btn-outline-info" title="Editar"><i class="bi bi-pencil"></i></a>
                                                        
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/rxnTiendasIA/public/js/rxn-crud-search.js"></script>
    <script src="/rxnTiendasIA/public/js/rxn-confirm-modal.js"></script>
    <script src="/rxnTiendasIA/public/js/rxn-row-links.js"></script>
    <script src="/rxnTiendasIA/public/js/rxn-shortcuts.js"></script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
