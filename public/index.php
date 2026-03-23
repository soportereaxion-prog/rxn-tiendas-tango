<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// Cargar .env si existe (mínimo nativo, sin librerías externas).
$envFile = BASE_PATH . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        putenv($line);
    }
}

require_once BASE_PATH . '/vendor/autoload.php';

use App\Core\App;

App::run();
