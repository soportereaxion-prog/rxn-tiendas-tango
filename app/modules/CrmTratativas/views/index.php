<?php
use App\Core\View;
?>
<?php
$pageTitle = 'Tratativas CRM - rxn_suite';
$sort = $sort ?? 'created_at';
$dir = $dir ?? 'DESC';
$estado = $estado ?? '';
$search = $search ?? '';
$field = $field ?? 'all';
$limit = $limit ?? 25;
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$totalItems = $totalItems ?? 0;
$tratativas = $tratativas ?? [];
$status = ($estado === 'papelera') ? 'papelera' : 'activos';
$isPapelera = $status === 'papelera';

$buildQuery = function (array $overrides = []) use ($search, $field, $sort, $dir, $page, $limit, $estado) {
    $params = [
        'search' => $search,
        'field' => $field,
        'limit' => $limit,
        'sort' => $sort,
        'dir' => $dir,
        'page' => $page,
        'estado' => $estado,
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

$estadoBadges = [
    'nueva' => ['bg-secondary', 'Nueva'],
    'en_curso' => ['bg-primary', 'En curso'],
    'ganada' => ['bg-success', 'Ganada'],
    'perdida' => ['bg-danger', 'Perdida'],
    'pausada' => ['bg-warning text-dark', 'Pausada'],
];

ob_start();
?>

<main class="container-fluid flex-grow-1 px-4 mb-5" style="max-width: 1400px;">
    <div class="rxn-module-header mb-4 pb-3 border-bottom border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h3 fw-bold mb-1"><i class="bi bi-briefcase-fill"></i> Tratativas CRM</h1>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= htmlspecialchars($dashboardPath ?? '/') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
            <?php if (!$isPapelera): ?>
                <a href="<?= htmlspecialchars($basePath) ?>/crear" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nueva Tratativa</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (($flashSuccess = \App\Core\Flash::get('success')) !== null): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <?= htmlspecialchars($flashSuccess) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (($flashDanger = \App\Core\Flash::get('danger')) !== null): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <?= htmlspecialchars($flashDanger) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (($flashWarning = \App\Core\Flash::get('warning')) !== null): ?>
        <div class="alert alert-warning alert-dismissible fade show shadow-sm" role="alert">
            <?= htmlspecialchars($flashWarning) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 bg-dark text-light mb-4 rxn-card-hover">
        <div class="card-body">
            <form action="<?= htmlspecialchars($basePath) ?>" method="GET" class="row g-3 align-items-end" data-search-form>
                <input type="hidden" name="estado" value="<?= htmlspecialchars($estado) ?>">
                <div class="col-md-5">
                    <label class="form-label text-muted small mb-1">Buscar Tratativa</label>
                    <input type="text" name="search" class="form-control bg-dark text-light border-secondary" value="<?= htmlspecialchars($search) ?>" placeholder='🔎 Presioná F3 o "/" para buscar' data-search-input autocomplete="off">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small mb-1">Campo</label>
                    <select name="field" class="form-select bg-dark text-light border-secondary">
                        <option value="all" <?= $field === 'all' ? 'selected' : '' ?>>Todos</option>
                        <option value="numero" <?= $field === 'numero' ? 'selected' : '' ?>>Número</option>
                        <option value="titulo" <?= $field === 'titulo' ? 'selected' : '' ?>>Título</option>
                        <option value="cliente" <?= $field === 'cliente' ? 'selected' : '' ?>>Cliente</option>
                        <option value="estado" <?= $field === 'estado' ? 'selected' : '' ?>>Estado</option>
                        <option value="usuario" <?= $field === 'usuario' ? 'selected' : '' ?>>Usuario</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-muted small mb-1">Por página</label>
                    <select name="limit" class="form-select bg-dark text-light border-secondary">
                        <option value="25" <?= $limit === 25 ? 'selected' : '' ?>>25</option>
                        <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50</option>
                        <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Buscar</button>
                </div>
            </form>
        </div>
    </div>

    <ul class="nav nav-tabs mb-4 border-secondary border-opacity-25" style="border-bottom-width: 2px;">
        <li class="nav-item">
            <a class="nav-link <?= !$isPapelera ? 'active fw-bold bg-dark text-light border-secondary border-bottom-0' : 'text-muted border-0' ?>" href="<?= htmlspecialchars($basePath) ?>?<?= htmlspecialchars($buildQuery(['estado' => '', 'page' => 1])) ?>">
                Activas
            </a>
        </li>
        <?php foreach (\App\Modules\CrmTratativas\TratativaRepository::ESTADOS as $e): ?>
            <?php [$badgeClass, $estadoLabel] = $estadoBadges[$e] ?? ['bg-secondary', ucfirst($e)]; ?>
            <li class="nav-item">
                <a class="nav-link <?= $estado === $e ? 'active fw-bold bg-dark text-light border-secondary border-bottom-0' : 'text-muted border-0' ?>" href="<?= htmlspecialchars($basePath) ?>?<?= htmlspecialchars($buildQuery(['estado' => $e, 'page' => 1])) ?>">
                    <?= htmlspecialchars($estadoLabel) ?>
                </a>
            </li>
        <?php endforeach; ?>
        <li class="nav-item ms-auto">
            <a class="nav-link text-danger <?= $isPapelera ? 'active fw-bold bg-dark border-secondary border-bottom-0' : 'border-0' ?>" href="<?= htmlspecialchars($basePath) ?>?<?= htmlspecialchars($buildQuery(['estado' => 'papelera', 'page' => 1])) ?>">
                <i class="bi bi-trash"></i> Papelera
            </a>
        </li>
    </ul>

    <div class="card shadow-sm border-0 bg-dark text-light">
        <?php if (!$isPapelera): ?>
            <div class="px-4 pt-3 pb-2 border-bottom border-secondary border-opacity-25">
                <button type="submit" form="hiddenFormBulk" formaction="<?= htmlspecialchars($basePath) ?>/eliminar-masivo" class="btn btn-outline-danger btn-sm rxn-confirm-form" data-msg="¿Enviar las tratativas seleccionadas a la papelera?"><i class="bi bi-trash"></i> Eliminar Seleccionadas</button>
            </div>
        <?php else: ?>
            <div class="px-4 pt-3 pb-2 border-bottom border-secondary border-opacity-25 d-flex gap-2">
                <button type="submit" form="hiddenFormBulk" formaction="<?= htmlspecialchars($basePath) ?>/restore-masivo" class="btn btn-outline-success btn-sm rxn-confirm-form" data-msg="¿Restaurar las tratativas seleccionadas?"><i class="bi bi-arrow-counterclockwise"></i> Restaurar Seleccionadas</button>
                <button type="submit" form="hiddenFormBulk" formaction="<?= htmlspecialchars($basePath) ?>/force-delete-masivo" class="btn btn-outline-danger btn-sm rxn-confirm-form" data-msg="⚠️ ATENCIÓN: Acción irreversible. Se desvincularán PDS y Presupuestos asociados. ¿Destruir definitivamente?"><i class="bi bi-x-circle"></i> Destruir Seleccionadas</button>
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
                        <th style="width: 80px;" class="rxn-filter-col" data-filter-field="numero"><?= $sortLink('numero', '#') ?></th>
                        <th class="rxn-filter-col" data-filter-field="titulo"><?= $sortLink('titulo', 'Título') ?></th>
                        <th class="rxn-filter-col" data-filter-field="cliente_nombre"><?= $sortLink('cliente_nombre', 'Cliente') ?></th>
                        <th class="rxn-filter-col" data-filter-field="estado"><?= $sortLink('estado', 'Estado') ?></th>
                        <th class="rxn-filter-col" data-filter-field="probabilidad"><?= $sortLink('probabilidad', 'Prob.') ?></th>
                        <th class="rxn-filter-col" data-filter-field="valor_estimado" class="text-end"><?= $sortLink('valor_estimado', 'Valor Est.') ?></th>
                        <th>Vínculos</th>
                        <th class="rxn-filter-col" data-filter-field="usuario_nombre"><?= $sortLink('usuario_nombre', 'Responsable') ?></th>
                        <th style="width: 120px;" class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tratativas)): ?>
                        <tr><td colspan="10" class="text-center p-4 text-muted">No existen tratativas registradas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($tratativas as $item): ?>
                            <?php
                            $estadoItem = (string) ($item['estado'] ?? 'nueva');
                            [$badgeClass, $estadoLabel] = $estadoBadges[$estadoItem] ?? ['bg-secondary', ucfirst($estadoItem)];
                            $pdsCount = (int) ($item['pds_count'] ?? 0);
                            $presupuestosCount = (int) ($item['presupuestos_count'] ?? 0);
                            ?>
                            <tr class="rxn-hover-bg" data-row-link="<?= htmlspecialchars($basePath) ?>/<?= (int) $item['id'] ?>">
                                <td data-row-link-ignore><input type="checkbox" name="ids[]" value="<?= (int) $item['id'] ?>" class="form-check-input check-item" form="hiddenFormBulk"></td>
                                <td class="fw-bold">#<?= (int) ($item['numero'] ?? 0) ?></td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars((string) ($item['titulo'] ?? '')) ?></div>
                                    <?php if (!empty($item['descripcion'])): ?>
                                        <div class="small text-muted"><?= htmlspecialchars(mb_strimwidth((string) $item['descripcion'], 0, 80, '...')) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($item['cliente_nombre'])): ?>
                                        <span class="badge bg-success text-white"><i class="bi bi-building"></i> <?= htmlspecialchars((string) $item['cliente_nombre']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">Sin cliente</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($estadoLabel) ?></span></td>
                                <td>
                                    <span class="badge bg-dark border border-secondary"><?= (int) ($item['probabilidad'] ?? 0) ?>%</span>
                                </td>
                                <td class="text-end">
                                    <?php if ((float) ($item['valor_estimado'] ?? 0) > 0): ?>
                                        $ <?= number_format((float) $item['valor_estimado'], 2, ',', '.') ?>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-secondary me-1" title="PDS asociados"><i class="bi bi-tools"></i> <?= $pdsCount ?></span>
                                    <span class="badge bg-secondary" title="Presupuestos asociados"><i class="bi bi-file-earmark-spreadsheet"></i> <?= $presupuestosCount ?></span>
                                </td>
                                <td class="small"><?= htmlspecialchars((string) ($item['usuario_nombre'] ?? '-')) ?></td>
                                <td class="text-end">
                                    <div class="btn-group" data-row-link-ignore>
                                        <?php if (!$isPapelera): ?>
                                            <a href="<?= htmlspecialchars($basePath) ?>/<?= (int) $item['id'] ?>" class="btn btn-sm btn-outline-info border-end-0" title="Ver detalle"><i class="bi bi-eye"></i></a>
                                            <a href="<?= htmlspecialchars($basePath) ?>/<?= (int) $item['id'] ?>/editar" class="btn btn-sm btn-outline-primary border-end-0" title="Editar"><i class="bi bi-pencil"></i></a>
                                            <form action="<?= htmlspecialchars($basePath) ?>/<?= (int) $item['id'] ?>/eliminar" method="POST" style="display:inline;" class="rxn-confirm-form" data-msg="¿Enviar esta tratativa a la papelera?">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar (Papelera)"><i class="bi bi-trash"></i></button>
                                            </form>
                                        <?php else: ?>
                                            <form action="<?= htmlspecialchars($basePath) ?>/<?= (int) $item['id'] ?>/restore" method="POST" style="display:inline;" class="rxn-confirm-form" data-msg="¿Confirma restaurar esta tratativa?">
                                                <button type="submit" class="btn btn-sm btn-outline-success border-end-0" title="Restaurar"><i class="bi bi-arrow-counterclockwise"></i></button>
                                            </form>
                                            <form action="<?= htmlspecialchars($basePath) ?>/<?= (int) $item['id'] ?>/force-delete" method="POST" style="display:inline;" class="rxn-confirm-form" data-msg="⚠️ ATENCIÓN: Esta acción es irreversible. Se desvincularán PDS y Presupuestos asociados. ¿Continuar?">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Destruir"><i class="bi bi-x-circle"></i></button>
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
<script src="/js/rxn-advanced-filters.js"></script>
<script src="/js/rxn-crud-search.js"></script>
<script src="/js/rxn-row-links.js"></script>
<script src="/js/rxn-shortcuts.js"></script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
