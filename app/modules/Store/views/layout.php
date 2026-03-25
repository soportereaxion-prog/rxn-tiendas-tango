<?php
$empresaId = \App\Modules\Store\Context\PublicStoreContext::getEmpresaId();
$empresaObj = $empresaId ? (new \App\Modules\Empresas\EmpresaRepository())->findById($empresaId) : null;
$logoUrl = $empresaObj->logo_url ?? null;
$faviconUrl = $empresaObj->favicon_url ?? null;
$footerText = $empresaObj->footer_text ?? '';
$footerAddr = $empresaObj->footer_address ?? '';
$footerPhone = $empresaObj->footer_phone ?? '';
$footerSocials = $empresaObj->footer_socials ?? '';
?><!DOCTYPE html>
<html lang="es" <?= \App\Core\Helpers\UIHelper::getHtmlAttributes() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($empresa_nombre ?? 'Tienda') ?> - rxnTiendasIA</title>
    <?php if ($faviconUrl): ?>
        <link rel="icon" href="/rxnTiendasIA/public<?= htmlspecialchars($faviconUrl) ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/rxnTiendasIA/public/css/rxn-theming.css" rel="stylesheet">
    <?= \App\Core\Helpers\UIHelper::getTenantStyles($empresaId) ?>
    <style>
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .navbar-store {
            background-color: var(--surface-color);
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            padding-top: 1rem;
            padding-bottom: 1rem;
        }
        .navbar-brand {
            font-weight: 800;
            letter-spacing: -0.5px;
            color: var(--color-primary, #111) !important;
        }
        .product-card {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            background: var(--surface-color);
            height: 100%;
        }
        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.06);
        }
        .product-card .card-body {
            display: flex;
            flex-direction: column;
        }
        .product-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-color);
        }
        .btn-add-cart {
            margin-top: auto;
            border-radius: 8px;
            font-weight: 600;
            background-color: var(--color-secondary);
            border-color: var(--color-secondary);
            color: #fff;
        }
        .btn-add-cart:hover {
            background-color: var(--color-primary);
            border-color: var(--color-primary);
            color: #fff;
        }
        .cart-badge {
            font-size: 0.75rem;
            transform: translate(-30%, -20%);
        }
        /* Fix text links for dark mode */
        html[data-theme="dark"] .nav-link.text-dark {
            color: var(--text-color) !important;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-store sticky-top">
    <div class="container">
        <a class="navbar-brand fs-4" href="/rxnTiendasIA/public/<?= htmlspecialchars($empresa_slug) ?>">
            <?php if ($logoUrl): ?>
                <img src="/rxnTiendasIA/public<?= htmlspecialchars($logoUrl) ?>" alt="<?= htmlspecialchars($empresa_nombre) ?>" height="40" style="object-fit: contain;">
            <?php else: ?>
                <?= htmlspecialchars($empresa_nombre) ?>
            <?php endif; ?>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#storeNav" aria-controls="storeNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="storeNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
                <li class="nav-item me-3">
                    <a class="nav-link text-dark fw-medium" href="/rxnTiendasIA/public/<?= htmlspecialchars($empresa_slug) ?>">Catálogo</a>
                </li>
                <?php if (\App\Modules\Store\Context\ClienteWebContext::isLoggedIn(\App\Modules\Store\Context\PublicStoreContext::getEmpresaId())): ?>
                    <li class="nav-item dropdown me-3">
                        <a class="nav-link dropdown-toggle text-dark fw-medium" href="#" id="clienteDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Hola, <?= htmlspecialchars(\App\Modules\Store\Context\ClienteWebContext::getClienteNombre()) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" aria-labelledby="clienteDropdown">
                            <li><a class="dropdown-item" href="/rxnTiendasIA/public/<?= htmlspecialchars($empresa_slug) ?>/mis-pedidos">📦 Mis Pedidos</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="/rxnTiendasIA/public/<?= htmlspecialchars($empresa_slug) ?>/logout">Cerrar Sesión</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item me-3">
                        <a class="nav-link text-dark fw-medium" href="/rxnTiendasIA/public/<?= htmlspecialchars($empresa_slug) ?>/login">Ingresar</a>
                    </li>
                <?php endif; ?>
                
                <li class="nav-item me-3 border-start ps-3 py-1">
                    <button class="btn btn-sm btn-outline-secondary rounded-circle" id="themeToggleBtn" title="Cambiar Tema">🌙</button>
                </li>

                <li class="nav-item">
                    <?php 
                        $cartQty = 0;
                        if (isset($_SESSION['cart'][$empresaId])) {
                            foreach($_SESSION['cart'][$empresaId] as $item) {
                                $cartQty += $item['cantidad'];
                            }
                        }
                    ?>
                    <a class="btn btn-dark rounded-pill px-4 py-2 position-relative" style="background-color: var(--color-primary, #212529); border-color: var(--color-primary, #212529);" href="/rxnTiendasIA/public/<?= htmlspecialchars($empresa_slug) ?>/carrito">
                        🛒 Mi Carrito
                        <?php if($cartQty > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger cart-badge">
                                <?= $cartQty ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="py-5 min-vh-100">
    <?= $content ?? '' ?>
</main>

<footer class="py-5 border-top" style="background-color: var(--surface-color); color: var(--text-color);">
    <div class="container">
        <div class="row gx-5">
            <div class="col-md-5 mb-4">
                <h5 class="fw-bold mb-3" style="color: var(--color-primary);"><?= htmlspecialchars($empresa_nombre) ?></h5>
                <p class="small text-muted opacity-75"><?= nl2br(htmlspecialchars($footerText)) ?></p>
            </div>
            <div class="col-md-4 mb-4">
                <h6 class="fw-bold mb-3">Contacto</h6>
                <ul class="list-unstyled small text-muted">
                    <?php if ($footerAddr): ?><li class="mb-2">📍 <?= htmlspecialchars($footerAddr) ?></li><?php endif; ?>
                    <?php if ($footerPhone): ?><li class="mb-2">📞 <?= htmlspecialchars($footerPhone) ?></li><?php endif; ?>
                </ul>
            </div>
            <div class="col-md-3 mb-4">
                <h6 class="fw-bold mb-3">Redes</h6>
                <?php if ($footerSocials): ?>
                    <a href="<?= htmlspecialchars($footerSocials) ?>" target="_blank" class="text-decoration-none fw-bold" style="color: var(--color-secondary);">Seguinos Aquí</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="text-center mt-4 pt-3 border-top small text-muted">
            &copy; <?= date('Y') ?> <?= htmlspecialchars($empresa_nombre) ?>. Todos los derechos reservados.
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // --- DARK MODE TOGGLE (LocalStorage) ---
    const themeBtn = document.getElementById('themeToggleBtn');
    const rootHtml = document.documentElement;
    
    const savedTheme = localStorage.getItem('store_theme_<?= $empresaId ?>');
    if (savedTheme) {
        rootHtml.setAttribute('data-theme', savedTheme);
        themeBtn.innerText = savedTheme === 'dark' ? '☀️' : '🌙';
    }

    if (themeBtn) {
        themeBtn.addEventListener('click', () => {
            const isDark = rootHtml.getAttribute('data-theme') === 'dark';
            const newTheme = isDark ? 'light' : 'dark';
            rootHtml.setAttribute('data-theme', newTheme);
            localStorage.setItem('store_theme_<?= $empresaId ?>', newTheme);
            themeBtn.innerText = newTheme === 'dark' ? '☀️' : '🌙';
        });
    }

    // --- CART AJAX ---
    document.querySelectorAll("form[action*='/carrito/add']").forEach(form => {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = form.querySelector('button[type="submit"]');
            if(!btn) return;
            
            const originalText = btn.innerHTML;
            btn.innerHTML = '⏳ Agregando...';
            btn.disabled = true;

            fetch(form.action, {
                method: "POST",
                body: new FormData(form),
                redirect: "follow"
            }).then(response => response.text())
            .then(html => {
                const doc = new DOMParser().parseFromString(html, "text/html");
                const newBadge = doc.querySelector('.cart-badge');
                const oldBtnCart = document.querySelector('a[href*="/carrito"]');
                
                if (newBadge && oldBtnCart) {
                    const oldBadge = oldBtnCart.querySelector('.cart-badge');
                    if (oldBadge) {
                        oldBadge.innerText = newBadge.innerText;
                    } else {
                        oldBtnCart.insertAdjacentHTML('beforeend', `<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger cart-badge">${newBadge.innerText}</span>`);
                    }
                }
                
                btn.innerHTML = '✔ Agregado';
                btn.classList.add('btn-success', 'text-white');
                
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    btn.classList.remove('btn-success', 'text-white');
                }, 1500);
            }).catch(err => {
                btn.innerHTML = '❌ Error';
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }, 1500);
            });
        });
    });
});
</script>
</body>
</html>
