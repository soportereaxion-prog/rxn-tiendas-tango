<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    $tables = ['crm_pedidos_servicio'];

    foreach ($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
        if (!$stmt->fetch()) {
            continue;
        }

        $check = $db->query("SHOW COLUMNS FROM `{$table}` LIKE 'tango_id_gva21'");
        if (!$check->fetch()) {
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN tango_id_gva21 INT NULL DEFAULT NULL AFTER tango_sync_response");
            $db->exec("ALTER TABLE `{$table}` ADD INDEX idx_{$table}_tango_id_gva21 (empresa_id, tango_id_gva21)");
        }

        $check = $db->query("SHOW COLUMNS FROM `{$table}` LIKE 'tango_nro_pedido'");
        if (!$check->fetch()) {
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN tango_nro_pedido VARCHAR(30) NULL DEFAULT NULL AFTER tango_id_gva21");
        }

        $check = $db->query("SHOW COLUMNS FROM `{$table}` LIKE 'tango_estado'");
        if (!$check->fetch()) {
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN tango_estado TINYINT NULL DEFAULT NULL AFTER tango_nro_pedido");
        }

        $check = $db->query("SHOW COLUMNS FROM `{$table}` LIKE 'tango_estado_sync_at'");
        if (!$check->fetch()) {
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN tango_estado_sync_at DATETIME NULL DEFAULT NULL AFTER tango_estado");
        }

        // Backfill desde tango_sync_response (JSON) para pedidos ya enviados.
        // Se usa JSON_UNQUOTE + NULLIF(..., 'null') en vez de CAST('null' AS JSON)
        // porque MariaDB NO soporta CAST AS JSON (el tipo JSON es alias de LONGTEXT).
        // El patrón funciona en MariaDB y MySQL.
        $db->exec(<<<SQL
            UPDATE `{$table}`
            SET
                tango_id_gva21 = COALESCE(
                    tango_id_gva21,
                    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(tango_sync_response, '$.data.value.ID_GVA21')), 'null'),
                    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(tango_sync_response, '$.value.ID_GVA21')), 'null'),
                    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(tango_sync_response, '$.ID_GVA21')), 'null')
                ),
                tango_nro_pedido = COALESCE(
                    tango_nro_pedido,
                    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(tango_sync_response, '$.data.value.NRO_PEDIDO')), 'null'),
                    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(tango_sync_response, '$.value.NRO_PEDIDO')), 'null'),
                    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(tango_sync_response, '$.NRO_PEDIDO')), 'null')
                )
            WHERE tango_sync_status = 'success'
              AND tango_sync_response IS NOT NULL
        SQL);
    }
};
