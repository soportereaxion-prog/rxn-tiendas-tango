<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    // Crear tabla crm_tratativas (agregador comercial tipo "oportunidad/deal")
    // Agrupa PDS + Presupuestos bajo un mismo caso de negociacion con un cliente.
    $db->exec("
    CREATE TABLE IF NOT EXISTS crm_tratativas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        numero INT NOT NULL,
        usuario_id INT NULL,
        usuario_nombre VARCHAR(180) NULL,
        cliente_id INT NULL,
        cliente_nombre VARCHAR(180) NULL,
        titulo VARCHAR(200) NOT NULL,
        descripcion TEXT NULL,
        estado ENUM('nueva','en_curso','ganada','perdida','pausada') NOT NULL DEFAULT 'nueva',
        probabilidad TINYINT UNSIGNED NOT NULL DEFAULT 0,
        valor_estimado DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        fecha_apertura DATE NULL,
        fecha_cierre_estimado DATE NULL,
        fecha_cierre_real DATE NULL,
        motivo_cierre TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at DATETIME NULL DEFAULT NULL,
        UNIQUE KEY uk_crm_tratativas_empresa_numero (empresa_id, numero),
        KEY idx_crm_tratativas_empresa_estado (empresa_id, estado),
        KEY idx_crm_tratativas_cliente (empresa_id, cliente_id),
        KEY idx_crm_tratativas_usuario (empresa_id, usuario_id),
        KEY idx_crm_tratativas_deleted (deleted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
};
