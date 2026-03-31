<?php
use App\Core\Helpers\UIHelper;
use App\Core\View;
$ui = isset($environmentLabel) ? compact('environmentLabel', 'dashboardPath') : [];
?>
<!DOCTYPE html>
<html lang="es" <?= UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notas CRM | <?= htmlspecialchars($environmentLabel ?? 'App') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="/rxnTiendasIA/public/css/rxn-theming.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100 rxn-launcher-shell pt-3">
    <?php View::render('app/shared/views/components/backoffice_user_banner.php', $ui); ?>

    <main class="container-fluid flex-grow-1 px-4 mb-5" style="max-width: 1400px;">
        <div class="rxn-module-header mb-4 pb-3 border-bottom border-secondary border-opacity-25">
            <div>
                <h1 class="h3 fw-bold mb-1"><i class="bi bi-journal-text"></i> Gestión de Notas</h1>
                <p class="text-muted mb-0">Anotaciones, seguimientos e historial de clientes.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= htmlspecialchars($indexPath) ?>/crear" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nueva Nota</a>
                <a href="<?= htmlspecialchars($indexPath) ?>/importar" class="btn btn-outline-info"><i class="bi bi-upload"></i> Importar CSV/Excel</a>
                <a href="<?= htmlspecialchars($dashboardPath ?? '/rxnTiendasIA/public/') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
            </div>
        </div>

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
                <form action="<?= htmlspecialchars($indexPath) ?>" method="GET" class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label text-muted small mb-1">Buscar Notas</label>
                        <input type="text" name="search" class="form-control bg-dark text-light border-secondary" value="<?= htmlspecialchars($search ?? '') ?>" placeholder="Título, contenido, cliente...">
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

        <div class="card shadow-sm border-0 bg-dark text-light">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
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
                                <tr style="cursor: pointer;" onclick="if(event.target.closest('.btn-group') === null) { window.location.href = '<?= htmlspecialchars($indexPath) ?>/<?= $item['id'] ?>'; }" class="rxn-hover-bg">
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
                                        <div class="btn-group">
                                            <a href="<?= htmlspecialchars($indexPath) ?>/<?= $item['id'] ?>" class="btn btn-sm btn-outline-primary" title="Ver Nota"><i class="bi bi-eye"></i></a>
                                            <a href="<?= htmlspecialchars($indexPath) ?>/<?= $item['id'] ?>/editar" class="btn btn-sm btn-outline-info" title="Editar"><i class="bi bi-pencil"></i></a>
                                            <form action="<?= htmlspecialchars($indexPath) ?>/<?= $item['id'] ?>/copiar" method="POST" style="display:inline;" onsubmit="return confirm('¿Copiar nota (duplicar registro)?');">
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Copiar"><i class="bi bi-files"></i></button>
                                            </form>
                                            <form action="<?= htmlspecialchars($indexPath) ?>/<?= $item['id'] ?>/eliminar" method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar nota permanentemente?');">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                                            </form>
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
                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>" class="btn btn-sm btn-outline-secondary">Anterior</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>" class="btn btn-sm btn-outline-secondary">Siguiente</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
