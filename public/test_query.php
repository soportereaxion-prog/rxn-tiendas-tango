<?php
require '../vendor/autoload.php';
$pdo = new PDO('mysql:host=localhost;dbname=rxntiendas', 'root', '');
$stmt = $pdo->query('SELECT tango_perfil_snapshot_json FROM crm_usuarios WHERE tango_perfil_pedido_id = 7 LIMIT 1');
echo $stmt->fetchColumn();
