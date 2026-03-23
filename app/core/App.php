<?php

declare(strict_types=1);

namespace App\Core;

class App
{
    public static function run(): void
    {
        // Inicializar contexto base (ej: identificar empresa activa)
        Context::init();

        $router = new Router();

        $routes = require BASE_PATH . '/app/config/routes.php';
        $routes($router);

        $request = new Request();
        $router->dispatch($request);
    }
}
