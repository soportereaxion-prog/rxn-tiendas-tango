<?php
declare(strict_types=1);
namespace App\Modules\Auth;
use App\Core\Controller;
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
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        $auth = new AuthService();
        $error = 'Credenciales inválidas o usuario inactivo.';
        
        try {
            if ($auth->attempt($email, $password)) {
                header('Location: /');
                exit;
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

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
