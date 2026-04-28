<?php

declare(strict_types=1);

/**
 * Smoke test del NotificationDispatcherService.
 * Uso: php tools/smoke_notifications_tick.php
 *
 * Equivalente a lo que el endpoint /api/internal/notifications/tick hace cuando
 * lo llama n8n, pero sin pasar por HTTP. Útil para validar la lógica de PHP
 * antes de activar el workflow remoto.
 */

define('BASE_PATH', dirname(__DIR__));

// Cargar .env
if (is_file(BASE_PATH . '/.env')) {
    foreach (file(BASE_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        putenv($line);
        [$key, $value] = explode('=', $line, 2);
        $_ENV[$key] = $value;
    }
}

// Autoloader compatible con la convención de la suite (app/core, app/modules, app/shared minúscula).
spl_autoload_register(function ($class) {
    $path = BASE_PATH . '/' . str_replace(['App\\', '\\'], ['app/', '/'], $class) . '.php';
    $path = str_replace('app/Core', 'app/core', $path);
    $path = str_replace('app/Modules', 'app/modules', $path);
    $path = str_replace('app/Shared', 'app/shared', $path);
    if (is_file($path)) {
        require_once $path;
    }
});

$started = microtime(true);
$dispatcher = new App\Core\Services\NotificationDispatcherService();
$result = $dispatcher->tick();
$elapsedMs = (int) round((microtime(true) - $started) * 1000);
$result['elapsed_ms'] = $elapsedMs;

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
