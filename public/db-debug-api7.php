<?php
require '../vendor/autoload.php';
$pdo = new PDO("mysql:host=127.0.0.1;port=3307;dbname=rxn_tiendas_core;charset=utf8", 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$crm = $pdo->query("SELECT * FROM empresa_config_crm WHERE empresa_id = 1;")->fetch(PDO::FETCH_ASSOC);

$apiUrl = $crm['tango_api_url'];
$token = $crm['tango_connect_token'];
$companyId = '351';
$clientKey = $crm['tango_connect_key'];

$client = new \App\Modules\Tango\TangoApiClient($apiUrl, $token, $companyId, $clientKey);

echo "Testing connection with URL: $apiUrl\n";
var_dump($client->testConnection());
