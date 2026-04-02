<?php
require '../vendor/autoload.php';
$env = parse_ini_file('../.env');
$pdo = new PDO("mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset=utf8", $env['DB_USER'], $env['DB_PASS']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tiendas = $pdo->query("SELECT * FROM empresa_config;")->fetchAll(PDO::FETCH_ASSOC);
$crm = $pdo->query("SELECT * FROM empresa_config_crm;")->fetchAll(PDO::FETCH_ASSOC);

echo "TIENDAS:\n";
print_r($tiendas);
echo "\nCRM:\n";
print_r($crm);
