<?php

$envValues = [];
$envPath = defined('BASE_PATH') ? BASE_PATH . '/.env' : dirname(__DIR__, 2) . '/.env';

if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if ($key === '') {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $envValues[$key] = $value;
    }
}

$env = static function (string $key, string $default = '') use ($envValues): string {
    if (array_key_exists($key, $envValues)) {
        return $envValues[$key];
    }

    $value = getenv($key);

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return (string) $value;
};

return [
    'host'    => $env('DB_HOST', '127.0.0.1'),
    'port'    => $env('DB_PORT', '3306'),
    'dbname'  => $env('DB_NAME', ''),
    'user'    => $env('DB_USER', ''),
    'pass'    => array_key_exists('DB_PASS', $envValues) ? $envValues['DB_PASS'] : ((getenv('DB_PASS') !== false) ? (string) getenv('DB_PASS') : ''),
    'charset' => $env('DB_CHARSET', 'utf8mb4'),
];
