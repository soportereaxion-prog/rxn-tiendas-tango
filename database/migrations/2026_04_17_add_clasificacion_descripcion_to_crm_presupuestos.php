<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    $stmt = $db->query("SHOW COLUMNS FROM crm_presupuestos LIKE 'clasificacion_descripcion'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE crm_presupuestos ADD COLUMN clasificacion_descripcion VARCHAR(255) NULL DEFAULT NULL AFTER clasificacion_id_tango");
    }
};
