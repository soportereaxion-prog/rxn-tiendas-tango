<?php

/**
 * Migración: 2026_04_04_create_rxn_live_vistas_table
 *
 * Crea la tabla para almacenar las vistas customizadas de los usuarios del módulo RXN_LIVE.
 */

return function (\PDO $pdo): void {

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS rxn_live_vistas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            dataset VARCHAR(100) NOT NULL,
            nombre VARCHAR(150) NOT NULL,
            config JSON NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_usuario_dataset (usuario_id, dataset)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

};
