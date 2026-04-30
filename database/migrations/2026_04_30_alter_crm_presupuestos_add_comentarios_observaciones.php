<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    $columns = [
        'comentarios'   => "ALTER TABLE crm_presupuestos ADD COLUMN comentarios TEXT NULL AFTER leyenda_5",
        'observaciones' => "ALTER TABLE crm_presupuestos ADD COLUMN observaciones TEXT NULL AFTER comentarios",
    ];

    foreach ($columns as $columnName => $alterSql) {
        $stmt = $db->query("SHOW COLUMNS FROM crm_presupuestos LIKE " . $db->quote($columnName));
        if (!$stmt->fetch()) {
            $db->exec($alterSql);
        }
    }
};
