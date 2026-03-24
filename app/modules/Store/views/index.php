<?php ob_start(); ?>
<div class="container">
    <div class="row mb-5 align-items-center">
        <div class="col-md-6">
            <h1 class="fw-bold fs-2 mb-1">Nuestros Productos</h1>
            <p class="text-secondary mb-0">Catálogo oficial de <?= htmlspecialchars($empresa_nombre) ?></p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <form action="/rxnTiendasIA/public/<?= htmlspecialchars($empresa_slug) ?>" method="GET" class="d-flex justify-content-md-end">
                <div class="input-group" style="max-width: 300px;">
                    <input type="text" name="search" class="form-control rounded-start-pill border-end-0 bg-light" placeholder="Buscar productos..." value="<?= htmlspecialchars((string)($search ?? '')) ?>">
                    <button class="btn btn-light border border-start-0 rounded-end-pill text-muted px-3" type="submit">🔍</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($articulos)): ?>
        <div class="text-center py-5">
            <div class="fs-1 mb-3">📦</div>
            <h3 class="text-muted">No se encontraron productos</h3>
            <p>Intenta con otra búsqueda o regresa más tarde.</p>
            <?php if (!empty($search)): ?>
                <a href="/rxnTiendasIA/public/<?= htmlspecialchars($empresa_slug) ?>" class="btn btn-outline-dark mt-2">Ver todo el catálogo</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4 mb-5">
            <?php foreach ($articulos as $art): ?>
                <?php 
                    // Filter inactive out correctly
                    if (!$art['activo']) continue; 
                    
                    $price = $art['precio_lista_1'] ?? $art['precio'] ?? null;
                    $hasStock = ((float)$art['stock_actual']) > 0;
                ?>
                <div class="col">
                    <div class="card product-card">
                        <!-- Image Box -->
                        <div class="bg-light w-100 d-flex align-items-center justify-content-center overflow-hidden" style="height: 200px; border-radius: 12px 12px 0 0;">
                            <?php if (!empty($art['imagen_principal'])): ?>
                                <img src="/rxnTiendasIA/public<?= htmlspecialchars((string)$art['imagen_principal']) ?>" alt="<?= htmlspecialchars((string)$art['nombre']) ?>" class="w-100 h-100" style="object-fit: cover;">
                            <?php else: ?>
                                <img src="/rxnTiendasIA/public/assets/img/producto-default.png" alt="Sin imagen" class="w-100 h-100" style="object-fit: contain; padding: 20px; opacity: 0.5;">
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-4">
                            <span class="badge bg-secondary opacity-50 mb-2 fw-normal" style="font-size: 0.70em;"><?= htmlspecialchars((string)$art['codigo_externo']) ?></span>
                            <h5 class="card-title fs-6 fw-bold mb-1 text-truncate" title="<?= htmlspecialchars((string)$art['nombre']) ?>">
                                <a href="/rxnTiendasIA/public/<?= htmlspecialchars($empresa_slug) ?>/producto/<?= $art['id'] ?>" class="text-dark text-decoration-none stretched-link">
                                    <?= htmlspecialchars((string)$art['nombre']) ?>
                                </a>
                            </h5>
                            
                            <div class="mt-2 mb-3 d-flex justify-content-between align-items-center">
                                <div class="product-price">
                                    <?php if ($price !== null): ?>
                                        $<?= number_format((float)$price, 2, ',', '.') ?>
                                    <?php else: ?>
                                        <span class="text-muted fs-6">Consultar</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($hasStock): ?>
                                    <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50 rounded-pill"><small>En Stock</small></span>
                                <?php else: ?>
                                    <span class="badge bg-warning bg-opacity-25 text-warning border border-warning border-opacity-50 rounded-pill"><small>Sin Stock</small></span>
                                <?php endif; ?>
                            </div>
                            
                            <form action="/rxnTiendasIA/public/<?= htmlspecialchars($empresa_slug) ?>/carrito/add" method="POST" class="mt-auto position-relative" style="z-index: 2;">
                                <input type="hidden" name="articulo_id" value="<?= $art['id'] ?>">
                                <input type="hidden" name="cantidad" value="1">
                                <?php if ($price !== null): ?>
                                    <button type="submit" class="btn btn-outline-dark btn-sm w-100 btn-add-cart">🛒 Agregar</button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-light btn-sm w-100 text-muted" disabled>No Disponible</button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link border-0 text-dark fw-medium" href="?page=<?= max(1, $page - 1) ?>&search=<?= urlencode((string)($search ?? '')) ?>">Anterior</a>
                </li>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                    <a class="page-link <?= ($i === $page) ? 'bg-dark border-dark' : 'text-dark border-0' ?>" href="?page=<?= $i ?>&search=<?= urlencode((string)($search ?? '')) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                    <a class="page-link border-0 text-dark fw-medium" href="?page=<?= min($totalPages, $page + 1) ?>&search=<?= urlencode((string)($search ?? '')) ?>">Siguiente</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php 
$content = ob_get_clean(); 
require __DIR__ . '/layout.php'; 
?>
