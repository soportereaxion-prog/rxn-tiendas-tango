<?php
$pageTitle = 'Audit log — Horas';
ob_start();
?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 fw-bold mb-0"><i class="bi bi-shield-lock text-warning"></i> Audit log — Horas</h1>
            <div class="text-muted small"><?= (int) $total ?> registros · <em>solo super admin</em></div>
        </div>
        <a href="/admin/dashboard" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <div class="card rxn-form-card">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Cuándo</th>
                        <th>Acción</th>
                        <th>Turno</th>
                        <th>Operador (dueño)</th>
                        <th>Realizado por</th>
                        <th>Motivo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Sin registros de auditoría todavía.</td></tr>
                    <?php else: foreach ($items as $i): ?>
                        <tr>
                            <td class="small"><?= htmlspecialchars((new DateTime((string) $i['performed_at']))->format('d/m/Y H:i:s')) ?></td>
                            <td><span class="badge bg-warning text-dark"><?= htmlspecialchars((string) $i['accion']) ?></span></td>
                            <td>#<?= (int) $i['hora_id'] ?></td>
                            <td class="small"><?= htmlspecialchars((string) ($i['owner_nombre'] ?? '#' . $i['owner_user_id'])) ?></td>
                            <td class="small"><?= htmlspecialchars((string) ($i['performed_by_nombre'] ?? '#' . $i['performed_by'])) ?></td>
                            <td class="small text-truncate" style="max-width: 320px;" title="<?= htmlspecialchars((string) ($i['motivo'] ?? '')) ?>">
                                <?= htmlspecialchars((string) ($i['motivo'] ?? '—')) ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="mt-3">
            <ul class="pagination justify-content-center">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require BASE_PATH . '/app/shared/views/admin_layout.php';
?>
