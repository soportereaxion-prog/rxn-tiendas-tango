<?php ob_start(); ?>
<div class="container py-4">
    <!-- Navegación -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/rxnTiendasIA/public/<?= htmlspecialchars($empresa_slug) ?>/mis-pedidos" class="text-decoration-none text-muted">Mis Pedidos</a></li>
            <li class="breadcrumb-item active text-dark fw-medium" aria-current="page">Pedido #<?= $pedido['pedido_id'] ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <!-- Resumen del Pedido -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4">
                    <h4 class="fw-bolder">Artículos Solicitados</h4>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle mb-0">
                            <thead class="border-bottom text-secondary small">
                                <tr>
                                    <th class="fw-medium pb-2">Producto</th>
                                    <th class="fw-medium pb-2 text-center">Cant.</th>
                                    <th class="fw-medium pb-2 text-end">P. Unit.</th>
                                    <th class="fw-medium pb-2 text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pedido['renglones'] as $item): ?>
                                    <tr class="border-bottom">
                                        <td class="py-3 fw-medium text-dark"><?= htmlspecialchars($item['nombre_articulo']) ?></td>
                                        <td class="py-3 text-center text-secondary"><?= $item['cantidad'] ?></td>
                                        <td class="py-3 text-end text-secondary">$<?= number_format((float)$item['precio_unitario'], 2, ',', '.') ?></td>
                                        <td class="py-3 text-end fw-bold text-dark">$<?= number_format($item['cantidad'] * $item['precio_unitario'], 2, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="border-top-2 border-dark">
                                <tr>
                                    <td colspan="3" class="text-end py-4 fw-bolder fs-5 text-dark">Total del Pedido:</td>
                                    <td class="text-end py-4 fw-bolder fs-5 text-dark">$<?= number_format((float)$pedido['total'], 2, ',', '.') ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Detalles Envío/Estado -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4 bg-light">
                <div class="card-body p-4">
                    <h5 class="fw-bolder mb-3">Información Operativa</h5>
                    
                    <div class="mb-4">
                        <span class="d-block text-secondary small fw-medium mb-1">Estado del Pedido</span>
                        <?php if ($pedido['estado_tango'] === 'pendiente_envio_tango'): ?>
                            <div class="alert alert-warning m-0 py-2 border-0 fw-medium text-center">Pendiente de Procesamiento</div>
                        <?php elseif ($pedido['estado_tango'] === 'enviado_tango'): ?>
                            <div class="alert alert-success m-0 py-2 border-0 fw-medium text-center">Aprobado y en ERP</div>
                        <?php else: ?>
                            <div class="alert alert-secondary m-0 py-2 border-0 fw-medium text-center">Revisión Interna</div>
                        <?php endif; ?>
                        
                        <?php if (!empty($pedido['tango_pedido_numero'])): ?>
                            <p class="mt-2 mb-0 small text-center text-muted">Tu orden confirmada externa es: <strong>#<?= $pedido['tango_pedido_numero'] ?></strong></p>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <span class="d-block text-secondary small fw-medium mb-1">Fecha de Registración</span>
                        <div class="fw-medium text-dark"><?= date('d/m/Y H:i:s', strtotime($pedido['pedido_fecha'])) ?></div>
                    </div>

                    <div class="mb-0">
                        <span class="d-block text-secondary small fw-medium mb-1">Dirección de Envío Principal</span>
                        <address class="fst-normal m-0 text-dark">
                            <strong><?= htmlspecialchars((string)$pedido['direccion']) ?></strong><br>
                            <?= htmlspecialchars((string)$pedido['localidad']) ?>, <?= htmlspecialchars((string)$pedido['provincia']) ?> <br>
                            CP: <?= htmlspecialchars((string)$pedido['codigo_postal']) ?><br>
                            <abbr title="Phone" class="text-decoration-none">Tel:</abbr> <?= htmlspecialchars((string)$pedido['telefono']) ?>
                        </address>
                    </div>

                    <?php if (!empty($pedido['pedido_observaciones'])): ?>
                        <div class="mt-4 pt-3 border-top">
                            <span class="d-block text-secondary small fw-medium mb-1">Tus Observaciones</span>
                            <p class="small text-muted m-0 fst-italic">"<?= htmlspecialchars($pedido['pedido_observaciones']) ?>"</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php 
$content = ob_get_clean(); 
require __DIR__ . '/../layout.php'; 
?>
