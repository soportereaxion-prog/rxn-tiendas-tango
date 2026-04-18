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
            header("Location: /public-error");
            exit;
        }
    }

    public function index(string $slug): void
    {
        $this->requireValidStore($slug);

        $items = $this->cartService->getItems();
        
        if (empty($items)) {
            header("Location: /{$slug}/carrito");
            exit;
        }

        $empresaId = PublicStoreContext::getEmpresaId();
        $clienteData = [];
        if (\App\Modules\Store\Context\ClienteWebContext::isLoggedIn($empresaId)) {
            $clienteId = \App\Modules\Store\Context\ClienteWebContext::getClienteId();
            $repo = new \App\Modules\ClientesWeb\ClienteWebRepository();
            $clienteData = $repo->findById($clienteId, $empresaId) ?? [];
        }

        View::render('app/modules/Store/views/checkout.php', [
            'empresa_nombre' => PublicStoreContext::getEmpresaNombre(),
            'empresa_slug'   => PublicStoreContext::getEmpresaSlug(),
            'items'          => $items,
            'total'          => $this->cartService->getTotal(),
            'cliente'        => $clienteData
        ]);
    }

    public function confirm(string $slug): void
    {
        $this->requireValidStore($slug);
        $this->verifyCsrfOrAbort();

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
        $passwordRegistro = trim($_POST['password_registro'] ?? '');

        if (empty($nombre) || empty($email)) {
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
            // Fase 7: Registro Automático de Invitado si mandó password en Checkout
            if (!\App\Modules\Store\Context\ClienteWebContext::isLoggedIn($empresaId) && !empty($passwordRegistro)) {
                $authService = new \App\Modules\ClientesWeb\Services\ClienteWebAuthService();
                $authService->register($empresaId, $clienteData, $passwordRegistro);
            }

            $resultado = $this->checkoutService->processCheckout($empresaId, $clienteData, $observaciones);

            View::render('app/modules/Store/views/checkout_success.php', [
                'empresa_nombre' => PublicStoreContext::getEmpresaNombre(),
                'empresa_slug'   => PublicStoreContext::getEmpresaSlug(),
                'pedido_id'      => $resultado['pedido_web_id'],
                'tango_enviado'  => $resultado['tango_enviado']
            ]);

        } catch (\Exception $e) {
            error_log("CheckoutController Exception (confirm): " . $e->getMessage());
            die("Lo sentimos. Hubo un inconveniente técnico al procesar el pedido. Intentá de nuevo a la brevedad.");
        }
    }
}
