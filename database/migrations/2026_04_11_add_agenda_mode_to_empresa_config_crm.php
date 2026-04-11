<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    // Parametro de empresa: define si las conexiones de Google Calendar de la agenda
    // son por usuario (default) o compartidas a nivel empresa.
    // El client_id y client_secret de OAuth son globales del sistema y viven en .env
    // (GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI), no por empresa.
    $stmt = $db->query("SHOW COLUMNS FROM empresa_config_crm LIKE 'agenda_google_auth_mode'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE empresa_config_crm ADD COLUMN agenda_google_auth_mode ENUM('usuario','empresa') NOT NULL DEFAULT 'usuario'");
    }
};
