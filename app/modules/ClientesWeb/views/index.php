<!DOCTYPE html>
<html lang="es" <?= \App\Core\Helpers\UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes Web - rxnTiendasIA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: none; }
    </style>
    <link href="/rxnTiendasIA/public/css/rxn-theming.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>Clientes Web</h2>
                <p class="text-muted">Gestión de Clientes y Vínculo Comercial Tango.</p>
            </div>
            <a href="/rxnTiendasIA/public/" class="btn btn-outline-secondary">← Volver al Panel</a>
        </div>

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

        <div class="card">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="badge bg-primary text-light fs-6 py-2 px-3">👥 Total Clientes Web</span>
                    <form action="/rxnTiendasIA/public/mi-empresa/clientes" method="GET" class="d-flex" style="width: 400px;">
                        <input type="text" name="search" class="form-control form-control-sm border-info me-2" placeholder="🔎 Buscar por nombre, email o doc..." value="<?= htmlspecialchars((string)$search) ?>">
                        <button type="submit" class="btn btn-info btn-sm text-white">Buscar</button>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle table-sm" style="font-size: 0.9rem;">
                        <thead class="table-light">
                            <?php
                            $sortLink = function(string $field, string $label) use ($search, $sort, $dir) {
                                $newDir = ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC';
                                $icon = ($sort === $field) ? ($dir === 'ASC' ? ' <small>▲</small>' : ' <small>▼</small>') : '';
                                $href = "?search=" . urlencode((string)$search) . "&sort={$field}&dir={$newDir}";
                                return "<a href=\"{$href}\" class=\"text-decoration-none text-dark d-block\">{$label}{$icon}</a>";
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
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($clientes)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        No hay clientes web registrados.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($clientes as $cli): ?>
                                    <tr>
                                        <td class="fw-bold">#<?= $cli['id'] ?></td>
                                        <td>
                                            <span class="fw-bold text-dark d-block"><?= htmlspecialchars(trim($cli['nombre'] . ' ' . ($cli['apellido'] ?? ''))) ?></span>
                                            <?php if(!empty($cli['razon_social'])): ?>
                                                <small class="text-muted"><?= htmlspecialchars((string)$cli['razon_social']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><a href="mailto:<?= htmlspecialchars((string)$cli['email']) ?>" class="text-decoration-none"><?= htmlspecialchars((string)$cli['email']) ?></a></td>
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
                                        <td>
                                            <a href="/rxnTiendasIA/public/mi-empresa/clientes/<?= $cli['id'] ?>/editar" class="btn btn-sm btn-outline-primary py-0">✏️ Ver/Editar</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center pagination-sm">
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= max(1, $page - 1) ?>&search=<?= urlencode((string)$search) ?>&sort=<?= $sort ?>&dir=<?= $dir ?>">Anterior</a>
                        </li>
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode((string)$search) ?>&sort=<?= $sort ?>&dir=<?= $dir ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= min($totalPages, $page + 1) ?>&search=<?= urlencode((string)$search) ?>&sort=<?= $sort ?>&dir=<?= $dir ?>">Siguiente</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>

            </div>
        </div>
    </div>
</body>
</html>
