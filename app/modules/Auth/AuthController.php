<?php
declare(strict_types=1);
namespace App\Modules\Auth;
use App\Core\Controller;
use App\Core\RateLimiter;
use App\Core\View;

class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (!empty($_SESSION['user_id'])) {
            header('Location: /');
            exit;
        }
        View::render('app/modules/Auth/views/login.php', []);
    }

    public function processLogin(): void
    {
        $this->verifyCsrfOrAbort();

        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        // Throttle: 5 intentos cada 15 min por email+IP.
        $rateKey = RateLimiter::clientKey('login_b2b', is_string($email) ? $email : null);
        if (!RateLimiter::allow($rateKey, 5, 900)) {
            $retryAfter = RateLimiter::retryAfter($rateKey);
            $minutes = max(1, (int) ceil($retryAfter / 60));
            View::render('app/modules/Auth/views/login.php', [
                'error' => "Demasiados intentos fallidos. Intentá de nuevo en {$minutes} minuto(s).",
                'old_email' => $email,
            ]);
            return;
        }

        $auth = new AuthService();
        // Mensaje SIEMPRE genérico — mitiga user enumeration.
        $error = 'Credenciales inválidas o usuario inactivo.';

        try {
            if ($auth->attempt($email, $password)) {
                RateLimiter::reset($rateKey);
                header('Location: /');
                exit;
            }
        } catch (\Exception $e) {
            // Log server-side para debug. Nunca reflejar $e->getMessage() al usuario en auth.
            error_log('[AuthController::processLogin] ' . $e->getMessage());
        }

        // Fallo: registrar intento.
        RateLimiter::hit($rateKey, 900);

        View::render('app/modules/Auth/views/login.php', [
            'error' => $error,
            'old_email' => $email
        ]);
    }

    public function logout(): void
    {
        AuthService::logout();
        header('Location: /');
        exit;
    }
}
