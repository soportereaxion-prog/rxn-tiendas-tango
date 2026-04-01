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
                'domain' => $_SERVER['HTTP_HOST'] ?? '',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            session_start();
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
