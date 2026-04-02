<?php ob_start(); 
$price = $articulo->precio_lista_1 ?? $articulo->precio ?? null;
$hasStock = ((float)$articulo->stock_actual) > 0;
?>
<div class="container py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/rxnTiendasIA/public/<?= htmlspecialchars($empresa_slug) ?>" class="text-decoration-none text-muted">Catálogo</a></li>
            <?php if (!empty($articulo->categoria_nombre) && !empty($articulo->categoria_slug)): ?>
                <li class="breadcrumb-item"><a href="/rxnTiendasIA/public/<?= htmlspecialchars($empresa_slug) ?>?categoria=<?= urlencode((string) $articulo->categoria_slug) ?>" class="text-decoration-none text-muted"><?= htmlspecialchars((string) $articulo->categoria_nombre) ?></a></li>
            <?php endif; ?>
            <li class="breadcrumb-item active text-dark fw-medium" aria-current="page"><?= htmlspecialchars((string)$articulo->nombre) ?></li>
        </ol>
    </nav>

<?php
// Armado dinámico de Array JS de Imágenes para el Lightbox
$jsImages = [];
if (!empty($imagenes) && count($imagenes) > 0) {
    foreach ($imagenes as $img) {
        $jsImages[] = "/rxnTiendasIA/public" . htmlspecialchars((string)$img['ruta']);
    }
} else {
    // Escalar 1:1 fallback
    if (!empty($articulo->imagen_principal)) {
         $jsImages[] = "/rxnTiendasIA/public" . htmlspecialchars((string)$articulo->imagen_principal);
    } else {
         $jsImages[] = "/rxnTiendasIA/public/assets/img/producto-default.png";
    }
}
$isFallbackOnly = (empty($articulo->imagen_principal) && empty($imagenes));
?>

