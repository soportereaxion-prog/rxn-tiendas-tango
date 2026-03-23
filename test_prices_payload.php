<?php
define('BASE_PATH', __DIR__);
require 'vendor/autoload.php';

$envFile = BASE_PATH . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (trim($line) && !str_starts_with(trim($line), '#')) putenv($line);
    }
}

try {
    $db = App\Core\Database::getConnection();
    $config = $db->query("SELECT * FROM empresa_config ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    $token = $config['tango_connect_token'] ?? '';
    $key = $config['tango_connect_key'] ?? '';
    $companyId = $config['tango_connect_company_id'] ?? '';
    
    // Si no hay key real, usamos el dummy que nos dió la jefa
    if (empty($key)) $key = '000357/014';
    $keyDash = str_replace('/', '-', $key);

    $url = "https://{$keyDash}.connect.axoft.com/Api/Get?process=20091&pageSize=3&pageIndex=0&view=";

    echo "Hitting: $url\n";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "ApiAuthorization: $token",
        "Company: $companyId",
        "Client-Id: $key",
        "Accept: application/json"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP CODE: $httpCode\n";
    echo "Payload snippet:\n" . substr((string)$response, 0, 2000) . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
