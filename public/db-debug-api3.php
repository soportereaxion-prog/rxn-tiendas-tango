<?php
require '../vendor/autoload.php';
$pdo = new PDO("mysql:host=127.0.0.1;port=3307;dbname=rxn_tiendas_core;charset=utf8", 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$crm = $pdo->query("SELECT * FROM empresa_config_crm WHERE empresa_id = 1;")->fetch(PDO::FETCH_ASSOC);

$ch = curl_init('http://localhost:9021/mi-empresa/configuracion/tango-metadata');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'tango_api_url' => $crm['tango_api_url'],
    'tango_connect_company_id' => '351',
    'tango_connect_key' => $crm['tango_connect_key'],
    'tango_connect_token' => $crm['tango_connect_token']
]);

// Since the endpoint has `AuthService::requireLogin();`, this will fail with redirect!
// Let's just bypass it. Oh! I can't bypass it via curl because I have no session.
