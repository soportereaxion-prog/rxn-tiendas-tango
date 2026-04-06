<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    // 1. Crear tabla relacional entre teléfonos (números origen de Anura) y Clientes del CRM (Tango)
    $db->exec("
    CREATE TABLE IF NOT EXISTS crm_telefonos_clientes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        cliente_id INT NOT NULL,
        numero_origen VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_tel_cli_empresa_numero (empresa_id, numero_origen),
        KEY idx_tel_cli_cliente (cliente_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 2. Agregar 'cliente_id' a la tabla crm_llamadas para persistir la vinculación automática
    $stmt = $db->query("SHOW COLUMNS FROM crm_llamadas LIKE 'cliente_id'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE crm_llamadas ADD COLUMN cliente_id INT NULL AFTER usuario_id");
        $db->exec("ALTER TABLE crm_llamadas ADD KEY idx_llamadas_cliente (cliente_id)");
    }
};
