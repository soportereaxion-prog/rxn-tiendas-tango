<?php

declare(strict_types=1);

return function (): void {
    $db = \App\Core\Database::getConnection();

    // 1. Check if columns exist before adding them to be idempotent
    $stmt = $db->query("SHOW COLUMNS FROM rxn_sync_status LIKE 'direccion_ultima_sync'");
    if ($stmt->rowCount() === 0) {
        $db->exec("ALTER TABLE rxn_sync_status 
            ADD COLUMN direccion_ultima_sync ENUM('push', 'pull', 'link', 'none') NOT NULL DEFAULT 'none' AFTER estado,
            ADD COLUMN resultado_ultima_sync ENUM('ok', 'error', 'pending') NOT NULL DEFAULT 'pending' AFTER direccion_ultima_sync,
            ADD COLUMN fecha_ultimo_push DATETIME NULL AFTER fecha_ultima_sync,
            ADD COLUMN fecha_ultimo_pull DATETIME NULL AFTER fecha_ultimo_push
        ");
    }

    // 2. Create the rxn_sync_log table
    $db->exec("CREATE TABLE IF NOT EXISTS rxn_sync_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        entidad VARCHAR(50) NOT NULL COMMENT 'cliente, articulo',
        local_id INT NULL,
        tango_id INT NULL,
        direccion ENUM('push', 'pull', 'link') NOT NULL,
        resultado ENUM('ok', 'error') NOT NULL,
        mensaje TEXT NULL,
        payload_resumen JSON NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_empresa_entidad (empresa_id, entidad),
        INDEX idx_local_id (local_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
};
