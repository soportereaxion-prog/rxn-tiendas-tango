<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    $stmt = $db->query("SHOW COLUMNS FROM usuarios LIKE 'preferencia_zoom'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE usuarios ADD COLUMN preferencia_zoom TINYINT UNSIGNED NOT NULL DEFAULT 100 AFTER preferencia_fuente");
    }
};
