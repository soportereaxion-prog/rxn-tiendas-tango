<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3307;dbname=rxn_tiendas_core;charset=utf8mb4', 'root', '');
$sql = "CREATE TABLE IF NOT EXISTS articulo_imagenes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    articulo_id INT NOT NULL,
    ruta VARCHAR(255) NOT NULL,
    orden INT DEFAULT 1,
    es_principal TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_articulo (articulo_id),
    INDEX idx_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
$pdo->exec($sql);
echo "Image table created.";
