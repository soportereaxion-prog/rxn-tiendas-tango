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

try {
    // We recreate the inside of getPerfilesPedidos but without the try/catch
    $reflection = new ReflectionClass($client);
    $prop = $reflection->getProperty('client');
    $prop->setAccessible(true);
    $apiClient = $prop->getValue($client);

    $data = $apiClient->get('/Api/Get', [
        'process' => 20020,
        'pageSize' => 500,
        'pageIndex' => 0,
        'view' => 'Habilitados'
    ]);
    echo "SUCCESS\n";
    var_dump($data);
} catch (\Exception $e) {
    echo "EXCEPTION\n";
    echo $e->getMessage() . "\n";
}
