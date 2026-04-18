<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    foreach (['empresa_config', 'empresa_config_crm'] as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
        if (!$stmt->fetch()) {
            continue;
        }

        $check = $db->query("SHOW COLUMNS FROM `{$table}` LIKE 'documentos_cc_enabled'");
        if (!$check->fetch()) {
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN documentos_cc_enabled TINYINT(1) NOT NULL DEFAULT 0");
        }

        $check = $db->query("SHOW COLUMNS FROM `{$table}` LIKE 'documentos_cc_emails'");
        if (!$check->fetch()) {
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN documentos_cc_emails TEXT NULL DEFAULT NULL");
        }
    }
};
