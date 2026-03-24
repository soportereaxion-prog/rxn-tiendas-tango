<?php
define('BASE_PATH', __DIR__);
require 'vendor/autoload.php';

$envFile = BASE_PATH . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (trim($line) && !str_starts_with(trim($line), '#')) putenv($line);
    }
}

try {
    $db = App\Core\Database::getConnection();

    // Crear Tabla clientes_web
    $sql1 = "
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
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (empresa_id),
        INDEX (email),
        INDEX (documento)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $db->exec($sql1);
    echo "Tabla clientes_web creada o verificada.\n";

    // Crear Tabla pedidos_web
    $sql2 = "
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
    ";
    $db->exec($sql2);
    echo "Tabla pedidos_web creada o verificada.\n";

    // Crear Tabla pedidos_web_renglones
    $sql3 = "
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
    ";
    $db->exec($sql3);
    echo "Tabla pedidos_web_renglones creada o verificada.\n";

} catch (Exception $e) {
    echo "ERROR CREANDO TABLAS: " . $e->getMessage() . "\n";
}
