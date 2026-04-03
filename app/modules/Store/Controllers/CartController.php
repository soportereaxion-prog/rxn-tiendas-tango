<?php
declare(strict_types=1);

namespace App\Modules\Store\Controllers;

use App\Core\Controller;
use App\Core\View;
use App\Modules\Store\Services\StoreResolver;
use App\Modules\Store\Context\PublicStoreContext;
use App\Modules\Store\Services\CartService;

class CartController extends Controller
{
    private CartService $cartService;

    public function __construct()
    {
        $this->cartService = new CartService();
    }

    private function requireValidStore(string $slug): void
    {
        if (!StoreResolver::resolveEmpresaPublica($slug)) {
            header("Location: /public-error");
            exit;
        }
    }

    public function index(string $slug): void
    {
        $this->requireValidStore($slug);

        View::render('app/modules/Store/views/cart.php', [
            'empresa_nombre' => PublicStoreContext::getEmpresaNombre(),
            'empresa_slug'   => PublicStoreContext::getEmpresaSlug(),
            'items'          => $this->cartService->getItems(),
            'total'          => $this->cartService->getTotal()
        ]);
    }

    public function add(string $slug): void
    {
        $this->requireValidStore($slug);

        $articuloId = (int)($_POST['articulo_id'] ?? 0);
        $cantidad = (int)($_POST['cantidad'] ?? 1);

        if ($articuloId > 0 && $cantidad > 0) {
            $this->cartService->addItem($articuloId, $cantidad);
        }

        // Redireccionar a la vista anterior para seguir comprando
        $referer = $_SERVER['HTTP_REFERER'] ?? "/{$slug}";
        header("Location: " . $referer);
        exit;
    }

    public function update(string $slug): void
    {
        $this->requireValidStore($slug);

        $articuloId = (int)($_POST['articulo_id'] ?? 0);
        $cantidad = (int)($_POST['cantidad'] ?? 0);

        if ($articuloId > 0) {
            $this->cartService->updateItem($articuloId, $cantidad);
        }

        header("Location: /{$slug}/carrito");
        exit;
    }

    public function remove(string $slug): void
    {
        $this->requireValidStore($slug);

        $articuloId = (int)($_POST['articulo_id'] ?? 0);

        if ($articuloId > 0) {
            $this->cartService->removeItem($articuloId);
        }

        header("Location: /{$slug}/carrito");
        exit;
    }
}
