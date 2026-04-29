<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    $columns = [
        'cotizacion'        => "ALTER TABLE crm_presupuestos ADD COLUMN cotizacion DECIMAL(15,4) NOT NULL DEFAULT 1 AFTER estado",
        'proximo_contacto'  => "ALTER TABLE crm_presupuestos ADD COLUMN proximo_contacto DATETIME NULL AFTER transporte_id_interno",
        'vigencia'          => "ALTER TABLE crm_presupuestos ADD COLUMN vigencia DATETIME NULL AFTER proximo_contacto",
        'leyenda_1'         => "ALTER TABLE crm_presupuestos ADD COLUMN leyenda_1 VARCHAR(60) NULL AFTER vigencia",
        'leyenda_2'         => "ALTER TABLE crm_presupuestos ADD COLUMN leyenda_2 VARCHAR(60) NULL AFTER leyenda_1",
        'leyenda_3'         => "ALTER TABLE crm_presupuestos ADD COLUMN leyenda_3 VARCHAR(60) NULL AFTER leyenda_2",
        'leyenda_4'         => "ALTER TABLE crm_presupuestos ADD COLUMN leyenda_4 VARCHAR(60) NULL AFTER leyenda_3",
        'leyenda_5'         => "ALTER TABLE crm_presupuestos ADD COLUMN leyenda_5 VARCHAR(60) NULL AFTER leyenda_4",
    ];

    foreach ($columns as $columnName => $alterSql) {
        $stmt = $db->query("SHOW COLUMNS FROM crm_presupuestos LIKE " . $db->quote($columnName));
        if (!$stmt->fetch()) {
            $db->exec($alterSql);
        }
    }
};
