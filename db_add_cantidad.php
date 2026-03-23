<?php
define('BASE_PATH', __DIR__);
require 'vendor/autoload.php';

$envFile = BASE_PATH . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (trim($line) && !str_starts_with(trim($line), '#')) putenv($line);
    }
}

try {
    $db = App\Core\Database::getConnection();
    $db->exec("ALTER TABLE empresa_config ADD COLUMN cantidad_articulos_sync INT NOT NULL DEFAULT 50");
    echo "Column cantidad_articulos_sync appended.\n";
} catch (Exception $e) { 
    echo "INFO: " . $e->getMessage(); 
}
