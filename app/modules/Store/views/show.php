<?php ob_start(); 
$price = $articulo->precio_lista_1 ?? $articulo->precio ?? null;
$hasStock = ((float)$articulo->stock_actual) > 0;
?>
<div class="container py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/rxnTiendasIA/public/<?= htmlspecialchars($empresa_slug) ?>" class="text-decoration-none text-muted">Catálogo</a></li>
            <li class="breadcrumb-item active text-dark fw-medium" aria-current="page"><?= htmlspecialchars((string)$articulo->nombre) ?></li>
        </ol>
    </nav>

    <div class="row g-5">
        <div class="col-md-6 mb-4 mb-md-0">
            <div class="bg-white border rounded-4 d-flex align-items-center justify-content-center shadow-sm overflow-hidden" style="height: 450px;">
                <?php if (!empty($articulo->imagen_principal)): ?>
                    <img src="/rxnTiendasIA/public<?= htmlspecialchars((string)$articulo->imagen_principal) ?>" alt="<?= htmlspecialchars((string)$articulo->nombre) ?>" class="w-100 h-100" style="object-fit: cover;">
                <?php else: ?>
                    <img src="/rxnTiendasIA/public/assets/img/producto-default.png" alt="Sin imagen" class="w-100 h-100" style="object-fit: contain; padding: 40px; opacity: 0.5;">
                <?php endif; ?>
            </div>
        </div>
        
        <div class="col-md-6">
            <span class="badge bg-secondary mb-3 fs-6 px-3 py-2 fw-normal opacity-75">SKU: <?= htmlspecialchars((string)$articulo->codigo_externo) ?></span>
            <h1 class="fw-bolder mb-3 text-dark"><?= htmlspecialchars((string)$articulo->nombre) ?></h1>
            
            <div class="mb-4">
                <?php if ($price !== null): ?>
                    <span class="display-5 fw-bold text-dark">$<?= number_format((float)$price, 2, ',', '.') ?></span>
                <?php else: ?>
                    <span class="display-6 text-muted">Precio no disponible</span>
                <?php endif; ?>
            </div>

            <div class="mb-4 d-flex align-items-center gap-3">
                <span class="fw-medium text-secondary">Disponibilidad:</span>
                <?php if ($hasStock): ?>
                    <span class="text-success fw-bold d-flex align-items-center gap-1">
                        <span style="font-size:1.2rem;">●</span> En Stock (<?= (float)$articulo->stock_actual ?> u.)
                    </span>
                <?php else: ?>
                    <span class="text-warning fw-bold d-flex align-items-center gap-1">
                        <span style="font-size:1.2rem;">●</span> Sin Stock 
                    </span>
                <?php endif; ?>
            </div>

            <p class="text-secondary lh-lg mb-5" style="font-size: 1.05rem;">
                <?= htmlspecialchars((string)($articulo->descripcion ?: 'Este producto no cuenta con descripción adicional.')) ?>
            </p>

            <form action="/rxnTiendasIA/public/<?= htmlspecialchars($empresa_slug) ?>/carrito/add" method="POST" class="p-4 bg-white border rounded-4 shadow-sm">
                <input type="hidden" name="articulo_id" value="<?= $articulo->id ?>">
                <div class="row g-3 align-items-center">
                    <div class="col-auto">
                        <label for="qty" class="col-form-label fw-medium text-dark">Cantidad:</label>
                    </div>
                    <div class="col-auto">
                        <input type="number" id="qty" name="cantidad" class="form-control text-center fw-bold" value="1" min="1" max="99" style="width: 80px;">
                    </div>
                    <div class="col">
                        <?php if ($price !== null): ?>
                            <button type="submit" class="btn btn-dark w-100 py-3 fw-bold fs-5 shadow-sm rounded-3">Añadir al Carrito</button>
                        <?php else: ?>
                            <button type="button" class="btn btn-secondary w-100 py-3 fw-bold fs-5 rounded-3" disabled>No Disponible</button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php 
$content = ob_get_clean(); 
require __DIR__ . '/layout.php'; 
?>
