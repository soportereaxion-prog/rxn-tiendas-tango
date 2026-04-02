<?php ob_start(); ?>
<div class="container py-4">
    <div class="row mb-4">
        <div class="col">
            <h1 class="fw-bold fs-2"><span class="me-2">🛒</span>Mi Carrito</h1>
            <p class="text-secondary">Revisa tus productos antes de continuar.</p>
        </div>
    </div>

    <?php if (empty($items)): ?>
        <div class="text-center py-5  rounded-4 border shadow-sm">
            <div class="display-1 text-muted opacity-25 mb-4">🛍️</div>
            <h3 class="text-dark fw-bold">Tu carrito está vacío</h3>
            <p class="text-secondary mb-4">¡Animate a sumar productos de nuestro catálogo!</p>
            <a href="/rxnTiendasIA/public/<?= htmlspecialchars($empresa_slug) ?>" class="btn btn-dark px-4 py-2 rounded-pill fw-medium">
                Volver a la tienda
            </a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class=" rounded-4 border shadow-sm overflow-hidden">
                    <table class="table mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="border-0 ps-4 py-3 text-secondary fw-medium">Producto</th>
                                <th class="border-0 py-3 text-secondary fw-medium text-center" style="width: 15%;">Cantidad</th>
                                <th class="border-0 py-3 text-secondary fw-medium text-end" style="width: 20%;">Subtotal</th>
                                <th class="border-0 pe-4 py-3 text-secondary fw-medium text-end" style="width: 10%;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="ps-4 py-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="bg-light rounded p-2 text-center" style="width: 60px; height: 60px;">
                                            <span class="fs-3 opacity-50">📷</span>
                                        </div>
                                        <div>
                                            <h6 class="mb-1 text-dark fw-bold"><?= htmlspecialchars((string)$item['nombre']) ?></h6>
                                            <small class="text-muted d-block">$<?= number_format((float)$item['precio_unitario'], 2, ',', '.') ?> c/u</small>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 text-center">
                                    <form action="/rxnTiendasIA/public/<?= htmlspecialchars($empresa_slug) ?>/carrito/update" method="POST" class="d-flex justify-content-center">
                                        <input type="hidden" name="articulo_id" value="<?= $item['articulo_id'] ?>">
                                        <div class="input-group input-group-sm rounded-3 shadow-none border" style="width: 100px;">
                                            <input type="number" name="cantidad" value="<?= $item['cantidad'] ?>" class="form-control text-center border-0 fw-bold bg-transparent" min="1" max="99" onchange="this.form.submit()">
                                        </div>
                                    </form>
                                </td>
                                <td class="py-4 text-end fw-bold text-dark fs-5">
                                    $<?= number_format(((float)$item['precio_unitario'] * (int)$item['cantidad']), 2, ',', '.') ?>
                                </td>
                                <td class="pe-4 py-4 text-end">
                                    <form action="/rxnTiendasIA/public/<?= htmlspecialchars($empresa_slug) ?>/carrito/remove" method="POST" class="d-inline">
                                        <input type="hidden" name="articulo_id" value="<?= $item['articulo_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger border-0 rounded-circle" title="Eliminar">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-lg-4">
                <div class=" rounded-4 border shadow-sm p-4 sticky-top" style="top: 100px;">
                    <h5 class="fw-bold mb-4">Resumen de Compra</h5>
                    
                    <div class="d-flex justify-content-between mb-3 text-secondary">
                        <span>Subtotal (<?= array_sum(array_column($items, 'cantidad')) ?> items)</span>
                        <span>$<?= number_format((float)$total, 2, ',', '.') ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-4 text-secondary pb-3 border-bottom">
                        <span>Envío</span>
                        <span>A calcular</span>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-4 align-items-center">
                        <span class="fw-bold fs-5 text-dark">Total estimado</span>
                        <span class="fw-bold fs-4 text-dark">$<?= number_format((float)$total, 2, ',', '.') ?></span>
                    </div>

                    <a href="/rxnTiendasIA/public/<?= htmlspecialchars($empresa_slug) ?>/checkout" class="btn btn-dark w-100 py-3 rounded-pill fw-bold d-block text-center text-decoration-none shadow-sm">
                        Iniciar Pago 💳
                    </a>
                    <div class="mt-3 text-center">
                        <a href="/rxnTiendasIA/public/<?= htmlspecialchars($empresa_slug) ?>" class="text-decoration-none text-muted small fw-medium">
                            ← Seguir comprando
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php 
$content = ob_get_clean(); 
require __DIR__ . '/layout.php'; 
?>