<style>
/* Estilos Vanilla Lightbox */
.lightbox-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.85); z-index: 9999;
    display: flex; align-items: center; justify-content: center;
    opacity: 0; transition: opacity 0.3s ease;
}
.lightbox-overlay.active { opacity: 1; }
.lightbox-content { position: relative; max-width: 90%; max-height: 90vh; display: flex; align-items: center; justify-content: center; }
.lightbox-content img { max-width: 100%; max-height: 90vh; object-fit: contain; border-radius: 8px; box-shadow: 0 5px 25px rgba(0,0,0,0.5); transition: opacity 0.2s ease; }
.lightbox-btn {
    position: absolute; background: rgba(255, 255, 255, 0.1); color: white; border: 1px solid rgba(255,255,255,0.2);
    font-size: 2rem; cursor: pointer; padding: 5px 20px; border-radius: 50%; backdrop-filter: blur(5px);
    transition: background 0.2s, transform 0.2s; z-index: 10000;
}
.lightbox-btn:hover { background: rgba(255, 255, 255, 0.3); transform: scale(1.1); }
.lightbox-close { top: -40px; right: -40px; }
.lightbox-prev { left: -60px; top: 50%; transform: translateY(-50%); }
.lightbox-next { right: -60px; top: 50%; transform: translateY(-50%); }
.lightbox-prev:hover, .lightbox-next:hover { transform: translateY(-50%) scale(1.1); }
@media (max-width: 768px) {
    .lightbox-close { top: -45px; right: 0; }
    .lightbox-prev { left: 10px; padding: 10px 15px; }
    .lightbox-next { right: 10px; padding: 10px 15px; }
}
</style>

    <div class="row g-5">
        <div class="col-md-6 mb-4 mb-md-0">
            <!-- Imagen Principal Grande con trigger Lightbox -->
            <div class=" border rounded-4 d-flex align-items-center justify-content-center shadow-sm overflow-hidden mb-3 position-relative" style="height: 400px; cursor: zoom-in;" onclick="openLightbox(currentGalleryIndex)">
                <img id="main-product-image" src="<?= $jsImages[0] ?>" alt="<?= htmlspecialchars((string)$articulo->nombre) ?>" class="w-100 h-100" style="object-fit: <?= $isFallbackOnly ? 'contain; padding: 40px; opacity: 0.5;' : 'cover;' ?> transition: opacity 0.3s ease;">
                
                <!-- Icono lupa indicador -->
                <div class="position-absolute bottom-0 end-0 m-3 bg-dark bg-opacity-75 text-white rounded-circle d-flex align-items-center justify-content-center pointer-events-none transition" style="width: 45px; height: 45px; transition: transform 0.2s;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M6.5 12a5.5 5.5 0 1 0 0-11 5.5 5.5 0 0 0 0 11zM13 6.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0z"/><path d="M10.344 11.742c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1 6.538 6.538 0 0 1-1.398 1.4z"/><path fill-rule="evenodd" d="M6.5 3a.5.5 0 0 1 .5.5V6h2.5a.5.5 0 0 1 0 1H7v2.5a.5.5 0 0 1-1 0V7H3.5a.5.5 0 0 1 .5-.5z"/></svg>
                </div>
            </div>
            
            <!-- Miniaturas (Galería Múltiple) -->
            <?php if (count($jsImages) > 1): ?>
                <div class="d-flex gap-2 overflow-auto pb-2" style="white-space: nowrap;">
                    <?php foreach ($jsImages as $index => $imgUrl): ?>
                        <div class="border rounded-3 overflow-hidden shadow-sm position-relative" style="width: 80px; height: 80px; flex-shrink: 0; cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'" onclick="setMainImage(<?= $index ?>)">
                            <img src="<?= $imgUrl ?>" class="w-100 h-100" style="object-fit: cover;" title="Visualizar variante">
                            <!-- Overlay lupa chiquita flotante al hover -->
                            <div class="position-absolute bottom-0 end-0 m-1 bg-dark bg-opacity-75 text-white rounded-circle d-flex align-items-center justify-content-center pointer-events-none" style="width: 22px; height: 22px; opacity: 0.8;" onclick="event.stopPropagation(); openLightbox(<?= $index ?>)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M6.5 12a5.5 5.5 0 1 0 0-11 5.5 5.5 0 0 0 0 11zM13 6.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0z"/><path d="M10.344 11.742c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1 6.538 6.538 0 0 1-1.398 1.4z"/><path fill-rule="evenodd" d="M6.5 3a.5.5 0 0 1 .5.5V6h2.5a.5.5 0 0 1 0 1H7v2.5a.5.5 0 0 1-1 0V7H3.5a.5.5 0 0 1 .5-.5z"/></svg>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="col-md-6">
            <span class="badge bg-secondary mb-3 fs-6 px-3 py-2 fw-normal opacity-75">SKU: <?= htmlspecialchars((string)$articulo->codigo_externo) ?></span>
            <?php if (!empty($articulo->categoria_nombre) && !empty($articulo->categoria_slug)): ?>
                <div class="mb-3">
                    <a href="/rxnTiendasIA/public/<?= htmlspecialchars($empresa_slug) ?>?categoria=<?= urlencode((string) $articulo->categoria_slug) ?>" class="badge rounded-pill text-bg-light border text-decoration-none px-3 py-2">
                        <?= htmlspecialchars((string) $articulo->categoria_nombre) ?>
                    </a>
                </div>
            <?php endif; ?>
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

            <form action="/rxnTiendasIA/public/<?= htmlspecialchars($empresa_slug) ?>/carrito/add" method="POST" class="p-4  border rounded-4 shadow-sm">
                <input type="hidden" name="articulo_id" value="<?= $articulo->id ?>">
                <div class="row g-3 align-items-center">
                    <div class="col-auto">
                        <label for="qty" class="col-form-label fw-medium text-dark">Cantidad:</label>
                    </div>
                    <div class="col-auto">
                        <input type="number" id="qty" name="cantidad" class="form-control text-center fw-bold" value="1" min="1" max="99" style="width: 80px;">
                    </div>
                    <div class="col">
                        <?php if ($price !== null && $hasStock): ?>
                            <button type="submit" class="btn btn-dark w-100 py-3 fw-bold fs-5 shadow-sm rounded-3">Añadir al Carrito</button>
                        <?php elseif ($price !== null): ?>
                            <button type="button" class="btn btn-secondary w-100 py-3 fw-bold fs-5 rounded-3" disabled>Sin Stock</button>
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

