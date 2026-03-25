<?php
require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$db = \App\Core\Database::getConnection();
$stmt = $db->query("SELECT id, nombre, email, empresa_id, es_admin, es_rxn_admin FROM usuarios");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
