<?php
$pdo = new PDO("mysql:host=127.0.0.1;port=3307;dbname=rxn_tiendas_core;charset=utf8mb4", "root", "", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$stmt = $pdo->query("SELECT * FROM empresa_config_crm WHERE empresa_id = 1");
$config = $stmt->fetch(PDO::FETCH_ASSOC);

$token = trim($config["tango_connect_token"]);
$companyId = trim($config["tango_connect_company_id"]);
$clientKey = trim($config["tango_connect_key"]);
$rawUrl = trim($config["tango_api_url"] ?? "");

$apiUrl = "";
if ($rawUrl !== "") {
    $apiUrl = rtrim($rawUrl, "/");
    if (!preg_match("/\/api$/i", $apiUrl)) $apiUrl .= "/Api";
} else {
    $apiUrl = "https://" . str_replace("/", "-", $clientKey) . ".connect.axoft.com/Api";
}

$url = $apiUrl . "/Get?process=20020&pageSize=50&pageIndex=0";
$opts = [
  "http" => [ "method" => "GET", "header" => "Authorization: Bearer " . $token . "\r\ncompanyId: " . $companyId . "\r\n" ]
];
echo "GET 20020 LIST:\n" . file_get_contents($url, false, stream_context_create($opts)) . "\n\n";

$url2 = $apiUrl . "/GetById?process=20020&id=7";
echo "GETBYID 20020 ID 7:\n" . file_get_contents($url2, false, stream_context_create($opts)) . "\n";

