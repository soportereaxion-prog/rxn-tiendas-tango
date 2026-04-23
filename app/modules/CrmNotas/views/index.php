<?php
/**
 * Notas CRM — Listado en layout split (master-detail).
 * Columna izquierda: lista de notas (con búsqueda, filtros, paginación, bulk).
 * Columna derecha: detalle de la nota seleccionada.
 *
 * Variables recibidas del controller:
 *   $notas, $search, $page, $totalPages, $totalItems, $status, $sort, $dir,
 *   $tratativaFiltroInfo, $activeNota, $activeNotaId, $indexPath, $dashboardPath, $environmentLabel
 */

$pageTitle = 'Notas CRM | ' . ($environmentLabel ?? 'App');
$usePageHeader = true;
$pageHeaderTitle = 'Gestión de Notas';
$pageHeaderSubtitle = 'Anotaciones, seguimientos e historial de clientes.';
$pageHeaderIcon = 'bi bi-journal-text';
$pageHeaderBackUrl = $dashboardPath ?? '/';
$pageHeaderBackLabel = 'Volver';

$sort = $sort ?? 'created_at';
$dir = $dir ?? 'DESC';
$status = $status ?? 'activos';
$isPapelera = $status === 'papelera';
$tratativaFiltroInfo = $tratativaFiltroInfo ?? null;
$tratativaFiltroId = $tratativaFiltroInfo['id'] ?? null;
$activeNotaId = $activeNotaId ?? null;

