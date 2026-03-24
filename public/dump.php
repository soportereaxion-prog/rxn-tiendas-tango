<?php
require __DIR__ . '/../app/bootstrap.php';
$db = \App\Core\Database::getConnection();
$stmt = $db->query("SELECT payload_enviado, respuesta_tango FROM pedidos_web WHERE estado_tango = 'error_envio_tango' ORDER BY id DESC LIMIT 1");
header('Content-Type: application/json');
echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
