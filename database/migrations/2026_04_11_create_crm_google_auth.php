<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    // Conexiones OAuth2 de Google Calendar, a nivel de usuario o de empresa.
    // Cuando usuario_id IS NULL, es una conexion "empresa-wide" usada cuando el modo
    // agenda_google_auth_mode del empresa_config_crm esta en 'empresa'.
    // Cuando usuario_id tiene valor, es la conexion personal del operador.
    // Los tokens se guardan encriptados con openssl_encrypt (clave derivada de APP_KEY + empresa_id).
    $db->exec("
    CREATE TABLE IF NOT EXISTS crm_google_auth (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        usuario_id INT NULL,
        google_email VARCHAR(255) NOT NULL,
        access_token TEXT NOT NULL,
        refresh_token TEXT NOT NULL,
        token_expiry DATETIME NOT NULL,
        calendar_id VARCHAR(255) NOT NULL DEFAULT 'primary',
        scope VARCHAR(500) NULL,
        connected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_sync_at DATETIME NULL,
        last_error TEXT NULL,
        UNIQUE KEY uk_crm_google_auth_empresa_usuario (empresa_id, usuario_id),
        KEY idx_crm_google_auth_empresa (empresa_id),
        KEY idx_crm_google_auth_expiry (token_expiry)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
};
