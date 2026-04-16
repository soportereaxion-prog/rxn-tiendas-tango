<?php
$pageTitle = 'Notas CRM | ' . ($environmentLabel ?? 'App');
$usePageHeader = true;
$pageHeaderTitle = 'Gestión de Notas';
$pageHeaderSubtitle = 'Anotaciones, seguimientos e historial de clientes.';
$pageHeaderIcon = 'bi bi-journal-text';
$pageHeaderBackUrl = $dashboardPath ?? '/';
$pageHeaderBackLabel = 'Volver';

$sort = $sort ?? 'created_at';
$dir = $dir ?? 'DESC';
$tratativaFiltroInfo = $tratativaFiltroInfo ?? null;
$tratativaFiltroId = $tratativaFiltroInfo['id'] ?? null;

$buildQuery = function (array $overrides = []) use ($search, $sort, $dir, $page, $status, $tratativaFiltroId) {
    $params = [
        'search' => $search,
        'limit' => 25,
        'sort' => $sort,
        'dir' => $dir,
        'page' => $page,
        'status' => $status ?? 'activos',
    ];
    if ($tratativaFiltroId !== null) {
        $params['tratativa_id'] = $tratativaFiltroId;
    }

    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
            continue;
        }
        $params[$key] = $value;
    }

    return http_build_query($params);
};
ob_start();
?>
<a href="<?= htmlspecialchars($indexPath) ?>/crear" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nueva Nota</a>
<a href="<?= htmlspecialchars($indexPath) ?>/importar" class="btn btn-outline-info"><i class="bi bi-upload"></i> Importar CSV/Excel</a>
<a href="<?= htmlspecialchars($indexPath) ?>/exportar?<?= htmlspecialchars($buildQuery()) ?>" class="btn btn-outline-success"><i class="bi bi-download"></i> Exportar a Excel</a>
<?php
$pageHeaderActions = ob_get_clean();

