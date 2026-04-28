<?php

declare(strict_types=1);

/**
 * Smoke test del WebPushService.
 * Uso: php tools/smoke_web_push.php
 *
 * Verifica que VAPID está cargado, que la tabla existe, y que sendToUser()
 * a un usuario sin subs devuelve { sent:0, failed:0, removed:0 } sin tronar.
 */

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

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

spl_autoload_register(function ($class) {
    $path = BASE_PATH . '/' . str_replace(['App\\', '\\'], ['app/', '/'], $class) . '.php';
    $path = str_replace('app/Core', 'app/core', $path);
    $path = str_replace('app/Modules', 'app/modules', $path);
    $path = str_replace('app/Shared', 'app/shared', $path);
    if (is_file($path)) {
        require_once $path;
    }
});

$svc = new App\Core\Services\WebPushService();
echo 'WebPushService configured: ' . ($svc->isConfigured() ? 'YES' : 'NO') . PHP_EOL;
echo 'VAPID public key (preview): ' . substr($svc->getPublicKey() ?? '(null)', 0, 30) . '...' . PHP_EOL;

$db = App\Core\Database::getConnection();
$stmt = $db->query('SHOW TABLES LIKE "web_push_subscriptions"');
echo 'Tabla web_push_subscriptions: ' . ($stmt->fetch() ? 'OK' : 'FALTA') . PHP_EOL;

$result = $svc->sendToUser(1, 1, 'Test', 'Body de prueba', '/test', ['source' => 'smoke']);
echo 'sendToUser sin subs registradas: ' . json_encode($result) . PHP_EOL;
