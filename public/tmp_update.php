<?php
require_once __DIR__ . '/../app/core/Database.php';

try {
    $db = \App\Core\Database::getConnection();
    $stmt = $db->exec("UPDATE crm_pedidos_servicio SET usuario_id = 1, usuario_nombre = 'Sergio Majeras' WHERE usuario_id IS NULL");
    echo "Updated " . ($stmt !== false ? $stmt : 0) . " rows.";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
