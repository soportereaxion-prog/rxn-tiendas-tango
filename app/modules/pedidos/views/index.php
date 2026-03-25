<!DOCTYPE html>
<html lang="es" <?= \App\Core\Helpers\UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos Web - rxnTiendasIA</title>
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
                <h2>GVA21 — Pedidos Web</h2>
                <p class="text-muted">Monitor de Integración de Pedidos hacia Tango Connect.</p>
            </div>
            <a href="/rxnTiendasIA/public/mi-empresa/dashboard" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver al Panel</a>
        </div>

        <?php $flash = \App\Core\Flash::get(); ?>
        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars((string)$flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
                <?= htmlspecialchars((string)$flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <span class="badge bg-dark text-light fs-6 py-2 px-3">📦 Total Pedidos: <?= $totalItems ?></span>
                </div>
                
                <div class="d-flex justify-content-end align-items-center mb-3">
                    <form action="/rxnTiendasIA/public/mi-empresa/pedidos" method="GET" class="d-flex gap-2">
                        <select name="estado" class="form-select form-select-sm border-secondary" style="width: 150px;" onchange="this.form.submit()">
                            <option value="">Todos los Estados</option>
                            <option value="pendiente_envio_tango" <?= $estado === 'pendiente_envio_tango' ? 'selected' : '' ?>>Pendientes</option>
                            <option value="enviado_tango" <?= $estado === 'enviado_tango' ? 'selected' : '' ?>>Enviados Ok</option>
                            <option value="error_envio_tango" <?= $estado === 'error_envio_tango' ? 'selected' : '' ?>>Con Error</option>
                        </select>
                        <select name="limit" class="form-select form-select-sm border-secondary" style="width: 80px;" onchange="this.form.submit()">
                            <option value="25" <?= $limit === 25 ? 'selected' : '' ?>>25</option>
                            <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100</option>
                        </select>
                        <input type="text" name="search" class="form-control form-control-sm border-secondary" style="width: 250px;" placeholder="🔎 Buscar código, cliente, email..." value="<?= htmlspecialchars((string)$search) ?>">
                        <button type="submit" class="btn btn-secondary btn-sm text-white">Buscar</button>
                        <?php if($search || $estado): ?>
                            <a href="/rxnTiendasIA/public/mi-empresa/pedidos" class="btn btn-light btn-sm border">Limpiar</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle table-sm" style="font-size: 0.9rem;">
                        <thead class="table-light">
                            <?php
                            $sortLink = function(string $field, string $label) use ($search, $limit, $estado, $sort, $dir) {
                                $newDir = ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC';
                                $icon = ($sort === $field) ? ($dir === 'ASC' ? ' <small>▲</small>' : ' <small>▼</small>') : '';
                                $href = "?search=" . urlencode((string)$search) . "&estado=" . urlencode((string)$estado) . "&limit={$limit}&sort={$field}&dir={$newDir}";
                                return "<a href=\"{$href}\" class=\"text-decoration-none text-dark d-block\">{$label}{$icon}</a>";
                            };
                            ?>
                            <tr>
                                <th><?= $sortLink('p.id', '# Orden') ?></th>
                                <th><?= $sortLink('p.created_at', 'Fecha') ?></th>
                                <th><?= $sortLink('cliente_nombre', 'Cliente') ?></th>
                                <th>Email</th>
                                <th>Cód. Tango Asignado</th>
                                <th class="text-nowrap"><?= $sortLink('p.total', 'Total ($)') ?></th>
                                <th>Estado Integración</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($pedidos)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <div class="mb-2 fs-3">🛒</div>
                                        Aún no hay pedidos registrados o no hay coincidencias con tu búsqueda.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($pedidos as $p): ?>
                                    <tr>
                                        <td class="fw-bold text-dark">#<?= $p['id'] ?></td>
                                        <td class="text-nowrap"><small class="text-muted"><?= htmlspecialchars((string)$p['created_at']) ?></small></td>
                                        <td class="text-wrap" style="max-width: 200px;">
                                            <?= htmlspecialchars((string)trim($p['cliente_nombre'] . ' ' . $p['cliente_apellido'])) ?>
                                        </td>
                                        <td><small><?= htmlspecialchars((string)$p['cliente_email']) ?></small></td>
                                        <td>
                                            <span class="badge bg-light text-dark border font-monospace">
                                                <?= htmlspecialchars((string)($p['codigo_cliente_tango_usado'] ?: 'No Definido')) ?>
                                            </span>
                                        </td>
                                        <td class="fw-semibold text-success text-nowrap">$<?= number_format((float)$p['total'], 2, ',', '.') ?></td>
                                        <td>
                                            <?php if($p['estado_tango'] === 'enviado_tango'): ?>
                                                <span class="badge bg-success bg-opacity-75">Enviado Ok</span>
                                            <?php elseif($p['estado_tango'] === 'error_envio_tango'): ?>
                                                <span class="badge bg-danger bg-opacity-75" title="<?= htmlspecialchars((string)$p['mensaje_error']) ?>">Error Integración</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning bg-opacity-75 text-dark">Pendiente</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="/rxnTiendasIA/public/mi-empresa/pedidos/<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2 fw-medium">Ver Detalle</a>
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
                            <a class="page-link" href="?page=<?= max(1, $page - 1) ?>&search=<?= urlencode((string)$search) ?>&estado=<?= urlencode((string)$estado) ?>&limit=<?= $limit ?>&sort=<?= $sort ?>&dir=<?= $dir ?>">Anterior</a>
                        </li>
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode((string)$search) ?>&estado=<?= urlencode((string)$estado) ?>&limit=<?= $limit ?>&sort=<?= $sort ?>&dir=<?= $dir ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= min($totalPages, $page + 1) ?>&search=<?= urlencode((string)$search) ?>&estado=<?= urlencode((string)$estado) ?>&limit=<?= $limit ?>&sort=<?= $sort ?>&dir=<?= $dir ?>">Siguiente</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>

            </div>
        </div>
    </div>
</body>
</html>
