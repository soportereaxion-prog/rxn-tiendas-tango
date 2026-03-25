<?php

declare(strict_types=1);

namespace App\Modules\ClientesWeb\Controllers;

use App\Core\Controller;
use App\Core\View;
use App\Modules\Store\Services\StoreResolver;
use App\Modules\Store\Context\PublicStoreContext;
use App\Modules\Store\Context\ClienteWebContext;
use App\Modules\ClientesWeb\Services\ClienteWebAuthService;
use Exception;

class ClienteAuthController extends Controller
{
    private ClienteWebAuthService $authService;

    public function __construct()
    {
        $this->authService = new ClienteWebAuthService();
    }

    private function requireValidStore(string $slug): void
    {
        if (!StoreResolver::resolveEmpresaPublica($slug)) {
            header("Location: /rxnTiendasIA/public/public-error");
            exit;
        }
    }

    public function showLoginForm(string $slug): void
    {
        $this->requireValidStore($slug);
        if (ClienteWebContext::isLoggedIn(PublicStoreContext::getEmpresaId())) {
            header("Location: /rxnTiendasIA/public/{$slug}");
            exit;
        }

        View::render('app/modules/Store/views/auth/login.php', [
            'empresa_nombre' => PublicStoreContext::getEmpresaNombre(),
            'empresa_slug'   => PublicStoreContext::getEmpresaSlug()
        ]);
    }

    public function processLogin(string $slug): void
    {
        $this->requireValidStore($slug);
        $empresaId = PublicStoreContext::getEmpresaId();

        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        $error = 'Credenciales inválidas o cuenta inactiva.';

        try {
            if ($this->authService->login($empresaId, $email, $password)) {
                // Verificar si hay un redirect previo (ej. al pagar checkout y requeria login temporalmente)
                $next = $_GET['next'] ?? "/rxnTiendasIA/public/{$slug}";
                header("Location: " . filter_var($next, FILTER_SANITIZE_URL));
                exit;
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }

        View::render('app/modules/Store/views/auth/login.php', [
            'empresa_nombre' => PublicStoreContext::getEmpresaNombre(),
            'empresa_slug'   => PublicStoreContext::getEmpresaSlug(),
            'error'          => $error
        ]);
    }

    public function showRegisterForm(string $slug): void
    {
        $this->requireValidStore($slug);
        if (ClienteWebContext::isLoggedIn(PublicStoreContext::getEmpresaId())) {
            header("Location: /rxnTiendasIA/public/{$slug}");
            exit;
        }

        View::render('app/modules/Store/views/auth/registro.php', [
            'empresa_nombre' => PublicStoreContext::getEmpresaNombre(),
            'empresa_slug'   => PublicStoreContext::getEmpresaSlug()
        ]);
    }

    public function processRegister(string $slug): void
    {
        $this->requireValidStore($slug);
        $empresaId = PublicStoreContext::getEmpresaId();

        $data = [
            'nombre'   => trim($_POST['nombre'] ?? ''),
            'apellido' => trim($_POST['apellido'] ?? ''),
            'email'    => trim($_POST['email'] ?? ''),
            'telefono' => trim($_POST['telefono'] ?? null)
        ];
        $password = trim($_POST['password'] ?? '');

        if (empty($data['nombre']) || empty($data['email']) || empty($password)) {
            View::render('app/modules/Store/views/auth/registro.php', [
                'empresa_nombre' => PublicStoreContext::getEmpresaNombre(),
                'empresa_slug'   => PublicStoreContext::getEmpresaSlug(),
                'error'          => 'Todos los campos Obligatorios deben completarse.'
            ]);
            return;
        }

        try {
            $this->authService->register($empresaId, $data, $password);
            header("Location: /rxnTiendasIA/public/{$slug}/login?msg=revisar_correo");
            exit;
        } catch (Exception $e) {
            View::render('app/modules/Store/views/auth/registro.php', [
                'empresa_nombre' => PublicStoreContext::getEmpresaNombre(),
                'empresa_slug'   => PublicStoreContext::getEmpresaSlug(),
                'error'          => $e->getMessage()
            ]);
        }
    }

    public function logout(string $slug): void
    {
        $this->requireValidStore($slug);
        $this->authService->logout();
        header("Location: /rxnTiendasIA/public/{$slug}");
        exit;
    }
}
