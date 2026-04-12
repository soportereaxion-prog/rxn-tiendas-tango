<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    // Credenciales OAuth de Google Calendar per-empresa (antes vivían en .env, ahora en DB).
    // google_oauth_client_secret se guarda encriptado con openssl (clave APP_KEY + empresa_id).
    $newCols = [
        'google_oauth_client_id' => "VARCHAR(255) NULL",
        'google_oauth_client_secret' => "TEXT NULL",
        'google_oauth_redirect_uri' => "VARCHAR(500) NULL",
    ];

    foreach ($newCols as $col => $type) {
        $stmt = $db->query("SHOW COLUMNS FROM empresa_config_crm LIKE '$col'");
        if (!$stmt->fetch()) {
            $db->exec("ALTER TABLE empresa_config_crm ADD COLUMN $col $type");
        }
    }

    // Expandir el ENUM agenda_google_auth_mode para incluir 'ambos'
    // (permite que empresa + usuarios individuales sincronicen en paralelo).
    // MySQL acepta ALTER COLUMN MODIFY para agregar valores a un ENUM existente.
    try {
        $stmt = $db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'empresa_config_crm'
              AND COLUMN_NAME = 'agenda_google_auth_mode'");
        $currentType = (string) ($stmt->fetchColumn() ?: '');

        if ($currentType !== '' && !str_contains($currentType, "'ambos'")) {
            $db->exec("ALTER TABLE empresa_config_crm MODIFY COLUMN agenda_google_auth_mode ENUM('usuario','empresa','ambos') NOT NULL DEFAULT 'usuario'");
        }
    } catch (\Throwable $e) {}

    // Agregar columna google_syncs JSON a crm_agenda_eventos para multi-auth push tracking
    $stmt = $db->query("SHOW COLUMNS FROM crm_agenda_eventos LIKE 'google_syncs'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE crm_agenda_eventos ADD COLUMN google_syncs JSON NULL AFTER sync_error");
    }
};
