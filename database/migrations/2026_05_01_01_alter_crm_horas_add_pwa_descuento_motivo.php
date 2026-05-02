<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * PWA Horas — release 1.43.0.
 *
 * Suma a `crm_horas`:
 *  - `tmp_uuid_pwa VARCHAR(50) NULL UNIQUE` — idempotencia del sync mobile.
 *  - `descuento_segundos INT NOT NULL DEFAULT 0` — descuento opcional al neto.
 *  - `motivo_descuento TEXT NULL` — justificación del descuento (textarea).
 *
 * Idempotente: chequea si la columna ya existe antes de crearla. UNIQUE en MySQL
 * no cuenta NULL como duplicado, así que las filas existentes conviven OK.
 */
return function (): void {
    $db = Database::getConnection();

    // tmp_uuid_pwa
    $stmt = $db->query("SHOW COLUMNS FROM crm_horas LIKE 'tmp_uuid_pwa'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE crm_horas
            ADD COLUMN tmp_uuid_pwa VARCHAR(50) NULL AFTER created_by,
            ADD UNIQUE KEY uniq_crm_horas_tmp_uuid_pwa (tmp_uuid_pwa)");
    }

    // descuento_segundos
    $stmt = $db->query("SHOW COLUMNS FROM crm_horas LIKE 'descuento_segundos'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE crm_horas
            ADD COLUMN descuento_segundos INT NOT NULL DEFAULT 0 AFTER concepto");
    }

    // motivo_descuento
    $stmt = $db->query("SHOW COLUMNS FROM crm_horas LIKE 'motivo_descuento'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE crm_horas
            ADD COLUMN motivo_descuento TEXT NULL AFTER descuento_segundos");
    }
};
