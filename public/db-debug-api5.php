<?php
require '../vendor/autoload.php';
$pdo = new PDO("mysql:host=127.0.0.1;port=3307;dbname=rxn_tiendas_core;charset=utf8", 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$crm = $pdo->query("SELECT * FROM empresa_config_crm WHERE empresa_id = 1;")->fetch(PDO::FETCH_ASSOC);

// Simulate the logic in getConnectTangoMetadata
$companyId = '351';
$token = $crm['tango_connect_token'];
$clientKey = $crm['tango_connect_key'];
$apiUrl = $crm['tango_api_url'];

$tangoKeyParsed = str_replace('/', '-', $clientKey);
$finalUrl = rtrim(sprintf("https://%s.connect.axoft.com/Api", $tangoKeyParsed), '/');

$client = new \App\Modules\Tango\TangoApiClient($finalUrl, $token, $companyId, $clientKey);
$perfilesObj = $client->getPerfilesPedidos();

echo "PERFILES OBJ:\n";
echo json_encode($perfilesObj);
