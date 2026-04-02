<?php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=rxntiendas_ia;charset=utf8mb4", "root", "");
$stmt = $pdo->query("SELECT * FROM empresas_crm_config WHERE empresa_id = 1");
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

$url = $apiUrl . "/GetById?process=20020&id=7";
$opts = [
  "http" => [
    "method" => "GET",
    "header" => "Authorization: Bearer " . $token . "\r\n" .
                "companyId: " . $companyId . "\r\n"
  ]
];
$context = stream_context_create($opts);
$result = file_get_contents($url, false, $context);
echo $result;

