<?php
$pageTitle = 'Notas CRM | ' . ($environmentLabel ?? 'App');
$usePageHeader = true;
$pageHeaderTitle = 'Gestión de Notas';
$pageHeaderSubtitle = 'Anotaciones, seguimientos e historial de clientes.';
$pageHeaderIcon = 'bi bi-journal-text';
$pageHeaderBackUrl = $dashboardPath ?? '/';
$pageHeaderBackLabel = 'Volver';

ob_start();
?>
<a href="<?= htmlspecialchars($indexPath) ?>/crear" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nueva Nota</a>
<a href="<?= htmlspecialchars($indexPath) ?>/importar" class="btn btn-outline-info"><i class="bi bi-upload"></i> Importar CSV/Excel</a>
<a href="<?= htmlspecialchars($indexPath) ?>/exportar<?= !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>" class="btn btn-outline-success"><i class="bi bi-download"></i> Exportar a Excel</a>
<?php
$pageHeaderActions = ob_get_clean();

ob_start();
?>

        <?php 
        $moduleNotesKey = 'crm_notas';
        $moduleNotesLabel = 'CRM - Notas';
        require BASE_PATH . '/app/shared/views/components/module_notes_panel.php'; 
        ?>

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
                    <div class="col-md-4">
                        <label class="form-label text-muted small mb-1">Ordenar por</label>
                        <select name="sort" class="form-select bg-dark text-light border-secondary">
                            <option value="created_at_desc" <?= (empty($_GET['sort']) || $_GET['sort'] === 'created_at_desc') ? 'selected' : '' ?>>Más Recientes</option>
                            <option value="created_at_asc" <?= ($_GET['sort'] ?? '') === 'created_at_asc' ? 'selected' : '' ?>>Más Antiguas</option>
                            <option value="id_desc" <?= ($_GET['sort'] ?? '') === 'id_desc' ? 'selected' : '' ?>>ID Descendente</option>
                            <option value="id_asc" <?= ($_GET['sort'] ?? '') === 'id_asc' ? 'selected' : '' ?>>ID Ascendente</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter"></i> Aplicar</button>
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
                <a class="nav-link <?= !$isPapelera ? 'active fw-bold bg-dark text-light border-secondary border-bottom-0' : 'text-muted border-0 hover-text-light' ?>" href="<?= htmlspecialchars($indexPath) ?>?status=activos&search=<?= urlencode($search ?? '') ?>">
                    Activos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-danger <?= $isPapelera ? 'active fw-bold bg-dark border-secondary border-bottom-0' : 'border-0 hover-text-danger' ?>" href="<?= htmlspecialchars($indexPath) ?>?status=papelera&search=<?= urlencode($search ?? '') ?>">
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
                        <tr>
                            <th style="width: 40px;"><input type="checkbox" id="checkAll" class="form-check-input" onclick="document.querySelectorAll('.check-item').forEach(e => e.checked = this.checked);"></th>
                            <th style="width: 50px;">ID</th>
                            <th>Título</th>
                            <th>Cliente Vinculado</th>
                            <th>Tags</th>
                            <th style="width: 150px;">Fecha</th>
                            <th style="width: 120px;" class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($notas)): ?>
                            <tr><td colspan="6" class="text-center p-4 text-muted">No existen notas registradas.</td></tr>
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
                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status ?? 'activos') ?>" class="btn btn-sm btn-outline-secondary">Anterior</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status ?? 'activos') ?>" class="btn btn-sm btn-outline-secondary">Siguiente</a>
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
    <script src="/js/rxn-crud-search.js"></script>
    <script src="/js/rxn-row-links.js"></script>
    <script src="/js/rxn-shortcuts.js"></script>
<?php
$extraScripts = ob_get_clean();

require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
