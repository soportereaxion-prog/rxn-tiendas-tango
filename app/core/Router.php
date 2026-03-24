<?php

declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $uri, array|callable $handler): void
    {
        $this->routes['GET'][$uri] = $handler;
    }

    public function post(string $uri, array|callable $handler): void
    {
        $this->routes['POST'][$uri] = $handler;
    }

    public function dispatch(Request $request): void
    {
        $method = $request->getMethod();
        $uri    = $request->getUri();

        $resolveHandler = function($handler) {
            if (is_array($handler) && is_string($handler[0])) {
                $class = $handler[0];
                return [new $class(), $handler[1]];
            }
            return $handler;
        };

        if (isset($this->routes[$method][$uri])) {
            $handler = $resolveHandler($this->routes[$method][$uri]);
            ($handler)();
            return;
        }

        // Fallback: Evaluación de rutas dinámicas (Ej: /empresas/{id})
        foreach ($this->routes[$method] ?? [] as $route => $handler) {
            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<\1>[a-zA-Z0-9_-]+)', $route);
            $pattern = '#^' . $pattern . '$#';
            
            if (preg_match($pattern, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $handler = $resolveHandler($handler);
                call_user_func_array($handler, array_values($params));
                return;
            }
        }

        http_response_code(404);
        echo '404 Not Found';
    }
}
