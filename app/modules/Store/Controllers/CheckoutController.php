<?php

declare(strict_types=1);

namespace App\Modules\Store\Controllers;

use App\Core\Controller;
use App\Core\View;
use App\Modules\Store\Services\StoreResolver;
use App\Modules\Store\Context\PublicStoreContext;
use App\Modules\Store\Services\CartService;
use App\Modules\Store\Services\CheckoutService;

class CheckoutController extends Controller
{
    private CheckoutService $checkoutService;
    private CartService $cartService;

    public function __construct()
    {
        $this->cartService = new CartService();
        $this->checkoutService = new CheckoutService($this->cartService);
    }

    private function requireValidStore(string $slug): void
    {
        if (!StoreResolver::resolveEmpresaPublica($slug)) {
            header("Location: /rxnTiendasIA/public/public-error");
            exit;
        }
    }

    public function index(string $slug): void
    {
        $this->requireValidStore($slug);

        $items = $this->cartService->getItems();
        
        if (empty($items)) {
            header("Location: /rxnTiendasIA/public/{$slug}/carrito");
            exit;
        }

        View::render('app/modules/Store/views/checkout.php', [
            'empresa_nombre' => PublicStoreContext::getEmpresaNombre(),
            'empresa_slug'   => PublicStoreContext::getEmpresaSlug(),
            'items'          => $items,
            'total'          => $this->cartService->getTotal()
        ]);
    }

    public function confirm(string $slug): void
    {
        $this->requireValidStore($slug);
        
        $empresaId = PublicStoreContext::getEmpresaId();

        // Validaciones básicas de formulario
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $documento = trim($_POST['documento'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $provincia = trim($_POST['provincia'] ?? '');
        $localidad = trim($_POST['localidad'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $observaciones = trim($_POST['observaciones'] ?? '');

        if (empty($nombre) || empty($email)) {
            // Podríamos pasar un error a la sesión, acá lo simplificamos parando ejecución o mandando de nuevo a checkout con error
            die("Error: El nombre y el e-mail son obligatorios.");
        }

        $clienteData = [
            'nombre' => $nombre,
            'apellido' => $apellido,
            'email' => $email,
            'documento' => $documento,
            'telefono' => $telefono,
            'provincia' => $provincia,
            'localidad' => $localidad,
            'direccion' => $direccion,
            'razon_social' => trim($_POST['razon_social'] ?? null),
            'codigo_postal' => trim($_POST['codigo_postal'] ?? null),
        ];

        try {
            $resultado = $this->checkoutService->processCheckout($empresaId, $clienteData, $observaciones);

            // Redirigir a pantalla de éxito usando una simple vista de agradecimiento
            View::render('app/modules/Store/views/checkout_success.php', [
                'empresa_nombre' => PublicStoreContext::getEmpresaNombre(),
                'empresa_slug'   => PublicStoreContext::getEmpresaSlug(),
                'pedido_id'      => $resultado['pedido_web_id'],
                'tango_enviado'  => $resultado['tango_enviado']
            ]);

        } catch (\Exception $e) {
            // Manejo estricto de error para el usuario sin romper
            die("Hubo un incoveniente al procesar su orden: " . $e->getMessage());
        }
    }
}
