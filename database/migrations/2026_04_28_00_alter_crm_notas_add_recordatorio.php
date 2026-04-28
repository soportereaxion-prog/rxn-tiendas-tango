<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    $stmt = $db->query("SHOW COLUMNS FROM crm_notas LIKE 'fecha_recordatorio'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE crm_notas ADD COLUMN fecha_recordatorio DATETIME NULL DEFAULT NULL AFTER tags");
    }

    $stmt = $db->query("SHOW COLUMNS FROM crm_notas LIKE 'recordatorio_disparado_at'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE crm_notas ADD COLUMN recordatorio_disparado_at DATETIME NULL DEFAULT NULL AFTER fecha_recordatorio");
    }

    $stmt = $db->query("SHOW COLUMNS FROM crm_notas LIKE 'created_by'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE crm_notas ADD COLUMN created_by INT NULL DEFAULT NULL AFTER recordatorio_disparado_at");
    }

    // Índice para el late firer: encontrar rápido recordatorios pendientes por usuario.
    $stmt = $db->query("SHOW INDEX FROM crm_notas WHERE Key_name = 'idx_notas_recordatorio_pendiente'");
    if (!$stmt->fetch()) {
        $db->exec("CREATE INDEX idx_notas_recordatorio_pendiente ON crm_notas (empresa_id, created_by, recordatorio_disparado_at, fecha_recordatorio)");
    }
};
