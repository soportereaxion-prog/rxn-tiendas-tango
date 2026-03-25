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
                if (!isset($usuario->email_verificado) || (int)$usuario->email_verificado !== 1) {
                    throw new \Exception("Cuenta pendiente de verificación. Revise el correo de activación.");
                }
                
                // Inyectamos contexto operativo persistente
                $_SESSION['user_id'] = $usuario->id;
                $_SESSION['empresa_id'] = $usuario->empresa_id;
                $_SESSION['user_name'] = $usuario->nombre;
                $_SESSION['es_rxn_admin'] = $usuario->es_rxn_admin ?? 0;
                $_SESSION['pref_theme'] = $usuario->preferencia_tema ?? 'light';
                $_SESSION['pref_font'] = $usuario->preferencia_fuente ?? 'md';
                $_SESSION['dashboard_order'] = $usuario->dashboard_order ?? null;
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

    public static function requireRxnAdmin(): void
    {
        self::requireLogin();
        if (empty($_SESSION['es_rxn_admin']) || $_SESSION['es_rxn_admin'] != 1) {
            http_response_code(403);
            echo "<div style='font-family:sans-serif; text-align:center; margin-top:50px;'>";
            echo "<h2>⚠️ Acceso Denegado (Área Restringida RXN)</h2>";
            echo "<p>Usted no posee credenciales centralizadas para auditar o administrar licencias. Contacte a soporte de RXN.</p>";
            echo "<a href='/rxnTiendasIA/public/'>Volver Seguro</a>";
            echo "</div>";
            exit;
        }
    }
}
