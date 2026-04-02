<?php
require __DIR__ . '/../vendor/autoload.php';
\App\Core\Context::initialize();
\ = \App\Core\Database::getInstance();
\ = \->query('SELECT id, nombre, tango_perfil_pedido, tango_perfil_snapshot_json FROM usuarios WHERE id = 1');
\ = \->fetch(PDO::FETCH_ASSOC);
echo json_encode(\, JSON_PRETTY_PRINT);

