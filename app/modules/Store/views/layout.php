<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($empresa_nombre ?? 'Tienda') ?> - rxnTiendasIA</title>
    <!-- Usamos un approach de Bootstrap estándar modificado o plain CSS para escapar visualmente del admin -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #fafafa;
            color: #333;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .navbar-store {
            background-color: #ffffff;
            border-bottom: 1px solid #eaeaea;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            padding-top: 1rem;
            padding-bottom: 1rem;
        }
        .navbar-brand {
            font-weight: 800;
            letter-spacing: -0.5px;
            color: #111 !important;
        }
        .product-card {
            border: 1px solid #f0f0f0;
            border-radius: 12px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            background: #fff;
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
            color: #2c3e50;
        }
        .btn-add-cart {
            margin-top: auto;
            border-radius: 8px;
            font-weight: 600;
        }
        .cart-badge {
            font-size: 0.75rem;
            transform: translate(-30%, -20%);
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-store sticky-top">
    <div class="container">
        <a class="navbar-brand fs-4" href="/rxnTiendasIA/public/<?= htmlspecialchars($empresa_slug) ?>">
            <?= htmlspecialchars($empresa_nombre) ?>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#storeNav" aria-controls="storeNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="storeNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
                <li class="nav-item me-3">
                    <a class="nav-link text-dark fw-medium" href="/rxnTiendasIA/public/<?= htmlspecialchars($empresa_slug) ?>">Catálogo</a>
                </li>
                <li class="nav-item">
                    <?php 
                        // Calculo rápido de cart total qty
                        $cartQty = 0;
                        if (isset($_SESSION['cart'][\App\Modules\Store\Context\PublicStoreContext::getEmpresaId()])) {
                            foreach($_SESSION['cart'][\App\Modules\Store\Context\PublicStoreContext::getEmpresaId()] as $item) {
                                $cartQty += $item['cantidad'];
                            }
                        }
                    ?>
                    <a class="btn btn-dark rounded-pill px-4 py-2 position-relative" href="/rxnTiendasIA/public/<?= htmlspecialchars($empresa_slug) ?>/carrito">
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

<footer class="bg-light py-4 text-center text-muted border-top">
    <div class="container">
        <small>&copy; <?= date('Y') ?> <?= htmlspecialchars($empresa_nombre) ?>. Todos los derechos reservados.</small>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
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
                btn.classList.remove('btn-dark', 'btn-outline-dark');
                
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    btn.classList.remove('btn-success', 'text-white');
                    if (btn.classList.contains('btn-add-cart')) {
                        btn.classList.add('btn-outline-dark');
                    } else {
                        btn.classList.add('btn-dark');
                    }
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
