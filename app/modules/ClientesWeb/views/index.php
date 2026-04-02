<?php
$pageTitle = 'RXN Tiendas IA';
ob_start();
?>
<?php
    $field = $field ?? 'all';
    $buildQuery = function (array $overrides = []) use ($search, $field, $sort, $dir, $page) {
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
    ?>
    <div class="container mt-5 rxn-responsive-container">
        <div class="rxn-module-header mb-4">
            <div>
                <h2>Clientes Web</h2>
                <p class="text-muted">Gestión de Clientes y Vínculo Comercial Tango.</p>
            </div>
            <div class="rxn-module-actions">
                <?php require BASE_PATH . '/app/shared/views/components/user_action_menu.php'; ?>
                <a href="/rxnTiendasIA/public/mi-empresa/ayuda" class="btn btn-outline-info" target="_blank" rel="noopener noreferrer"><i class="bi bi-question-circle"></i> Ayuda</a>
                <a href="/rxnTiendasIA/public/mi-empresa/dashboard" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver al Panel</a>
            </div>
        </div>

        <?php
        $moduleNotesKey = 'clientes_web';
        $moduleNotesLabel = 'Clientes Web';
        require BASE_PATH . '/app/shared/views/components/module_notes_panel.php';
        ?>

        <?php $flash_success = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']); ?>
        <?php $flash_error = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']); ?>
        
        <?php if ($flash_success): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <strong>✔</strong> <?= htmlspecialchars((string)$flash_success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($flash_error): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <strong>⚠️</strong> <?= htmlspecialchars((string)$flash_error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php $isPapelera = ($status ?? 'activos') === 'papelera'; ?>
        
        <ul class="nav nav-tabs mb-4 rxn-crud-tabs">
            <li class="nav-item">
                <a class="nav-link <?= !$isPapelera ? 'active fw-bold' : '' ?>" href="<?= htmlspecialchars((string) ($ui['basePath'] ?? '/rxnTiendasIA/public/mi-empresa/clientes')) ?>?<?= htmlspecialchars($buildQuery(['status' => 'activos', 'page' => 1])) ?>">
                    Activos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-danger <?= $isPapelera ? 'active fw-bold' : '' ?>" href="<?= htmlspecialchars((string) ($ui['basePath'] ?? '/rxnTiendasIA/public/mi-empresa/clientes')) ?>?<?= htmlspecialchars($buildQuery(['status' => 'papelera', 'page' => 1])) ?>">
                    <i class="bi bi-trash"></i> Papelera
                </a>
            </li>
        </ul>

        <div class="card rxn-crud-card">
            <div class="card-body p-4">
                <div class="rxn-toolbar-split mb-3">
                    <span class="badge bg-primary text-light fs-6 py-2 px-3">👥 Total Clientes Web</span>
                    <form action="/rxnTiendasIA/public/mi-empresa/clientes" method="GET" class="rxn-filter-form justify-content-end flex-md-nowrap ms-md-auto" style="width: 720px; max-width: 100%;" data-search-form>
                        <input type="hidden" name="search" value="<?= htmlspecialchars((string) $search) ?>" data-search-hidden>
                        <select name="field" class="form-select form-select-sm border-info rxn-filter-compact rxn-search-field-wrap" style="width: 150px;" data-search-field>
                            <option value="all" <?= $field === 'all' ? 'selected' : '' ?>>Todos los campos</option>
                            <option value="id" <?= $field === 'id' ? 'selected' : '' ?>>ID</option>
                            <option value="nombre" <?= $field === 'nombre' ? 'selected' : '' ?>>Nombre</option>
                            <option value="email" <?= $field === 'email' ? 'selected' : '' ?>>Email</option>
                            <option value="documento" <?= $field === 'documento' ? 'selected' : '' ?>>Documento</option>
                            <option value="codigo_tango" <?= $field === 'codigo_tango' ? 'selected' : '' ?>>Cod. Tango</option>
                        </select>
                        <div class="rxn-search-input-wrap rxn-filter-grow">
                            <input type="text" class="form-control form-control-sm border-info" placeholder='🔎 Presioná F3 o "/" para buscar' value="<?= htmlspecialchars((string)$search) ?>" data-search-input data-suggestions-url="/rxnTiendasIA/public/mi-empresa/clientes/sugerencias" autocomplete="off">
                            <div class="rxn-search-suggestions d-none" data-search-suggestions></div>
                        </div>
                        <button type="submit" class="btn btn-info btn-sm text-white">Buscar</button>
                    </form>
                </div>
                <div class="form-text rxn-search-help text-md-end mb-3">Se sugieren coincidencias mientras escribis, pero el listado solo se filtra al buscar o presionar Enter.</div>

                <?php if (!$isPapelera): ?>
                <div class="mb-3">
                    <button type="submit" form="hiddenFormBulk" formaction="/rxnTiendasIA/public/mi-empresa/clientes/eliminar-masivo" class="btn btn-outline-danger btn-sm rxn-confirm-form" data-msg="¿Enviar los clientes seleccionados a la papelera?"><i class="bi bi-trash"></i> Eliminar Seleccionados</button>
                </div>
                <?php else: ?>
                <div class="mb-3 d-flex gap-2">
                    <button type="submit" form="hiddenFormBulk" formaction="/rxnTiendasIA/public/mi-empresa/clientes/restore-masivo" class="btn btn-outline-success btn-sm rxn-confirm-form" data-msg="¿Restaurar los clientes seleccionados?"><i class="bi bi-arrow-counterclockwise"></i> Restaurar Seleccionados</button>
                    <button type="submit" form="hiddenFormBulk" formaction="/rxnTiendasIA/public/mi-empresa/clientes/force-delete-masivo" class="btn btn-outline-danger btn-sm rxn-confirm-form" data-msg="⚠️ ATENCIÓN: Acción irreversible. ¿Destruir definitivamente los clientes seleccionados?"><i class="bi bi-x-circle"></i> Destruir Seleccionados</button>
                </div>
                <?php endif; ?>

                <form id="hiddenFormBulk" method="POST">

                    <div class="table-responsive rxn-table-responsive">
                        <table class="table table-hover align-middle table-sm rxn-crud-table" style="font-size: 0.9rem;">
                            <thead class="table-light">
                                <?php
                                $sortLink = function(string $fieldName, string $label) use ($buildQuery, $sort, $dir) {
                                    $newDir = ($sort === $fieldName && $dir === 'ASC') ? 'DESC' : 'ASC';
                                    $icon = ($sort === $fieldName) ? ($dir === 'ASC' ? '▲' : '▼') : '';
                                    $href = '?' . $buildQuery(['sort' => $fieldName, 'dir' => $newDir]);
                                    return "<a href=\"{$href}\" class=\"rxn-sort-link\"><span>{$label}</span><span class=\"rxn-sort-indicator\">{$icon}</span></a>";
                                };
                                ?>
                                <tr>
                                    <th style="width: 40px;" class="text-center">
                                        <input type="checkbox" class="form-check-input" id="bulk-select-all" aria-label="Seleccionar todos" onclick="document.querySelectorAll('.rxn-bulk-checkbox').forEach(e => e.checked = this.checked);">
                                    </th>
                                    <th><?= $sortLink('id', 'ID') ?></th>
                                    <th><?= $sortLink('nombre', 'Nombre/Razón Social') ?></th>
                                    <th><?= $sortLink('email', 'Email') ?></th>
                                    <th><?= $sortLink('documento', 'Documento') ?></th>
                                    <th><?= $sortLink('codigo_tango', 'Cod. Tango') ?></th>
                                    <th><?= $sortLink('id_gva14_tango', 'Tango Resuelto') ?></th>
                                    <th><?= $sortLink('created_at', 'Alta') ?></th>
                                    <th class="rxn-actions-col text-end">Acciones</th>
                                </tr>
                            </thead>
                        <tbody>
                            <?php if(empty($clientes)): ?>
                                <tr>
                                    <td colspan="8" class="rxn-empty-state text-muted">
                                        No hay clientes web registrados.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($clientes as $cli): ?>
                                    <tr data-row-link="/rxnTiendasIA/public/mi-empresa/clientes/<?= $cli['id'] ?>/editar" class="<?= $isPapelera ? 'rxn-row-deleted' : '' ?>">
                                        <td class="text-center" data-row-link-ignore>
                                            <input class="form-check-input rxn-bulk-checkbox" type="checkbox" name="ids[]" value="<?= $cli['id'] ?>" form="hiddenFormBulk" aria-label="Seleccionar fila">
                                        </td>
                                        <td class="fw-bold">#<?= $cli['id'] ?></td>
                                        <td>
                                            <span class="fw-bold text-dark d-block"><?= htmlspecialchars(trim($cli['nombre'] . ' ' . ($cli['apellido'] ?? ''))) ?></span>
                                            <?php if(!empty($cli['razon_social'])): ?>
                                                <small class="text-muted"><?= htmlspecialchars((string)$cli['razon_social']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><a href="mailto:<?= htmlspecialchars((string)$cli['email']) ?>" class="text-decoration-none" data-row-link-ignore><?= htmlspecialchars((string)$cli['email']) ?></a></td>
                                        <td><?= htmlspecialchars((string)$cli['documento']) ?: '--' ?></td>
                                        <td>
                                            <?php if($cli['codigo_tango']): ?>
                                                <span class="badge bg-secondary"><?= htmlspecialchars((string)$cli['codigo_tango']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted mb-0">--</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if($cli['id_gva14_tango']): ?>
                                                <span class="badge bg-success">✔ GVA14: <?= $cli['id_gva14_tango'] ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Pendiente</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-nowrap"><small class="text-secondary"><?= date('d/m/Y H:i', strtotime($cli['created_at'])) ?></small></td>
                                        <td class="rxn-actions-col text-end" data-row-link-ignore>
                                            <div class="d-inline-flex gap-1 align-items-center">
                                                <a href="/rxnTiendasIA/public/mi-empresa/clientes/<?= $cli['id'] ?>/editar" class="btn btn-sm btn-outline-primary" title="Editar">
                                                    <img src="/rxnTiendasIA/public/icons/pencil-square.svg" alt="Editar" width="14" height="14">
                                                </a>

                                                <?php if (!$isPapelera): ?>
                                                    <form method="POST" action="/rxnTiendasIA/public/mi-empresa/clientes/<?= $cli['id'] ?>/eliminar" class="d-inline rxn-confirm-form">
                                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Mover a Papelera"><img src="/rxnTiendasIA/public/icons/trash.svg" alt="Eliminar" width="14" height="14"></button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" action="/rxnTiendasIA/public/mi-empresa/clientes/<?= $cli['id'] ?>/restore" class="d-inline rxn-confirm-form" data-confirm-msg="¿Restaurar este cliente web?">
                                                        <button type="button" class="btn btn-sm btn-outline-success" title="Restaurar"><i class="bi bi-arrow-counterclockwise"></i></button>
                                                    </form>
                                                    <form method="POST" action="/rxnTiendasIA/public/mi-empresa/clientes/<?= $cli['id'] ?>/force-delete" class="d-inline rxn-confirm-form" data-confirm-msg="¿Destruir definitivamente este cliente web? Esta acción no se puede deshacer.">
                                                        <button type="button" class="btn btn-sm btn-danger" title="Destruir"><i class="bi bi-x-circle"></i></button>
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
<script src="/rxnTiendasIA/public/js/rxn-crud-search.js"></script>
    <script src="/rxnTiendasIA/public/js/rxn-row-links.js"></script>
    <script src="/rxnTiendasIA/public/js/rxn-confirm-modal.js"></script>

    <script src="/rxnTiendasIA/public/js/rxn-shortcuts.js"></script>
<?php
$extraScripts = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
