<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    foreach (['crm_presupuestos', 'crm_pedidos_servicio'] as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
        if (!$stmt->fetch()) {
            continue;
        }

        $check = $db->query("SHOW COLUMNS FROM `{$table}` LIKE 'correos_enviados_count'");
        if (!$check->fetch()) {
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN correos_enviados_count INT NOT NULL DEFAULT 0");
        }

        $check = $db->query("SHOW COLUMNS FROM `{$table}` LIKE 'correos_ultimo_envio_at'");
        if (!$check->fetch()) {
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN correos_ultimo_envio_at DATETIME NULL DEFAULT NULL");
        }

        $check = $db->query("SHOW COLUMNS FROM `{$table}` LIKE 'correos_ultimo_error'");
        if (!$check->fetch()) {
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN correos_ultimo_error TEXT NULL DEFAULT NULL");
        }

        $check = $db->query("SHOW COLUMNS FROM `{$table}` LIKE 'correos_ultimo_error_at'");
        if (!$check->fetch()) {
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN correos_ultimo_error_at DATETIME NULL DEFAULT NULL");
        }
    }
};
