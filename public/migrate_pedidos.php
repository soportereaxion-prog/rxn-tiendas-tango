<?php
define('BASE_PATH', dirname(__DIR__));
$envFile = BASE_PATH . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        putenv(trim($line));
    }
}
require_once BASE_PATH . '/vendor/autoload.php';

try {
    $db = \App\Core\Database::getConnection();
    $db->exec("ALTER TABLE pedidos_web ADD COLUMN activo TINYINT(1) DEFAULT 1");
    echo "Column activo added successfully.\n";
} catch (\Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column activo already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
