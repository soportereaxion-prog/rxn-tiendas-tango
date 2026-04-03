<?php ob_start(); ?>
<div class="container py-4">
    <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-4">
        <h2 class="fw-bolder m-0">Mis Pedidos</h2>
        <a href="/<?= htmlspecialchars($empresa_slug) ?>" class="btn btn-outline-dark rounded-pill px-4">Volver al Catálogo</a>
    </div>

    <?php if (empty($pedidos)): ?>
        <div class="text-center py-5">
            <h4 class="text-muted fw-normal">Aún no tienes pedidos registrados.</h4>
            <a href="/<?= htmlspecialchars($empresa_slug) ?>" class="btn btn-dark mt-3 px-4 py-2 rounded-3">Descubrir Productos</a>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle m-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="py-3 px-4 fw-semibold text-secondary"># Pedido</th>
                            <th class="py-3 px-4 fw-semibold text-secondary">Fecha</th>
                            <th class="py-3 px-4 fw-semibold text-secondary text-end">Total</th>
                            <th class="py-3 px-4 fw-semibold text-secondary text-center">Estado</th>
                            <th class="py-3 px-4 fw-semibold text-secondary rxn-row-chevron-col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidos as $p): ?>
                            <tr data-row-link="/<?= htmlspecialchars($empresa_slug) ?>/mis-pedidos/ver/<?= $p['id'] ?>">
                                <td class="px-4 fw-medium text-dark border-bottom-0">
                                    <span class="text-secondary small">ID:</span> <?= $p['id'] ?>
                                </td>
                                <td class="px-4 text-secondary border-bottom-0">
                                    <?= date('d/m/Y H:i', strtotime($p['created_at'])) ?>
                                </td>
                                <td class="px-4 text-end fw-bold border-bottom-0">
                                    $<?= number_format((float)$p['total'], 2, ',', '.') ?>
                                </td>
                                <td class="px-4 text-center border-bottom-0">
                                    <?php if ($p['estado_tango'] === 'pendiente_envio_tango'): ?>
                                        <span class="badge bg-warning text-dark px-3 py-2 rounded-pill">Procesando</span>
                                    <?php elseif ($p['estado_tango'] === 'enviado_tango'): ?>
                                        <span class="badge bg-success px-3 py-2 rounded-pill">Aprobado</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary px-3 py-2 rounded-pill">En Revisión</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 text-end border-bottom-0 rxn-row-chevron-col">
                                    <a href="/<?= htmlspecialchars($empresa_slug) ?>/mis-pedidos/ver/<?= $p['id'] ?>" class="btn btn-sm btn-light fw-medium border rounded-3 px-2 rxn-row-link-action rxn-row-chevron" title="Abrir pedido" aria-label="Abrir pedido" data-row-link-ignore>›</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php 
$content = ob_get_clean(); 
require __DIR__ . '/../layout.php'; 
?>
