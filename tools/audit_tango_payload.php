<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
date_default_timezone_set('America/Argentina/Buenos_Aires');

$envFile = BASE_PATH . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        putenv($line);
    }
}

require_once BASE_PATH . '/vendor/autoload.php';

use App\Core\Context;
use App\Modules\Tango\TangoService;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    Context::setEmpresaId(1); // Forzamos Empresa 1
} catch (\Throwable $e) {
    echo "Fallo setEmpresaId: " . $e->getMessage() . "\n";
    die();
}

$output = [];

try {
    $service = TangoService::forCrm(); // Por defecto apuntamos al tunel del CRM
    $client = $service->getApiClient();
    $rawClient = $client->getRawClient();

    echo "Obteniendo 1 Articulo...\n";
    $arts = $rawClient->get('Get', ['process' => 87, 'pageSize' => 1, 'pageIndex' => 0, 'view' => '']);
    
    if (isset($arts['data']['resultData']['list'][0])) {
        $firstArt = $arts['data']['resultData']['list'][0];
        $idArt = $firstArt['ID_STA11'] ?? null;
        if ($idArt) {
            echo "Consultando GetById Articulo ID: $idArt\n";
            $fullArt = $client->getArticuloById((int)$idArt);
            $output['articulo_getbyid_response'] = $fullArt;
        }
    } else {
        echo "No se pudo obtener el ID del Articulo. Res: " . json_encode($arts) . "\n";
    }

    echo "Obteniendo 1 Cliente...\n";
    $clients = $rawClient->get('Get', ['process' => 2117, 'pageSize' => 1, 'pageIndex' => 0, 'view' => '']);
    
    if (isset($clients['data']['resultData']['list'][0])) {
        $firstCli = $clients['data']['resultData']['list'][0];
        $idCli = $firstCli['ID_GVA14'] ?? null;
        if ($idCli) {
            echo "Consultando GetById Cliente ID: $idCli\n";
            $fullCli = $client->getClienteById((int)$idCli);
            $output['cliente_getbyid_response'] = $fullCli;
        }
    } else {
        echo "No se pudo obtener el ID del Cliente. Res: " . json_encode($clients) . "\n";
    }

} catch (\Throwable $e) {
    $output['error'] = $e->getMessage();
    $output['trace'] = $e->getTraceAsString();
    echo "Error atrapado: " . $e->getMessage() . "\n";
}

$jsonOut = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

file_put_contents(BASE_PATH . '/docs/logs/tango_payload_audit.json', $jsonOut);
file_put_contents('C:\\Users\\charl\\.gemini\\antigravity\\brain\\08c84185-b532-4a3b-be7f-6bdb40f7b4bd\\tango_payload_audit.json', $jsonOut);

echo "Audit completada.\n";
