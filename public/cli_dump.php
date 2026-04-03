<?php
define('BASE_PATH', 'd:\RXNAPP\3.3\www\rxn_suite');
require 'd:\RXNAPP\3.3\www\rxn_suite\vendor\autoload.php';

// Cargar .env
$envFile = 'd:\RXNAPP\3.3\www\rxn_suite\.env';
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#')) continue;
    putenv($line);
}

$db = \App\Core\Database::getConnection();
$stmt = $db->query("SELECT payload_enviado, respuesta_tango, mensaje_error FROM pedidos_web WHERE estado_tango = 'error_envio_tango' ORDER BY id DESC LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
