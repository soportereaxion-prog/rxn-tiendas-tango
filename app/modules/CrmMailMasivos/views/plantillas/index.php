<?php
$pageTitle = 'Plantillas de Mail Masivos - rxn_suite';
ob_start();

$flash = \App\Core\Flash::get();
?>
<div class="container-fluid mt-2 mb-5 rxn-responsive-container">
    <div class="rxn-module-header mb-4">
        <div>
            <h2 class="fw-bold mb-1"><i class="bi bi-file-earmark-text-fill"></i> Plantillas de Mail Masivos</h2>
            <p class="text-muted mb-0">Diseños HTML reutilizables con variables del reporte asociado.</p>
        </div>
        <div class="rxn-module-actions">
            <a href="/mi-empresa/crm/mail-masivos" class="btn btn-outline-secondary">← Volver</a>
            <a href="/mi-empresa/crm/mail-masivos/plantillas/crear" class="btn btn-primary fw-bold">
                <i class="bi bi-plus-lg"></i> Nueva Plantilla
            </a>
        </div>
    </div>

    <?php if ($flash): ?>
        <?php
            $flashClass = match ($flash['type'] ?? 'info') {
                'success' => 'alert-success',
                'error', 'danger' => 'alert-danger',
                'warning' => 'alert-warning',
                default => 'alert-info',
            };
        ?>
        <div class="alert <?= $flashClass ?> py-2 small"><?= htmlspecialchars($flash['message'] ?? '') ?></div>
    <?php endif; ?>

    <form method="get" class="mb-4">
        <div class="input-group" style="max-width: 480px;">
            <input type="search" name="search" class="form-control" placeholder="Buscar por nombre, asunto o descripción..." value="<?= htmlspecialchars($search ?? '') ?>">
            <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i></button>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($templates)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-file-earmark-text" style="font-size: 2.5rem; opacity: 0.4;"></i>
                    <p class="mt-3 mb-1">Todavía no hay plantillas guardadas.</p>
                    <p class="small">Creá la primera y empezá a diseñar tus envíos.</p>
                </div>
            <?php else: ?>
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Asunto</th>
                            <th>Reporte asociado</th>
                            <th>Actualizado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($templates as $t): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($t['nombre']) ?></div>
                                    <?php if (!empty($t['descripcion'])): ?>
                                        <div class="text-muted small"><?= htmlspecialchars($t['descripcion']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small"><?= htmlspecialchars((string) ($t['asunto'] ?? '')) ?></td>
                                <td>
                                    <?php if (!empty($t['report_nombre'])): ?>
                                        <span class="badge bg-secondary"><?= htmlspecialchars((string) $t['report_nombre']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small fst-italic">sin reporte</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small"><?= htmlspecialchars((string) $t['updated_at']) ?></td>
                                <td class="text-end">
                                    <a href="/mi-empresa/crm/mail-masivos/plantillas/<?= (int) $t['id'] ?>/editar" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a>
                                    <form method="post" action="/mi-empresa/crm/mail-masivos/plantillas/<?= (int) $t['id'] ?>/eliminar" class="d-inline" onsubmit="return confirm('¿Eliminar esta plantilla? La acción se puede revertir desde la base.');">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
