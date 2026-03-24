<?php ob_start(); ?>
<div class="container py-4">
    <div class="row mb-4">
        <div class="col">
            <h1 class="fw-bold fs-2"><span class="me-2">💳</span>Checkout</h1>
            <p class="text-secondary">Completá tus datos para finalizar la compra.</p>
        </div>
    </div>

    <form action="/rxnTiendasIA/public/<?= htmlspecialchars($empresa_slug) ?>/checkout/confirmar" method="POST">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="bg-white rounded-4 border shadow-sm p-4 mb-4">
                    <h5 class="fw-bold mb-4">Datos de Contacto</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-medium">Nombre *</label>
                            <input type="text" name="nombre" class="form-control form-control-lg bg-light border-0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-medium">Apellido</label>
                            <input type="text" name="apellido" class="form-control form-control-lg bg-light border-0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-medium">E-mail *</label>
                            <input type="email" name="email" class="form-control form-control-lg bg-light border-0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-medium">Teléfono</label>
                            <input type="text" name="telefono" class="form-control form-control-lg bg-light border-0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-medium">Documento (DNI/CUIT)</label>
                            <input type="text" name="documento" class="form-control form-control-lg bg-light border-0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-medium">Razón Social</label>
                            <input type="text" name="razon_social" class="form-control form-control-lg bg-light border-0">
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-4 border shadow-sm p-4 mb-4">
                    <h5 class="fw-bold mb-4">Datos de Envío</h5>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label text-secondary small fw-medium">Dirección Completa</label>
                            <input type="text" name="direccion" class="form-control form-control-lg bg-light border-0">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label text-secondary small fw-medium">Localidad</label>
                            <input type="text" name="localidad" class="form-control form-control-lg bg-light border-0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-secondary small fw-medium">Provincia</label>
                            <input type="text" name="provincia" class="form-control form-control-lg bg-light border-0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-secondary small fw-medium">C.P.</label>
                            <input type="text" name="codigo_postal" class="form-control form-control-lg bg-light border-0">
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-4 border shadow-sm p-4">
                    <h5 class="fw-bold mb-4">Observaciones del Pedido</h5>
                    <textarea name="observaciones" class="form-control bg-light border-0" rows="3" placeholder="Ej: Entregar por la tarde, dejar en portería..."></textarea>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="bg-white rounded-4 border shadow-sm p-4 sticky-top" style="top: 100px;">
                    <h5 class="fw-bold mb-4">Resumen</h5>
                    <div class="mb-4">
                        <?php foreach ($items as $item): ?>
                            <div class="d-flex justify-content-between mb-2 text-secondary small border-bottom pb-2">
                                <span><?= htmlspecialchars((string)$item['nombre']) ?> <b>x<?= $item['cantidad'] ?></b></span>
                                <span>$<?= number_format(((float)$item['precio_unitario'] * (int)$item['cantidad']), 2, ',', '.') ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-4 align-items-center">
                        <span class="fw-bold fs-5 text-dark">Total</span>
                        <span class="fw-bold fs-4 text-dark">$<?= number_format((float)$total, 2, ',', '.') ?></span>
                    </div>

                    <button type="submit" class="btn btn-dark w-100 py-3 rounded-pill fw-bold fs-5 shadow-sm">
                        Confirmar Pedido ✔️
                    </button>
                    
                    <div class="mt-3 text-center">
                        <a href="/rxnTiendasIA/public/<?= htmlspecialchars($empresa_slug) ?>/carrito" class="text-decoration-none text-muted small fw-medium">
                            ← Volver al carrito
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
<?php 
$content = ob_get_clean(); 
require __DIR__ . '/layout.php'; 
?>
