<?php

declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $uri, callable $handler): void
    {
        $this->routes['GET'][$uri] = $handler;
    }

    public function post(string $uri, callable $handler): void
    {
        $this->routes['POST'][$uri] = $handler;
    }

    public function dispatch(Request $request): void
    {
        $method = $request->getMethod();
        $uri    = $request->getUri();

        if (isset($this->routes[$method][$uri])) {
            ($this->routes[$method][$uri])();
            return;
        }

        http_response_code(404);
        echo '404 Not Found';
    }
}
