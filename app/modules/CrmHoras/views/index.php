<?php
$pageTitle = 'Listado de Horas';
ob_start();
?>
<div class="container-fluid py-3 crm-horas-shell">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 fw-bold mb-0"><i class="bi bi-list-ul text-info"></i> Listado de Horas</h1>
            <div class="text-muted small"><?= (int) $total ?> turnos en total</div>
        </div>
        <a href="/mi-empresa/crm/horas" class="btn btn-outline-secondary btn-sm" data-rxn-back="/mi-empresa/crm/horas">
            <i class="bi bi-arrow-left"></i> Volver al turnero
        </a>
    </div>

    <form method="GET" class="card rxn-form-card mb-3">
        <div class="card-body p-3">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label small mb-1">Desde</label>
                    <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small mb-1">Hasta</label>
                    <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>" class="form-control form-control-sm">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small mb-1">Operador (ID)</label>
                    <input type="number" name="usuario_id" value="<?= $usuarioFilter ? (int) $usuarioFilter : '' ?>" class="form-control form-control-sm" placeholder="Todos">
                </div>
                <div class="col-12 col-md-3 d-flex gap-1">
                    <button class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-search"></i> Filtrar</button>
                    <a href="/mi-empresa/crm/horas/listado" class="btn btn-outline-secondary btn-sm">Limpiar</a>
                </div>
            </div>
        </div>
    </form>

    <div class="card rxn-form-card">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Operador</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Duración</th>
                        <th>Modo</th>
                        <th>Estado</th>
                        <th>Concepto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">Sin turnos para los filtros aplicados.</td></tr>
                    <?php else: foreach ($items as $i): ?>
                        <?php
                        $duracion = '—';
                        if ($i['ended_at']) {
                            try {
                                $sec = (new DateTimeImmutable((string) $i['ended_at']))->getTimestamp() - (new DateTimeImmutable((string) $i['started_at']))->getTimestamp();
                                $h = intdiv(max(0, $sec), 3600);
                                $m = intdiv(max(0, $sec) % 3600, 60);
                                $duracion = sprintf('%dh %02dm', $h, $m);
                            } catch (\Throwable) {}
                        }
                        ?>
                        <tr>
                            <td><?= (int) $i['id'] ?></td>
                            <td><?= htmlspecialchars((string) ($i['usuario_nombre'] ?? '—')) ?></td>
                            <td><?= htmlspecialchars((new DateTime((string) $i['started_at']))->format('d/m/Y H:i')) ?></td>
                            <td><?= $i['ended_at'] ? htmlspecialchars((new DateTime((string) $i['ended_at']))->format('d/m/Y H:i')) : '<em class="text-muted">abierto</em>' ?></td>
                            <td><?= $duracion ?></td>
                            <td><span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars((string) $i['modo']) ?></span></td>
                            <td>
                                <?php if ($i['estado'] === 'abierto'): ?>
                                    <span class="badge bg-success">abierto</span>
                                <?php elseif ($i['estado'] === 'anulado'): ?>
                                    <span class="badge bg-danger">anulado</span>
                                <?php else: ?>
                                    <span class="badge bg-info text-dark">cerrado</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-truncate" style="max-width: 240px;" title="<?= htmlspecialchars((string) ($i['concepto'] ?? '')) ?>">
                                <?= htmlspecialchars((string) ($i['concepto'] ?? '—')) ?>
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
                        <a class="page-link" href="?<?= http_build_query(array_filter(['desde' => $desde, 'hasta' => $hasta, 'usuario_id' => $usuarioFilter, 'page' => $p])) ?>"><?= $p ?></a>
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
