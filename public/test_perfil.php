<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Core\Environment;
use App\Modules\EmpresaConfig\EmpresaConfigService;
use App\Modules\Tango\TangoApiClient;
use App\Core\Database;

Environment::load(__DIR__ . '/../.env');
Database::init();

$configService = EmpresaConfigService::forCrm();
$config = $configService->getConfig();

$token = trim((string) ($config->tango_connect_token ?? ''));
$companyId = trim((string) ($config->tango_connect_company_id ?? ''));
$apiUrl = trim((string) ($config->tango_api_url ?? ''));
$clientKey = trim((string) ($config->tango_connect_key ?? ''));

if ($apiUrl === '' && $clientKey !== '') {
    $apiUrl = sprintf('https://%s.connect.axoft.com/Api', str_replace('/', '-', $clientKey));
}

$client = new TangoApiClient($apiUrl, $token, $companyId, $clientKey !== '' ? $clientKey : null);
print_r($client->getPerfilPedidoById(7));
echo "\n====================\n";
print_r($client->getPerfilPedidoById(1));