ob_start();
?>

        <?php 
        $moduleNotesKey = 'crm_notas';
        $moduleNotesLabel = 'CRM - Notas';
        require BASE_PATH . '/app/shared/views/components/module_notes_panel.php'; 
        ?>

        <?php if ($tratativaFiltroInfo !== null): ?>
            <div class="alert alert-info bg-info bg-opacity-10 border-info border-opacity-25 text-info d-flex justify-content-between align-items-center shadow-sm" role="alert">
                <div>
                    <i class="bi bi-funnel-fill"></i>
                    Filtrando notas de la
                    <strong>Tratativa #<?= (int) $tratativaFiltroInfo['numero'] ?></strong>
                    <?php if (!empty($tratativaFiltroInfo['titulo'])): ?>
                        <span class="text-muted"> — <?= htmlspecialchars((string) $tratativaFiltroInfo['titulo']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <a href="/mi-empresa/crm/tratativas/<?= (int) $tratativaFiltroInfo['id'] ?>" class="btn btn-sm btn-outline-light"><i class="bi bi-arrow-left"></i> Volver a la tratativa</a>
                    <a href="<?= htmlspecialchars($indexPath) ?>?<?= htmlspecialchars($buildQuery(['tratativa_id' => null, 'page' => 1])) ?>" class="btn btn-sm btn-outline-warning"><i class="bi bi-x-lg"></i> Quitar filtro</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <?= htmlspecialchars($_GET['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <?= htmlspecialchars($_GET['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 bg-dark text-light mb-4 rxn-card-hover">
            <div class="card-body">
                <form action="<?= htmlspecialchars($indexPath) ?>" method="GET" class="row g-3 align-items-end" data-search-form>
                    <input type="hidden" name="status" value="<?= htmlspecialchars($status ?? 'activos') ?>">
                    <div class="col-md-6">
                        <label class="form-label text-muted small mb-1">Buscar Notas</label>
                        <input type="text" name="search" class="form-control bg-dark text-light border-secondary" value="<?= htmlspecialchars($search ?? '') ?>" placeholder='🔎 Presioná F3 o "/" para buscar' data-search-input autocomplete="off">
                    </div>
                    <div class="col-md-2" style="display:none;">
                    </div>
                </form>
            </div>
        </div>

        <?php
        $status = $status ?? 'activos';
        $isPapelera = $status === 'papelera';
        ?>

        <ul class="nav nav-tabs mb-4 border-secondary border-opacity-25" style="border-bottom-width: 2px;">
            <li class="nav-item">
                <a class="nav-link <?= !$isPapelera ? 'active fw-bold bg-dark text-light border-secondary border-bottom-0' : 'text-muted border-0 hover-text-light' ?>" href="<?= htmlspecialchars($indexPath) ?>?<?= htmlspecialchars($buildQuery(['status' => 'activos', 'page' => 1])) ?>">
                    Activos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-danger <?= $isPapelera ? 'active fw-bold bg-dark border-secondary border-bottom-0' : 'border-0 hover-text-danger' ?>" href="<?= htmlspecialchars($indexPath) ?>?<?= htmlspecialchars($buildQuery(['status' => 'papelera', 'page' => 1])) ?>">
                    <i class="bi bi-trash"></i> Papelera
                </a>
            </li>
        </ul>

        <div class="card shadow-sm border-0 bg-dark text-light">
            <?php if (!$isPapelera): ?>
            <div class="px-4 pt-3 pb-2 border-bottom border-secondary border-opacity-25 pb-0">
                <button type="submit" form="hiddenFormBulk" formaction="<?= htmlspecialchars($indexPath) ?>/eliminar-masivo" class="btn btn-outline-danger btn-sm rxn-confirm-form" data-msg="¿Enviar las notas seleccionadas a la papelera?"><i class="bi bi-trash"></i> Eliminar Seleccionadas</button>
            </div>
            <?php else: ?>
            <div class="px-4 pt-3 pb-2 border-bottom border-secondary border-opacity-25 d-flex gap-2">
                <button type="submit" form="hiddenFormBulk" formaction="<?= htmlspecialchars($indexPath) ?>/restore-masivo" class="btn btn-outline-success btn-sm rxn-confirm-form" data-msg="¿Restaurar las notas seleccionadas?"><i class="bi bi-arrow-counterclockwise"></i> Restaurar Seleccionadas</button>
                <button type="submit" form="hiddenFormBulk" formaction="<?= htmlspecialchars($indexPath) ?>/force-delete-masivo" class="btn btn-outline-danger btn-sm rxn-confirm-form" data-msg="⚠️ ATENCIÓN: Acción irreversible. ¿Destruir definitivamente las notas seleccionadas?"><i class="bi bi-x-circle"></i> Destruir Seleccionadas</button>
            </div>
            <?php endif; ?>

            <form method="POST" id="hiddenFormBulk"></form>
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead class="table-dark">
                        <?php
                        $sortLink = function (string $fieldName, string $label) use ($buildQuery, $sort, $dir) {
                            $newDir = ($sort === $fieldName && $dir === 'ASC') ? 'DESC' : 'ASC';
                            $icon = ($sort === $fieldName) ? ($dir === 'ASC' ? '▲' : '▼') : '';
                            $href = '?' . $buildQuery(['sort' => $fieldName, 'dir' => $newDir]);
                            return '<a href="' . $href . '" class="text-white text-decoration-none"><span>' . $label . '</span><span class="ms-1">' . $icon . '</span></a>';
                        };
                        ?>
                        <tr>
                            <th style="width: 40px;"><input type="checkbox" id="checkAll" class="form-check-input" onclick="document.querySelectorAll('.check-item').forEach(e => e.checked = this.checked);"></th>
                            <th style="width: 50px;" class="rxn-filter-col" data-filter-field="id"><?= $sortLink('id', 'ID') ?></th>
                            <th class="rxn-filter-col" data-filter-field="titulo"><?= $sortLink('titulo', 'Título') ?></th>
                            <th class="rxn-filter-col" data-filter-field="cliente_nombre"><?= $sortLink('cliente_nombre', 'Cliente Vinculado') ?></th>
                            <th class="rxn-filter-col" data-filter-field="tratativa_numero"><?= $sortLink('tratativa_numero', 'Tratativa') ?></th>
                            <th class="rxn-filter-col" data-filter-field="tags"><?= $sortLink('tags', 'Tags') ?></th>
                            <th style="width: 150px;" class="rxn-filter-col" data-filter-field="created_at"><?= $sortLink('created_at', 'Fecha') ?></th>
                            <th style="width: 120px;" class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($notas)): ?>
                            <tr><td colspan="8" class="text-center p-4 text-muted">No existen notas registradas.</td></tr>
                        <?php else: ?>
                            <?php foreach ($notas as $item): ?>
                                <tr data-row-link="<?= htmlspecialchars($indexPath) ?>/<?= $item['id'] ?>">
                                    <td data-row-link-ignore><input type="checkbox" name="ids[]" value="<?= (int) $item['id'] ?>" class="form-check-input check-item" form="hiddenFormBulk"></td>
                                    <td class="text-muted small">#<?= $item['id'] ?></td>
                                    <td>
                                        <a href="<?= htmlspecialchars($indexPath) ?>/<?= $item['id'] ?>" class="text-info text-decoration-none fw-bold"><?= htmlspecialchars($item['titulo']) ?></a>
                                        <?php if ($item['activo'] == 0): ?>
                                            <span class="badge bg-danger ms-2">Inactiva</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($item['cliente_nombre']): ?>
                                            <?= htmlspecialchars($item['cliente_nombre']) ?>
                                            <small class="text-muted ms-1">(<?= htmlspecialchars($item['cliente_codigo'] ?? '') ?>)</small>
                                        <?php else: ?>
                                            <span class="text-muted"><i class="bi bi-link-45deg"></i> Sin cliente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-row-link-ignore>
                                        <?php if (!empty($item['tratativa_id'])): ?>
                                            <a href="/mi-empresa/crm/tratativas/<?= (int) $item['tratativa_id'] ?>" class="text-decoration-none" title="Ir al detalle de la tratativa">
                                                <span class="badge bg-primary bg-opacity-75"><i class="bi bi-briefcase"></i> #<?= (int) ($item['tratativa_numero'] ?? 0) ?></span>
                                                <?php if (!empty($item['tratativa_titulo'])): ?>
                                                    <small class="text-muted ms-1"><?= htmlspecialchars(mb_strimwidth((string) $item['tratativa_titulo'], 0, 40, '…')) ?></small>
                                                <?php endif; ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($item['tags'])): ?>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($item['tags']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">Ninguno</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small"><?= date('d/m/Y', strtotime($item['created_at'])) ?></td>
                                    <td class="text-end">
                                        <div class="btn-group" data-row-link-ignore>
                                            <?php if (!$isPapelera): ?>
                                                <a href="<?= htmlspecialchars($indexPath) ?>/<?= $item['id'] ?>" class="btn btn-sm btn-outline-primary" title="Ver Nota"><i class="bi bi-eye"></i></a>
                                                <a href="<?= htmlspecialchars($indexPath) ?>/<?= $item['id'] ?>/editar" class="btn btn-sm btn-outline-info" title="Editar"><i class="bi bi-pencil"></i></a>
                                                <form action="<?= htmlspecialchars($indexPath) ?>/<?= $item['id'] ?>/copiar" method="POST" style="display:inline;" class="rxn-confirm-form" data-msg="¿Copiar nota (duplicar registro)?">
                                                    <button type="submit" class="btn btn-sm btn-outline-success" style="border-radius: 0;" title="Copiar"><i class="bi bi-files"></i></button>
                                                </form>
                                                <form action="<?= htmlspecialchars($indexPath) ?>/<?= $item['id'] ?>/eliminar" method="POST" style="display:inline;" class="rxn-confirm-form" data-msg="¿Enviar nota a la papelera?">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" style="border-top-left-radius: 0; border-bottom-left-radius: 0;" title="Eliminar (Papelera)"><i class="bi bi-trash"></i></button>
                                                </form>
                                            <?php else: ?>
                                                <form action="<?= htmlspecialchars($indexPath) ?>/<?= $item['id'] ?>/restore" method="POST" style="display:inline;" class="rxn-confirm-form" data-msg="¿Confirma restaurar esta nota?">
                                                    <button type="submit" class="btn btn-sm btn-outline-success border-end-0" title="Restaurar Nota"><i class="bi bi-arrow-counterclockwise"></i> Restaurar</button>
                                                </form>
                                                <form action="<?= htmlspecialchars($indexPath) ?>/<?= $item['id'] ?>/force-delete" method="POST" style="display:inline;" class="rxn-confirm-form" data-msg="⚠️ ATENCIÓN: Esta acción es irreversible. ¿Eliminar nota definitivamente?">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" style="border-top-left-radius: 0; border-bottom-left-radius: 0;" title="Destruir"><i class="bi bi-x-circle"></i> Destruir</button>
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
        </div>

        <?php if ($totalPages > 1): ?>
            <!-- Paginación basica -->
            <div class="d-flex justify-content-between align-items-center mt-4">
                <span class="text-sm text-muted">Mostrando página <?= $page ?> de <?= $totalPages ?> (Total: <?= $totalItems ?>)</span>
                <div class="d-flex gap-1">
                    <?php if ($page > 1): ?>
                        <a href="?<?= htmlspecialchars($buildQuery(['page' => $page - 1])) ?>" class="btn btn-sm btn-outline-secondary">Anterior</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?= htmlspecialchars($buildQuery(['page' => $page + 1])) ?>" class="btn btn-sm btn-outline-secondary">Siguiente</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </main>



<?php
$content = ob_get_clean();

ob_start();
?>
    <script>
        // Universal rxn-confirm-modal.js is loaded in the layout and handles rxn-confirm-form submissions.
    </script>
    <script src="/js/rxn-advanced-filters.js"></script>
    <script src="/js/rxn-crud-search.js"></script>
    <script src="/js/rxn-row-links.js"></script>
    <script src="/js/rxn-shortcuts.js"></script>
<?php
$extraScripts = ob_get_clean();

require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
