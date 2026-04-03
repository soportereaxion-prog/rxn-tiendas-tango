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
                
                // Regenerar sesión para prevenir Session Fixation
                session_regenerate_id(true);

                // Inyectamos contexto operativo persistente
                $_SESSION['user_id'] = $usuario->id;
                $_SESSION['empresa_id'] = $usuario->empresa_id;
                $_SESSION['user_name'] = $usuario->nombre;
                $_SESSION['anura_interno'] = $usuario->anura_interno ?? null;
                $_SESSION['es_rxn_admin'] = $usuario->es_rxn_admin ?? 0;
                $_SESSION['pref_theme'] = $usuario->preferencia_tema ?? 'light';
                $_SESSION['pref_font'] = $usuario->preferencia_fuente ?? 'md';
                $_SESSION['es_admin'] = $usuario->es_admin ?? 0;
                $_SESSION['dashboard_order'] = $usuario->dashboard_order ?? null;
                
                $now = time();
                $_SESSION['backoffice_created_at'] = $now;
                $_SESSION['backoffice_last_activity'] = $now;
                return true;
            }
        }
        return false;
    }

    public static function getCurrentUser(): ?Usuario
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        $repo = new UsuarioRepository();
        return $repo->findById((int) $_SESSION['user_id']);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public static function requireLogin(): void
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }
    }

    public static function hasAdminPrivileges(): bool
    {
        $isTenantAdmin = !empty($_SESSION['es_admin']) && $_SESSION['es_admin'] == 1;
        $isGlobalAdmin = !empty($_SESSION['es_rxn_admin']) && $_SESSION['es_rxn_admin'] == 1;

        return $isTenantAdmin || $isGlobalAdmin;
    }

    public static function isRxnAdmin(): bool
    {
        // 1. Criterio Principal y Oficial: La cuenta tiene asignado explícitamente el flag Global en su Perfil
        $hasFlag = !empty($_SESSION['es_rxn_admin']) && $_SESSION['es_rxn_admin'] == 1;
        
        // 2. Fallback de Compatibilidad Legada:
        // En instalaciones heredadas, el usuario administrador principal que despliega el tenant 1
        // (empresa_id: 1, es_admin: 1) figura como owner funcional aunque el flag RXN Admin viniera apagado.
        // Se mantiene para evitar bloquear fuera a los usuarios maestros iniciales y darles tiempo de asignarse el flag desde UI.
        $isPrimaryAdmin = !empty($_SESSION['es_admin']) && $_SESSION['es_admin'] == 1 &&
                          !empty($_SESSION['empresa_id']) && $_SESSION['empresa_id'] == 1;
                          
        return $hasFlag || $isPrimaryAdmin;
    }

    public static function requireRxnAdmin(): void
    {
        self::requireLogin();
        if (!self::isRxnAdmin()) {
            http_response_code(403);
            echo "<div style='font-family:sans-serif; text-align:center; margin-top:50px;'>";
            echo "<h2>⚠️ Acceso Denegado (Área Restringida RXN)</h2>";
            echo "<p>Usted no posee credenciales centralizadas para auditar o administrar licencias. Contacte a soporte de RXN.</p>";
            echo "<a href='/'>Volver Seguro</a>";
            echo "</div>";
            exit;
        }
    }

    public static function requireBackofficeAdmin(): void
    {
        self::requireLogin();

        if (!self::hasAdminPrivileges()) {
            http_response_code(403);
            echo "<div style='font-family:sans-serif; text-align:center; margin-top:50px;'>";
            echo "<h2>⚠️ Acceso Denegado (Backoffice)</h2>";
            echo "<p>Necesita privilegios de administrador para ingresar al backoffice.</p>";
            echo "<a href='/'>Volver Seguro</a>";
            echo "</div>";
            exit;
        }
    }
}
