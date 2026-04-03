<?php
ob_start();

$categoriaSlug = $categoriaSlug ?? null;
$selectedCategory = $selectedCategory ?? null;
$categorias = $categorias ?? [];
$buildQuery = static function (array $overrides = []) use ($search, $categoriaSlug, $page) {
    $params = [
        'search' => $search,
        'categoria' => $categoriaSlug,
        'page' => $page,
    ];

    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
            continue;
        }

        $params[$key] = $value;
    }

    return http_build_query($params);
};
?>

<style>
.store-category-card {
    border: 1px solid var(--border-color);
    border-radius: 18px;
    overflow: hidden;
    background: var(--surface-color);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.04);
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    height: 100%;
}

.store-category-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 18px 30px rgba(0, 0, 0, 0.08);
    border-color: rgba(0, 0, 0, 0.1);
}

.store-category-card.is-active {
    border-color: var(--color-primary, #212529);
    box-shadow: 0 16px 28px rgba(0, 0, 0, 0.1);
}

.store-category-media {
    height: 150px;
    background: linear-gradient(135deg, rgba(0, 0, 0, 0.04), rgba(0, 0, 0, 0.08));
}

.store-category-media img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.store-category-fallback {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: rgba(0, 0, 0, 0.35);
}

.store-filter-chip {
    border-radius: 999px;
    padding: 0.55rem 1rem;
    border: 1px solid var(--border-color);
    color: var(--text-color);
    text-decoration: none;
    background: var(--surface-color);
    font-weight: 600;
}

.store-filter-chip.active {
    background: var(--color-primary, #212529);
    border-color: var(--color-primary, #212529);
    color: #fff;
}
</style>

<div class="container">
    <div class="row mb-4 align-items-center gy-3">
        <div class="col-lg-6">
            <h1 class="fw-bold fs-2 mb-1">Nuestros Productos</h1>
            <p class="text-secondary mb-0">
                Catalogo oficial de <?= htmlspecialchars($empresa_nombre) ?>
                <?php if ($selectedCategory !== null): ?>
                    <span class="d-block mt-2 fw-semibold text-dark">Filtrando por <?= htmlspecialchars((string) $selectedCategory->nombre) ?></span>
                <?php endif; ?>
            </p>
        </div>
        <div class="col-lg-6">
            <form action="/<?= htmlspecialchars($empresa_slug) ?>" method="GET" class="d-flex justify-content-lg-end">
                <?php if (!empty($categoriaSlug)): ?>
                    <input type="hidden" name="categoria" value="<?= htmlspecialchars((string) $categoriaSlug) ?>">
                <?php endif; ?>
                <div class="input-group" style="max-width: 360px; width: 100%;">
                    <input type="text" name="search" class="form-control rounded-start-pill border-end-0 bg-light" placeholder='🔎 Presioná F3 o "/" para buscar' value="<?= htmlspecialchars((string) ($search ?? '')) ?>" data-search-input autocomplete="off">
                    <button class="btn btn-light border border-start-0 rounded-end-pill text-muted px-3" type="submit">Buscar</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($categorias)): ?>
        <section id="categorias" class="mb-5">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                <div>
                    <h2 class="h4 fw-bold mb-1">Comprar por categorias</h2>
                    
                </div>
                <?php if ($selectedCategory !== null || !empty($search)): ?>
                    <a href="/<?= htmlspecialchars($empresa_slug) ?>" class="btn btn-outline-dark btn-sm rounded-pill px-3">Ver todo</a>
                <?php endif; ?>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-4">
                <a href="/<?= htmlspecialchars($empresa_slug) ?><?= $search !== '' ? '?' . htmlspecialchars(http_build_query(['search' => $search])) : '' ?>" class="store-filter-chip <?= $selectedCategory === null ? 'active' : '' ?>">Todas</a>
                <?php foreach ($categorias as $categoria): ?>
                    <a href="/<?= htmlspecialchars($empresa_slug) ?>?<?= htmlspecialchars(http_build_query(['categoria' => $categoria->slug, 'search' => $search !== '' ? $search : null])) ?>" class="store-filter-chip <?= $selectedCategory !== null && $selectedCategory->slug === $categoria->slug ? 'active' : '' ?>">
                        <?= htmlspecialchars((string) $categoria->nombre) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-4">
                <?php foreach ($categorias as $categoria): ?>
                    <div class="col">
                        <a href="/<?= htmlspecialchars($empresa_slug) ?>?<?= htmlspecialchars(http_build_query(['categoria' => $categoria->slug])) ?>" class="text-decoration-none text-reset d-block h-100">
                            <article class="store-category-card <?= $selectedCategory !== null && $selectedCategory->slug === $categoria->slug ? 'is-active' : '' ?>">
                                <div class="store-category-media">
                                    <?php if (!empty($categoria->imagen_portada)): ?>
                                        <img src="<?= htmlspecialchars((string) $categoria->imagen_portada) ?>" alt="<?= htmlspecialchars((string) $categoria->nombre) ?>">
                                    <?php else: ?>
                                        <div class="store-category-fallback">#</div>
                                    <?php endif; ?>
                                </div>
                                <div class="p-3">
                                    <h3 class="h6 fw-bold mb-1"><?= htmlspecialchars((string) $categoria->nombre) ?></h3>
                                    <p class="small text-secondary mb-0"><?= (int) $categoria->articulos_count ?> productos</p>
                                </div>
                            </article>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($selectedCategory !== null || !empty($search)): ?>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
            <span class="text-secondary small fw-semibold">Filtros activos:</span>
            <?php if ($selectedCategory !== null): ?>
                <span class="badge rounded-pill text-bg-dark px-3 py-2"><?= htmlspecialchars((string) $selectedCategory->nombre) ?></span>
            <?php endif; ?>
            <?php if (!empty($search)): ?>
                <span class="badge rounded-pill text-bg-light border px-3 py-2">Busqueda: <?= htmlspecialchars((string) $search) ?></span>
            <?php endif; ?>
            <a href="/<?= htmlspecialchars($empresa_slug) ?>" class="btn btn-link btn-sm text-decoration-none px-0">Limpiar filtros</a>
        </div>
    <?php endif; ?>

    <?php if (empty($articulos)): ?>
        <div class="text-center py-5">
            <div class="fs-1 mb-3">[]</div>
            <h3 class="text-muted">No se encontraron productos</h3>
            <p>Prueba con otra busqueda o cambia la categoria seleccionada.</p>
            <a href="/<?= htmlspecialchars($empresa_slug) ?>" class="btn btn-outline-dark mt-2">Ver todo el catalogo</a>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4 mb-5">
            <?php foreach ($articulos as $art): ?>
                <?php
                    $price = $art['precio_lista_1'] ?? $art['precio'] ?? null;
                    $hasStock = ((float) ($art['stock_actual'] ?? 0)) > 0;
                ?>
                <div class="col">
                    <div class="card product-card">
                        <div class="bg-light w-100 d-flex align-items-center justify-content-center overflow-hidden" style="height: 200px; border-radius: 12px 12px 0 0;">
                            <?php if (!empty($art['imagen_principal'])): ?>
                                <img src="<?= htmlspecialchars((string) $art['imagen_principal']) ?>" alt="<?= htmlspecialchars((string) $art['nombre']) ?>" class="w-100 h-100" style="object-fit: cover;">
                            <?php else: ?>
                                <img src="/assets/img/producto-default.png" alt="Sin imagen" class="w-100 h-100" style="object-fit: contain; padding: 20px; opacity: 0.5;">
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-4">
                            <?php if (!empty($art['categoria_nombre'])): ?>
                                <span class="badge bg-light text-dark border mb-2 fw-normal"><?= htmlspecialchars((string) $art['categoria_nombre']) ?></span>
                            <?php endif; ?>
                            <span class="badge bg-secondary opacity-50 mb-2 fw-normal" style="font-size: 0.70em;"><?= htmlspecialchars((string) $art['codigo_externo']) ?></span>
                            <h5 class="card-title fs-6 fw-bold mb-1 text-truncate" title="<?= htmlspecialchars((string) $art['nombre']) ?>">
                                <a href="/<?= htmlspecialchars($empresa_slug) ?>/producto/<?= (int) $art['id'] ?>" class="text-dark text-decoration-none stretched-link">
                                    <?= htmlspecialchars((string) $art['nombre']) ?>
                                </a>
                            </h5>

                            <div class="mt-2 mb-3 d-flex justify-content-between align-items-center">
                                <div class="product-price">
                                    <?php if ($price !== null): ?>
                                        $<?= number_format((float) $price, 2, ',', '.') ?>
                                    <?php else: ?>
                                        <span class="text-muted fs-6">Consultar</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($hasStock): ?>
                                    <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50 rounded-pill"><small>En stock</small></span>
                                <?php else: ?>
                                    <span class="badge bg-warning bg-opacity-25 text-warning border border-warning border-opacity-50 rounded-pill"><small>Sin stock</small></span>
                                <?php endif; ?>
                            </div>

                            <form action="/<?= htmlspecialchars($empresa_slug) ?>/carrito/add" method="POST" class="mt-auto position-relative" style="z-index: 2;">
                                <input type="hidden" name="articulo_id" value="<?= (int) $art['id'] ?>">
                                <input type="hidden" name="cantidad" value="1">
                                <?php if ($price !== null && $hasStock): ?>
                                    <button type="submit" class="btn btn-outline-dark btn-sm w-100 btn-add-cart">Agregar</button>
                                <?php elseif ($price !== null): ?>
                                    <button type="button" class="btn btn-light btn-sm w-100 text-muted" disabled>Sin stock</button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-light btn-sm w-100 text-muted" disabled>No disponible</button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav aria-label="Paginacion del catalogo">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link border-0 text-dark fw-medium" href="?<?= htmlspecialchars($buildQuery(['page' => max(1, $page - 1)])) ?>">Anterior</a>
                    </li>
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                            <a class="page-link <?= ($i === $page) ? 'bg-dark border-dark' : 'text-dark border-0' ?>" href="?<?= htmlspecialchars($buildQuery(['page' => $i])) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link border-0 text-dark fw-medium" href="?<?= htmlspecialchars($buildQuery(['page' => min($totalPages, $page + 1)])) ?>">Siguiente</a>
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
