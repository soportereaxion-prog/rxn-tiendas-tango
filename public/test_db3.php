<?php
require __DIR__ . "/../vendor/autoload.php";
\App\Core\Context::initialize();
$pdo = \App\Core\Database::getInstance();
$stmt = $pdo->query("SELECT id, nombre, tango_perfil_pedido, tango_perfil_snapshot_json FROM usuarios WHERE id = 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo json_encode($row, JSON_PRETTY_PRINT);

