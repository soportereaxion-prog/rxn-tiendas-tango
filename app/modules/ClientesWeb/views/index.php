<!DOCTYPE html>
<html lang="es" <?= \App\Core\Helpers\UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes Web - rxnTiendasIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/rxnTiendasIA/public/css/rxn-theming.css" rel="stylesheet">
</head>
<body class="rxn-page-shell">
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
                            <input type="text" class="form-control form-control-sm border-info" placeholder="🔎 Buscar por nombre, email o doc..." value="<?= htmlspecialchars((string)$search) ?>" data-search-input data-suggestions-url="/rxnTiendasIA/public/mi-empresa/clientes/sugerencias" autocomplete="off">
                            <div class="rxn-search-suggestions d-none" data-search-suggestions></div>
                        </div>
                        <button type="submit" class="btn btn-info btn-sm text-white">Buscar</button>
                    </form>
                </div>
                <div class="form-text rxn-search-help text-md-end">Se sugieren coincidencias mientras escribis, pero el listado solo se filtra al buscar o presionar Enter.</div>

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
                                <th><?= $sortLink('id', 'ID') ?></th>
                                <th><?= $sortLink('nombre', 'Nombre/Razón Social') ?></th>
                                <th><?= $sortLink('email', 'Email') ?></th>
                                <th><?= $sortLink('documento', 'Documento') ?></th>
                                <th><?= $sortLink('codigo_tango', 'Cod. Tango') ?></th>
                                <th><?= $sortLink('id_gva14_tango', 'Tango Resuelto') ?></th>
                                <th><?= $sortLink('created_at', 'Alta') ?></th>
                                <th class="rxn-row-chevron-col"></th>
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
                                    <tr data-row-link="/rxnTiendasIA/public/mi-empresa/clientes/<?= $cli['id'] ?>/editar">
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
                                        <td class="rxn-row-chevron-col">
                                            <a href="/rxnTiendasIA/public/mi-empresa/clientes/<?= $cli['id'] ?>/editar" class="btn btn-sm btn-outline-primary py-0 px-2 rxn-row-link-action rxn-row-chevron" title="Abrir cliente" aria-label="Abrir cliente" data-row-link-ignore>›</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

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
    <script src="/rxnTiendasIA/public/js/rxn-crud-search.js"></script>
    <script src="/rxnTiendasIA/public/js/rxn-row-links.js"></script>
</body>
</html>
