<?php

declare(strict_types=1);

return function (): void {
    $db = \App\Core\Database::getConnection();
    
    $sql = "CREATE TABLE IF NOT EXISTS rxn_sync_status (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        entidad VARCHAR(50) NOT NULL COMMENT 'cliente, articulo',
        local_id INT NULL COMMENT 'ID de la tabla local',
        tango_id INT NULL COMMENT 'ID de Tango',
        estado ENUM('vinculado', 'pendiente', 'conflicto', 'error') NOT NULL DEFAULT 'pendiente',
        mensaje_error TEXT NULL,
        fecha_ultima_sync DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_empresa_entidad_local (empresa_id, entidad, local_id),
        INDEX idx_estado (estado)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $db->exec($sql);
};
