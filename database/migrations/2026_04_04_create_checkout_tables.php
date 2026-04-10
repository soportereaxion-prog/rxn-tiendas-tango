<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    // 1. Crear Tabla clientes_web
    $db->exec("
    CREATE TABLE IF NOT EXISTS clientes_web (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        codigo_tango VARCHAR(15) NULL COMMENT 'Si es nulo se manda 000000',
        nombre VARCHAR(100) NOT NULL,
        apellido VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL,
        telefono VARCHAR(50) NULL,
        documento VARCHAR(50) NULL,
        razon_social VARCHAR(150) NULL,
        direccion VARCHAR(255) NULL,
        localidad VARCHAR(100) NULL,
        provincia VARCHAR(100) NULL,
        codigo_postal VARCHAR(20) NULL,
        observaciones TEXT NULL,
        activo TINYINT(1) DEFAULT 1,
        id_gva14_tango INT NULL COMMENT 'ID Técnico en Cliente Tango',
        id_gva01_condicion_venta INT NULL COMMENT 'ID de Condición Venta en Tango',
        id_gva10_lista_precios INT NULL COMMENT 'ID de Lista de Precios en Tango',
        id_gva23_vendedor INT NULL COMMENT 'ID de Vendedor en Tango',
        id_gva24_transporte INT NULL COMMENT 'ID de Transporte en Tango',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (empresa_id),
        INDEX (email),
        INDEX (documento)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Alter table to add fields if they don't exist (Retrocompatibilidad si existía)
    try {
        $stmt = $db->query("SHOW COLUMNS FROM clientes_web LIKE 'id_gva14_tango'");
        if (!$stmt->fetch()) {
            $db->exec("ALTER TABLE clientes_web ADD COLUMN id_gva14_tango INT NULL AFTER activo");
            $db->exec("ALTER TABLE clientes_web ADD COLUMN id_gva01_condicion_venta INT NULL AFTER id_gva14_tango");
            $db->exec("ALTER TABLE clientes_web ADD COLUMN id_gva10_lista_precios INT NULL AFTER id_gva01_condicion_venta");
            $db->exec("ALTER TABLE clientes_web ADD COLUMN id_gva23_vendedor INT NULL AFTER id_gva10_lista_precios");
            $db->exec("ALTER TABLE clientes_web ADD COLUMN id_gva24_transporte INT NULL AFTER id_gva23_vendedor");
        }
    } catch (Exception $e) { }

    // 2. Crear Tabla pedidos_web
    $db->exec("
    CREATE TABLE IF NOT EXISTS pedidos_web (
        id INT AUTO_INCREMENT PRIMARY KEY,
        empresa_id INT NOT NULL,
        cliente_web_id INT NOT NULL,
        codigo_cliente_tango_usado VARCHAR(15) NULL,
        total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        observaciones TEXT NULL,
        estado_tango ENUM('pendiente_envio_tango', 'enviado_tango', 'error_envio_tango') DEFAULT 'pendiente_envio_tango',
        tango_pedido_numero VARCHAR(50) NULL,
        payload_enviado JSON NULL,
        respuesta_tango JSON NULL,
        mensaje_error TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (empresa_id),
        INDEX (cliente_web_id),
        INDEX (estado_tango)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 3. Crear Tabla pedidos_web_renglones
    $db->exec("
    CREATE TABLE IF NOT EXISTS pedidos_web_renglones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pedido_web_id INT NOT NULL,
        articulo_id INT NOT NULL,
        cantidad INT NOT NULL DEFAULT 1,
        precio_unitario DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        nombre_articulo VARCHAR(255) NOT NULL,
        INDEX (pedido_web_id),
        INDEX (articulo_id),
        FOREIGN KEY (pedido_web_id) REFERENCES pedidos_web(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
};
