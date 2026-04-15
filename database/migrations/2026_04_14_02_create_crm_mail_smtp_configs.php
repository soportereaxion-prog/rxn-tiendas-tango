<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    // Tabla crm_mail_smtp_configs: configuración SMTP POR USUARIO para envíos
    // masivos. Es INTENCIONALMENTE separada del SMTP transaccional (smtp_global,
    // smtp_master) porque los envíos masivos tienen límites y comportamiento
    // distinto (batch, pausa, from diferente, etc.).
    //
    // password_encrypted se guarda cifrado con el mecanismo estándar del
    // proyecto (ver App\Core\Crypto si existe, si no se discute con el Rey).
    //
    // max_per_batch y pause_seconds son los parámetros que viajan en el payload
    // del job hacia n8n para no reventar el servidor SMTP.
    $db->exec("
    CREATE TABLE IF NOT EXISTS crm_mail_smtp_configs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        usuario_id INT NOT NULL,
        nombre VARCHAR(120) NOT NULL DEFAULT 'SMTP Masivo',
        host VARCHAR(180) NOT NULL,
        port SMALLINT UNSIGNED NOT NULL DEFAULT 587,
        username VARCHAR(180) NOT NULL,
        password_encrypted TEXT NOT NULL,
        encryption ENUM('none','ssl','tls') NOT NULL DEFAULT 'tls',
        from_email VARCHAR(180) NOT NULL,
        from_name VARCHAR(180) NULL,
        max_per_batch SMALLINT UNSIGNED NOT NULL DEFAULT 50,
        pause_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 5,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        last_test_at DATETIME NULL,
        last_test_status ENUM('ok','fail') NULL,
        last_test_error TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at DATETIME NULL DEFAULT NULL,
        UNIQUE KEY uk_crm_mail_smtp_configs_usuario (empresa_id, usuario_id),
        KEY idx_crm_mail_smtp_configs_empresa (empresa_id),
        KEY idx_crm_mail_smtp_configs_deleted (deleted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
};
