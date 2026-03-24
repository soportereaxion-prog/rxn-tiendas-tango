<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3307;dbname=rxn_tiendas_core;charset=utf8mb4', 'root', '');
$pdo->exec("ALTER TABLE empresa_config ADD COLUMN imagen_default_producto VARCHAR(255) NULL AFTER deposito_codigo");
echo "Company config fallback image column added.";
$stmt = $pdo->query("DESCRIBE empresa_config");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
