<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    $db->exec("
    CREATE TABLE IF NOT EXISTS articulo_store_flags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        articulo_codigo_externo VARCHAR(191) NOT NULL,
        mostrar_oferta_store TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_articulo_store_flags_empresa_codigo (empresa_id, articulo_codigo_externo),
        KEY idx_articulo_store_flags_oferta (empresa_id, mostrar_oferta_store)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
};
