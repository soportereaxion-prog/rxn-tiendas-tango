<?php
define('BASE_PATH', __DIR__ . '/..');
require BASE_PATH . '/app/core/Database.php';

$envFile = __DIR__ . '/../.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (trim($line) && !str_starts_with(trim($line), '#')) {
            putenv($line);
        }
    }
}

try {
    $db = App\Core\Database::getConnection();

    $sqlAddColumn = "
        SELECT COUNT(*)
        FROM information_schema.columns 
        WHERE table_schema = DATABASE()
        AND table_name = 'usuarios' 
        AND column_name = 'anura_interno'
    ";
    $stmt = $db->query($sqlAddColumn);
    
    if ($stmt->fetchColumn() == 0) {
        $db->exec("ALTER TABLE usuarios ADD COLUMN anura_interno VARCHAR(50) NULL AFTER password_hash");
        echo "Exito. Columna anura_interno agregada a usuarios.";
    } else {
        echo "Exito. Columna anura_interno ya existia.";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