<!-- Componente Lightbox DOM -->
<div id="product-lightbox" class="lightbox-overlay" style="display: none;">
    <div class="lightbox-content">
        <button id="lightbox-close" class="lightbox-btn lightbox-close" title="Cerrar">&times;</button>
        <button id="lightbox-prev" class="lightbox-btn lightbox-prev" title="Anterior">&#10094;</button>
        <img id="lightbox-img" src="" alt="Ampliación">
        <button id="lightbox-next" class="lightbox-btn lightbox-next" title="Siguiente">&#10095;</button>
    </div>
</div>

<script>
// Manejo Vanilla JS Galería + Lightbox
const galleryImages = <?= json_encode($jsImages) ?>;
let currentGalleryIndex = 0;

function setMainImage(index) {
    const mainImg = document.getElementById('main-product-image');
    mainImg.style.opacity = '0';
    currentGalleryIndex = index;
    setTimeout(() => {
        mainImg.src = galleryImages[index];
        mainImg.style.opacity = '1';
    }, 150);
}

const lightbox = document.getElementById('product-lightbox');
const lightboxImg = document.getElementById('lightbox-img');
const btnClose = document.getElementById('lightbox-close');
const btnPrev = document.getElementById('lightbox-prev');
const btnNext = document.getElementById('lightbox-next');

function openLightbox(index) {
    currentGalleryIndex = index;
    updateLightboxImage();
    lightbox.style.display = 'flex';
    // Trigger fadeIn suave
    setTimeout(() => lightbox.classList.add('active'), 10);
    
    // Toggle controles si hay 1 sola imagen
    if (galleryImages.length <= 1) {
        btnPrev.style.display = 'none';
        btnNext.style.display = 'none';
    } else {
        btnPrev.style.display = 'block';
        btnNext.style.display = 'block';
    }
}

function closeLightbox() {
    lightbox.classList.remove('active');
    setTimeout(() => { lightbox.style.display = 'none'; }, 300);
}

function updateLightboxImage() {
    lightboxImg.style.opacity = '0';
    setTimeout(() => {
        lightboxImg.src = galleryImages[currentGalleryIndex];
        lightboxImg.style.opacity = '1';
    }, 200);
}

function prevImage(e) {
    if(e) e.stopPropagation();
    currentGalleryIndex = (currentGalleryIndex > 0) ? currentGalleryIndex - 1 : galleryImages.length - 1;
    updateLightboxImage();
    setMainImage(currentGalleryIndex); // Sincroniza imagen de fondo grilla
}

function nextImage(e) {
    if(e) e.stopPropagation();
    currentGalleryIndex = (currentGalleryIndex < galleryImages.length - 1) ? currentGalleryIndex + 1 : 0;
    updateLightboxImage();
    setMainImage(currentGalleryIndex); // Sincroniza imagen de fondo grilla
}

// Bindings Eventos Mouse/DOM
btnClose.addEventListener('click', closeLightbox);
lightbox.addEventListener('click', (e) => {
    // Si clickeamos fuera del layout o fondo negro, cerrar
    if (e.target === lightbox || e.target.classList.contains('lightbox-content')) {
        closeLightbox();
    }
});
btnPrev.addEventListener('click', prevImage);
btnNext.addEventListener('click', nextImage);

// Soportes Teclado Global Lightbox
document.addEventListener('keydown', (e) => {
    if (lightbox.style.display === 'flex') {
        if (e.key === 'Escape') closeLightbox();
        if (galleryImages.length > 1) {
            if (e.key === 'ArrowLeft') prevImage();
            if (e.key === 'ArrowRight') nextImage();
        }
    }
});
</script>
