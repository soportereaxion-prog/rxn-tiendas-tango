<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Índice global para el tick de notificaciones disparado por n8n.
 *
 * El índice previo `idx_notas_recordatorio_pendiente` lleva (empresa_id, created_by,
 * recordatorio_disparado_at, fecha_recordatorio) — perfecto para el late firer
 * que ya filtra por usuario, pero ineficiente para el tick global que NO filtra
 * por empresa ni usuario y barre todo lo que esté vencido.
 *
 * Este índice cubre la query de NotificationDispatcherService::dispatchCrmNotas().
 */
return function (): void {
    $db = Database::getConnection();

    $stmt = $db->query("SHOW INDEX FROM crm_notas WHERE Key_name = 'idx_notas_recordatorio_global'");
    if (!$stmt->fetch()) {
        $db->exec(
            'CREATE INDEX idx_notas_recordatorio_global
              ON crm_notas (recordatorio_disparado_at, fecha_recordatorio, deleted_at, activo)'
        );
    }
};