$buildQuery = function (array $overrides = []) use ($search, $sort, $dir, $page, $status, $tratativaFiltroId, $activeNotaId) {
    $params = [
        'search' => $search,
        'sort' => $sort,
        'dir' => $dir,
        'page' => $page,
        'status' => $status ?? 'activos',
    ];
    if ($tratativaFiltroId !== null) {
        $params['tratativa_id'] = $tratativaFiltroId;
    }
    if ($activeNotaId !== null) {
        $params['n'] = $activeNotaId;
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
<style>
    .notas-split .notas-list-item { transition: background-color .12s ease; }
    .notas-split .notas-list-row { cursor: pointer; outline: none; }
    .notas-split .notas-list-row:hover { background-color: rgba(255,255,255,0.04); }
    .notas-split .notas-list-row:focus-visible { background-color: rgba(13,110,253,0.12); box-shadow: inset 3px 0 0 #0d6efd; }
    .notas-split .notas-list-item--active > .notas-list-row { background-color: rgba(13,110,253,0.18); box-shadow: inset 3px 0 0 #0d6efd; }
    .notas-split .notas-list-scroll {
        flex: 1 1 auto;
        min-height: 260px;
        max-height: calc(100vh - 260px);
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: rgba(255,255,255,0.2) transparent;
    }
    .notas-split .notas-list-scroll::-webkit-scrollbar { width: 8px; }
    .notas-split .notas-list-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 4px; }
    .notas-split [data-notas-panel] { min-height: 400px; transition: opacity .15s ease; }
    .notas-split .min-w-0 { min-width: 0; }
</style>
<?php
$extraHead = ob_get_clean();

ob_start();
?>
<a href="<?= htmlspecialchars($indexPath) ?>/crear" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nueva Nota</a>
<a href="<?= htmlspecialchars($indexPath) ?>/importar" class="btn btn-outline-info btn-sm"><i class="bi bi-upload"></i> Importar</a>
<a href="<?= htmlspecialchars($indexPath) ?>/exportar?<?= htmlspecialchars($buildQuery()) ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-download"></i> Exportar</a>
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
            <div class="alert alert-info bg-info bg-opacity-10 border-info border-opacity-25 text-info d-flex justify-content-between align-items-center shadow-sm mb-3" role="alert">
                <div>
                    <i class="bi bi-funnel-fill"></i>
                    Filtrando notas de la <strong>Tratativa #<?= (int) $tratativaFiltroInfo['numero'] ?></strong>
                    <?php if (!empty($tratativaFiltroInfo['titulo'])): ?>
                        <span class="text-muted"> — <?= htmlspecialchars((string) $tratativaFiltroInfo['titulo']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <a href="/mi-empresa/crm/tratativas/<?= (int) $tratativaFiltroInfo['id'] ?>" class="btn btn-sm btn-outline-light"><i class="bi bi-arrow-left"></i> Volver a la tratativa</a>
                    <a href="<?= htmlspecialchars($indexPath) ?>?<?= htmlspecialchars($buildQuery(['tratativa_id' => null, 'page' => 1, 'n' => null])) ?>" class="btn btn-sm btn-outline-warning"><i class="bi bi-x-lg"></i> Quitar filtro</a>
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

        <form method="POST" id="hiddenFormBulk"></form>

        <div class="notas-split row g-3"
             data-index-path="<?= htmlspecialchars($indexPath) ?>"
             data-status="<?= htmlspecialchars($status) ?>"
             data-search="<?= htmlspecialchars($search ?? '') ?>"
             data-sort="<?= htmlspecialchars($sort) ?>"
             data-dir="<?= htmlspecialchars($dir) ?>"
             data-tratativa-id="<?= $tratativaFiltroId !== null ? (int) $tratativaFiltroId : '' ?>"
             data-active-nota-id="<?= $activeNotaId !== null ? (int) $activeNotaId : '' ?>"
             data-empresa-id="<?= (int) ($empresaId ?? 0) ?>"
             data-explicit-n="<?= !empty($hasExplicitNotaParam) ? '1' : '0' ?>">

            <aside class="col-lg-4 col-md-5">
                <div class="card shadow-sm border-0 bg-dark text-light h-100 d-flex flex-column">
                    <div class="p-2 border-bottom border-secondary border-opacity-25">
                        <form action="<?= htmlspecialchars($indexPath) ?>" method="GET" class="d-flex align-items-center gap-2" data-search-form data-notas-search-form>
                            <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                            <?php if ($tratativaFiltroId !== null): ?>
                                <input type="hidden" name="tratativa_id" value="<?= (int) $tratativaFiltroId ?>">
                            <?php endif; ?>
                            <input type="text" name="search" class="form-control form-control-sm bg-dark text-light border-secondary" value="<?= htmlspecialchars($search ?? '') ?>" placeholder='🔎 Buscar (F3)' data-search-input data-notas-search-input autocomplete="off">
                            <?php if (!empty($search)): ?>
                                <a href="<?= htmlspecialchars($indexPath) ?>?<?= htmlspecialchars($buildQuery(['search' => null, 'page' => 1, 'n' => null])) ?>" class="btn btn-sm btn-outline-secondary" title="Limpiar búsqueda"><i class="bi bi-x-lg"></i></a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <ul class="nav nav-tabs border-secondary border-opacity-25 px-2 pt-2" style="border-bottom-width: 1px;">
                        <li class="nav-item">
                            <a class="nav-link py-1 px-2 small <?= !$isPapelera ? 'active fw-bold bg-dark text-light border-secondary border-bottom-0' : 'text-muted border-0' ?>" href="<?= htmlspecialchars($indexPath) ?>?<?= htmlspecialchars($buildQuery(['status' => 'activos', 'page' => 1, 'n' => null])) ?>">
                                Activos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link py-1 px-2 small text-danger <?= $isPapelera ? 'active fw-bold bg-dark border-secondary border-bottom-0' : 'border-0' ?>" href="<?= htmlspecialchars($indexPath) ?>?<?= htmlspecialchars($buildQuery(['status' => 'papelera', 'page' => 1, 'n' => null])) ?>">
                                <i class="bi bi-trash"></i> Papelera
                            </a>
                        </li>
                    </ul>

                    <div class="px-2 py-2 border-bottom border-secondary border-opacity-25 d-flex gap-1 flex-wrap">
                        <label class="form-check-label small text-muted d-flex align-items-center gap-1 me-2" style="cursor: pointer;">
                            <input type="checkbox" id="checkAll" class="form-check-input" onclick="document.querySelectorAll('.notas-list-items .check-item').forEach(e => e.checked = this.checked);"> Todas
                        </label>
                        <?php if (!$isPapelera): ?>
                            <button type="submit" form="hiddenFormBulk" formaction="<?= htmlspecialchars($indexPath) ?>/eliminar-masivo" class="btn btn-outline-danger btn-sm rxn-confirm-form" data-msg="¿Enviar las notas seleccionadas a la papelera?" title="Papelera"><i class="bi bi-trash"></i></button>
                        <?php else: ?>
                            <button type="submit" form="hiddenFormBulk" formaction="<?= htmlspecialchars($indexPath) ?>/restore-masivo" class="btn btn-outline-success btn-sm rxn-confirm-form" data-msg="¿Restaurar las notas seleccionadas?" title="Restaurar"><i class="bi bi-arrow-counterclockwise"></i></button>
                            <button type="submit" form="hiddenFormBulk" formaction="<?= htmlspecialchars($indexPath) ?>/force-delete-masivo" class="btn btn-outline-danger btn-sm rxn-confirm-form" data-msg="⚠️ Acción irreversible. ¿Destruir definitivamente las notas seleccionadas?" title="Destruir"><i class="bi bi-x-circle"></i></button>
                        <?php endif; ?>
                    </div>

                    <div class="notas-list-scroll" data-notas-list-container>
                        <?php
                            $items = $notas ?? [];
                            include BASE_PATH . '/app/modules/CrmNotas/views/partials/list_items.php';
                        ?>
                    </div>
                </div>
            </aside>

            <section class="col-lg-8 col-md-7">
                <div class="card shadow-sm border-0 bg-dark text-light h-100">
                    <div class="card-body p-3" data-notas-panel>
                        <?php if ($activeNota !== null): ?>
                            <?php
                                $nota = $activeNota;
                                include BASE_PATH . '/app/modules/CrmNotas/views/partials/detail_panel.php';
                            ?>
                        <?php else: ?>
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-journal-text fs-1 d-block mb-3 opacity-50"></i>
                                <h5 class="text-muted">Seleccioná una nota</h5>
                                <p class="small mb-0">Usá la lista de la izquierda para ver el detalle.<br>Tip: <kbd>j</kbd> / <kbd>k</kbd> para navegar, <kbd>Enter</kbd> para editar.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>

    </main>

<?php
$content = ob_get_clean();

ob_start();
?>
    <script src="/js/rxn-advanced-filters.js"></script>
    <script src="/js/rxn-crud-search.js"></script>
    <script src="/js/crm-notas-split.js"></script>
    <script src="/js/rxn-shortcuts.js"></script>
<?php
$extraScripts = ob_get_clean();

require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
