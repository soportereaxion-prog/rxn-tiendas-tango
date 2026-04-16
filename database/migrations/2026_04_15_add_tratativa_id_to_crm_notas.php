<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    // Agregar tratativa_id a crm_notas (idempotente).
    // Patrón: FK blanda (sin constraint duro), consistente con crm_pedidos_servicio y
    // crm_presupuestos (ver 2026_04_11_add_tratativa_id_to_pds_presupuestos.php).
    $stmt = $db->query("SHOW COLUMNS FROM crm_notas LIKE 'tratativa_id'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE crm_notas ADD COLUMN tratativa_id INT NULL DEFAULT NULL AFTER cliente_id");
        $db->exec("ALTER TABLE crm_notas ADD INDEX idx_crm_notas_tratativa (empresa_id, tratativa_id)");
    }
};
