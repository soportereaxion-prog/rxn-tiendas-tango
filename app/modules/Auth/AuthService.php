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

        // Logs DIAGNÓSTICOS temporales (release 1.29.x — cazando bug de cambio de
        // contraseña que aparenta guardar pero el login falla). Sacar cuando esté
        // confirmado el root cause. NO logean el password ni el hash, solo flags.
        if (!$usuario) {
            error_log('[AuthService::attempt] FAIL — usuario inexistente para email: ' . $email);
        } else {
            $hashLen = isset($usuario->password_hash) ? strlen((string) $usuario->password_hash) : -1;
            $hashPrefix = $hashLen > 0 ? substr((string) $usuario->password_hash, 0, 4) : 'null';
            error_log(sprintf(
                '[AuthService::attempt] usuario #%d encontrado | activo=%s | email_verificado=%s | hash_len=%d | hash_prefix=%s',
                (int) $usuario->id,
                var_export($usuario->activo, true),
                var_export($usuario->email_verificado ?? null, true),
                $hashLen,
                $hashPrefix
            ));
        }

        if ($usuario && $usuario->activo === 1) {
            $verified = password_verify($password, $usuario->password_hash);
            error_log('[AuthService::attempt] usuario #' . (int) $usuario->id . ' password_verify=' . var_export($verified, true));
            if ($verified) {
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
                $_SESSION['color_calendario'] = $usuario->color_calendario ?? '#007bff';
                $_SESSION['es_admin'] = $usuario->es_admin ?? 0;
                $_SESSION['dashboard_order'] = $usuario->dashboard_order ?? null;
                
                $now = time();
                $_SESSION['backoffice_created_at'] = $now;
                $_SESSION['backoffice_last_activity'] = $now;

                // Geo-tracking: registrar evento de login. Fire-and-forget:
                // nunca puede lanzar ni bloquear el flujo de login (ver RxnGeoTracking/MODULE_CONTEXT.md).
                // El ID del evento queda en sesión para que el frontend lo reporte con
                // posición del browser en el primer render del layout post-login.
                try {
                    $geoService = new \App\Modules\RxnGeoTracking\GeoTrackingService();
                    $eventoId = $geoService->registrar(\App\Modules\RxnGeoTracking\GeoTrackingService::EVENT_LOGIN);
                    if ($eventoId !== null) {
                        $_SESSION['rxn_geo_pending_event_id'] = $eventoId;
                    }
                } catch (\Throwable) {
                    // Silent fail — la invariante del service ya no debería llegar acá.
                }

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
            // Return-URL: solo capturamos GET de páginas (no POST ni endpoints AJAX).
            // Para POST con sesión muerta el browser no puede repetir el body, así
            // que mandar al usuario al GET equivalente sería peor; mejor que caiga
            // al dashboard post-login que a una URL que va a fallar.
            $next = '/';
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
                $candidate = (string) ($_SERVER['REQUEST_URI'] ?? '/');
                if (self::isSafeNext($candidate)) {
                    $next = $candidate;
                }
            }

            $loginUrl = '/login';
            if ($next !== '/' && $next !== '') {
                $loginUrl .= '?next=' . rawurlencode($next);
            }
            header('Location: ' . $loginUrl);
            exit;
        }
    }

    /**
     * Whitelist estricta para evitar open-redirect:
     * - Debe arrancar con "/" (path absoluto local).
     * - NO puede arrancar con "//" (protocol-relative).
     * - NO puede contener "\\" (windows-style protocol-relative).
     * - NO puede contener "://" (URL absoluta).
     * - Largo razonable.
     */
    public static function isSafeNext(string $next): bool
    {
        if ($next === '' || strlen($next) > 2048) {
            return false;
        }
        if ($next[0] !== '/') {
            return false;
        }
        if (strncmp($next, '//', 2) === 0) {
            return false;
        }
        if (strpos($next, '\\') !== false) {
            return false;
        }
        if (strpos($next, '://') !== false) {
            return false;
        }
        return true;
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
