<?php

declare(strict_types=1);

namespace App\Modules\ClientesWeb\Controllers;

use App\Core\Controller;
use App\Core\RateLimiter;
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
            header("Location: /public-error");
            exit;
        }
    }

    public function showLoginForm(string $slug): void
    {
        $this->requireValidStore($slug);
        if (ClienteWebContext::isLoggedIn(PublicStoreContext::getEmpresaId())) {
            header("Location: /{$slug}");
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
        $this->verifyCsrfOrAbort();
        $empresaId = PublicStoreContext::getEmpresaId();

        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        // Mensaje SIEMPRE genérico — mitiga user enumeration (no diferenciar mail inexistente vs password errado vs no verificado).
        $error = 'Credenciales inválidas o cuenta inactiva.';

        // Throttle: 5 intentos cada 15 min por email+IP+empresa.
        $rateKey = RateLimiter::clientKey('login_b2c_' . $empresaId, $email);
        if (!RateLimiter::allow($rateKey, 5, 900)) {
            $retryAfter = RateLimiter::retryAfter($rateKey);
            $minutes = max(1, (int) ceil($retryAfter / 60));
            View::render('app/modules/Store/views/auth/login.php', [
                'empresa_nombre' => PublicStoreContext::getEmpresaNombre(),
                'empresa_slug'   => PublicStoreContext::getEmpresaSlug(),
                'error'          => "Demasiados intentos fallidos. Intentá de nuevo en {$minutes} minuto(s).",
            ]);
            return;
        }

        try {
            if ($this->authService->login($empresaId, $email, $password)) {
                RateLimiter::reset($rateKey);
                // Redirect post-login. Validar que `next` sea relativo local (mitiga open redirect).
                $next = $_GET['next'] ?? "/{$slug}";
                if (!is_string($next)
                    || !str_starts_with($next, '/')
                    || str_starts_with($next, '//')
                    || str_contains($next, '://')) {
                    $next = "/{$slug}";
                }
                header("Location: " . $next);
                exit;
            }
        } catch (Exception $e) {
            // Log server-side para debug. Nunca reflejar $e->getMessage() al usuario en auth.
            error_log('[ClienteAuthController::processLogin] ' . $e->getMessage());
        }

        // Fallo: registrar intento fallido.
        RateLimiter::hit($rateKey, 900);

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
            header("Location: /{$slug}");
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
        $this->verifyCsrfOrAbort();
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

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            View::render('app/modules/Store/views/auth/registro.php', [
                'empresa_nombre' => PublicStoreContext::getEmpresaNombre(),
                'empresa_slug'   => PublicStoreContext::getEmpresaSlug(),
                'error'          => 'El e-mail ingresado no es válido.'
            ]);
            return;
        }

        // Throttle: 3 registros cada 15 min por IP — mitiga que se use el endpoint como relay de mails de verificación.
        $rateKey = RateLimiter::clientKey('register_b2c_' . $empresaId);
        if (!RateLimiter::attempt($rateKey, 3, 900)) {
            $retryAfter = RateLimiter::retryAfter($rateKey);
            $minutes = max(1, (int) ceil($retryAfter / 60));
            View::render('app/modules/Store/views/auth/registro.php', [
                'empresa_nombre' => PublicStoreContext::getEmpresaNombre(),
                'empresa_slug'   => PublicStoreContext::getEmpresaSlug(),
                'error'          => "Demasiados registros desde esta conexión. Intentá de nuevo en {$minutes} minuto(s).",
            ]);
            return;
        }

        try {
            $this->authService->register($empresaId, $data, $password);
            header("Location: /{$slug}/login?msg=revisar_correo");
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
        header("Location: /{$slug}");
        exit;
    }
}
