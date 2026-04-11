<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    // Agregar tratativa_id a crm_pedidos_servicio (idempotente)
    $stmt = $db->query("SHOW COLUMNS FROM crm_pedidos_servicio LIKE 'tratativa_id'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE crm_pedidos_servicio ADD COLUMN tratativa_id INT NULL DEFAULT NULL AFTER empresa_id");
        $db->exec("ALTER TABLE crm_pedidos_servicio ADD INDEX idx_crm_pds_tratativa (empresa_id, tratativa_id)");
    }

    // Agregar tratativa_id a crm_presupuestos (idempotente)
    $stmt = $db->query("SHOW COLUMNS FROM crm_presupuestos LIKE 'tratativa_id'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE crm_presupuestos ADD COLUMN tratativa_id INT NULL DEFAULT NULL AFTER empresa_id");
        $db->exec("ALTER TABLE crm_presupuestos ADD INDEX idx_crm_presupuestos_tratativa (empresa_id, tratativa_id)");
    }
};
