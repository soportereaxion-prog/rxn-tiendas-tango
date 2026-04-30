<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Suma `tmp_uuid_pwa` a `crm_presupuestos` para idempotencia del sync de la PWA mobile
 * (Iteración 42 — Fase 3 / Bloque C).
 *
 * El cliente PWA genera un UUID local (`TMP-<uuid>`) al crear el draft offline. Cuando
 * sincroniza al server, ese mismo UUID viaja en el payload y queda persistido acá. Si el
 * cliente reintenta el mismo POST (red intermitente), el server detecta la fila existente
 * por este campo y devuelve el id real sin crear duplicado.
 *
 * UNIQUE en MySQL no cuenta NULL como duplicado, así que los presupuestos existentes
 * (sin origen PWA) conviven sin chocar.
 */
return function (): void {
    $db = Database::getConnection();

    $stmt = $db->query("SHOW COLUMNS FROM crm_presupuestos LIKE 'tmp_uuid_pwa'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE crm_presupuestos
            ADD COLUMN tmp_uuid_pwa VARCHAR(50) NULL AFTER version_numero,
            ADD UNIQUE KEY uniq_crm_presupuestos_tmp_uuid_pwa (tmp_uuid_pwa)");
    }
};
