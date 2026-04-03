<?php

declare(strict_types=1);

namespace App\Core;

class App
{
    public static function run(): void
    {
        // Seguridad para la sesión antes de iniciarla
        if (session_status() === PHP_SESSION_NONE) {
            // Mitiga secuestro de sesión y Session Fixation
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');

            session_set_cookie_params([
                'lifetime' => 86400,
                'path' => '/',
                // 'domain' no se debe forzar sin parsear puertos, rompe localhost
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            session_start();
        }

        // --- Gestión de Sesión Estricta (Operador Backoffice) ---
        if (isset($_SESSION['user_id'])) {
            $now = time();
            $idleTimeout = 21600; // 6 horas
            $absoluteTimeout = 43200; // 12 horas

            if (!isset($_SESSION['backoffice_created_at'])) {
                $_SESSION['backoffice_created_at'] = $now;
            }
            if (!isset($_SESSION['backoffice_last_activity'])) {
                $_SESSION['backoffice_last_activity'] = $now;
            }

            if (($now - $_SESSION['backoffice_last_activity']) > $idleTimeout || ($now - $_SESSION['backoffice_created_at']) > $absoluteTimeout) {
                // Limpiamos selectivamente para no romper sesiones aisladas (ej. ClienteWeb)
                unset(
                    $_SESSION['user_id'],
                    $_SESSION['empresa_id'],
                    $_SESSION['user_name'],
                    $_SESSION['anura_interno'],
                    $_SESSION['es_rxn_admin'],
                    $_SESSION['pref_theme'],
                    $_SESSION['pref_font'],
                    $_SESSION['es_admin'],
                    $_SESSION['dashboard_order'],
                    $_SESSION['backoffice_created_at'],
                    $_SESSION['backoffice_last_activity']
                );
            } else {
                // Renovar actividad
                $_SESSION['backoffice_last_activity'] = $now;
            }
        }

        // Inicializar contexto base (ej: identificar empresa activa)
        Context::init();

        $router = new Router();

        $routes = require BASE_PATH . '/app/config/routes.php';
        $routes($router);

        $request = new Request();
        $router->dispatch($request);
    }
}
