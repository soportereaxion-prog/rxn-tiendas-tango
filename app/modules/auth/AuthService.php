<?php

declare(strict_types=1);

namespace App\Modules\Auth;

class AuthService
{
    private UsuarioRepository $repository;

    public function __construct() {
        $this->repository = new UsuarioRepository();
    }

    public function attempt(string $email, string $password): bool
    {
        $usuario = $this->repository->findByEmail($email);
        
        if ($usuario && $usuario->activo === 1) {
            if (password_verify($password, $usuario->password_hash)) {
                // Inyectamos contexto operativo persistente
                $_SESSION['user_id'] = $usuario->id;
                $_SESSION['empresa_id'] = $usuario->empresa_id;
                $_SESSION['user_name'] = $usuario->nombre;
                return true;
            }
        }
        return false;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public static function requireLogin(): void
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: /rxnTiendasIA/public/login');
            exit;
        }
    }
}
