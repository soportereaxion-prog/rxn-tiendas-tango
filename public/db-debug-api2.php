<?php
require '../vendor/autoload.php';
$pdo = new PDO("mysql:host=127.0.0.1;port=3307;dbname=rxn_tiendas_core;charset=utf8", 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$crm = $pdo->query("SELECT * FROM empresa_config_crm WHERE empresa_id = 1;")->fetch(PDO::FETCH_ASSOC);

$client = new \App\Modules\Tango\TangoApiClient(
    $crm['tango_api_url'] ?? 'https://000357-014.connect.axoft.com/Api', 
    $crm['tango_connect_token'], 
    '351', 
    $crm['tango_connect_key']
);

$res = $client->getPerfilesPedidos();
echo json_encode($res, JSON_PRETTY_PRINT);
