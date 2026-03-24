<?php ob_start(); ?>
<div class="container py-5 text-center">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="bg-white rounded-4 border shadow-sm p-5">
                <div class="display-1 text-success mb-4">🎉</div>
                <h1 class="fw-bold mb-3 text-dark">¡Gracias por tu compra!</h1>
                <p class="text-secondary fs-5 mb-4">
                    Tu pedido ha sido registrado con éxito bajo el número interno 
                    <strong class="text-dark">#<?= htmlspecialchars((string) $pedido_id) ?></strong>.
                </p>

                <?php if ($tango_enviado): ?>
                    <div class="alert alert-success d-inline-block rounded-pill px-4 py-2 border-0 fw-medium small mb-4">
                        <span class="me-1">📦</span> Ya estamos procesando tu pedido en nuestro sistema central.
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning d-inline-block rounded-pill px-4 py-2 border-0 fw-medium small mb-4 text-dark">
                        <span class="me-1">🕒</span> Hemos recibido tu orden, la registraremos a la brevedad en central.
                    </div>
                <?php endif; ?>

                <div class="mt-2">
                    <a href="/rxnTiendasIA/public/<?= htmlspecialchars($empresa_slug) ?>" class="btn btn-dark rounded-pill px-5 py-3 fw-bold shadow-sm">
                        Volver al inicio
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php 
$content = ob_get_clean(); 
require __DIR__ . '/layout.php'; 
?>
