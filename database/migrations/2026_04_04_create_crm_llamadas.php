<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    // 1. Agregar 'anura_interno' a usuarios si no existe
    $stmt = $db->query("SHOW COLUMNS FROM usuarios LIKE 'anura_interno'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE usuarios ADD COLUMN anura_interno VARCHAR(50) NULL AFTER password_hash");
    }

    // 2. Crear tabla crm_llamadas
    $db->exec("
    CREATE TABLE IF NOT EXISTS crm_llamadas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        usuario_id INT NULL,
        fecha DATETIME NULL,
        origen VARCHAR(255) NULL,
        numero_origen VARCHAR(255) NULL,
        destino VARCHAR(255) NULL,
        duracion VARCHAR(50) NULL,
        interno VARCHAR(50) NULL,
        atendio VARCHAR(255) NULL,
        evento_link VARCHAR(255) NULL,
        mp3 VARCHAR(1000) NULL,
        precio DECIMAL(10,2) NULL,
        json_bruto TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL,
        KEY idx_llamadas_empresa (empresa_id),
        KEY idx_llamadas_usuario (usuario_id),
        KEY idx_llamadas_deleted (deleted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
};
