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
    // Check if column exists
    $result = $db->query("SHOW COLUMNS FROM crm_pedidos_servicio LIKE 'deleted_at'");
    if ($result->rowCount() == 0) {
        $db->exec("ALTER TABLE crm_pedidos_servicio ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
        echo "Added deleted_at to crm_pedidos_servicio.\n";
    }

    $result = $db->query("SHOW COLUMNS FROM crm_presupuestos LIKE 'deleted_at'");
    if ($result->rowCount() == 0) {
        $db->exec("ALTER TABLE crm_presupuestos ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
        echo "Added deleted_at to crm_presupuestos.\n";
    }

    echo "Done!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
