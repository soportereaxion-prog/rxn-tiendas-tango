<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

date_default_timezone_set('America/Argentina/Buenos_Aires');

// Cabeceras de Seguridad Globales
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Cargar .env si existe (mínimo nativo, sin librerías externas).
$envFile = BASE_PATH . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        putenv($line);
    }
}

require_once BASE_PATH . '/vendor/autoload.php';

// Cargar helpers globales
if (is_file(BASE_PATH . '/app/core/helpers.php')) {
    require_once BASE_PATH . '/app/core/helpers.php';
}

use App\Core\App;

App::run();
