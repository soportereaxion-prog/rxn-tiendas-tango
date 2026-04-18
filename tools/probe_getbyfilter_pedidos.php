<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

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

Context::setEmpresaId(1);

$service = TangoService::forCrm();
$raw = $service->getApiClient()->getRawClient();

// Test 1: GetByFilter con IN
echo "==== Test 1: GetByFilter?process=19845 con WHERE ID_GVA21 IN (...) ====\n";
$filtroSql = 'WHERE ID_GVA21 IN (26550,26575)';
$endpoint = 'GetByFilter?process=19845&view=&filtroSql=' . rawurlencode($filtroSql);
echo "Endpoint: {$endpoint}\n";
try {
    $res = $raw->get($endpoint);
    echo "Response status: " . ($res['status'] ?? 'n/a') . "\n";
    $list = $res['data']['resultData']['list'] ?? $res['resultData']['list'] ?? [];
    echo "Items devueltos: " . count($list) . "\n";
    if (!empty($list)) {
        echo json_encode($list[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "Cuerpo raw:\n";
        echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Test 2: GetByFilter con = simple
echo "\n==== Test 2: GetByFilter?process=19845 con WHERE ID_GVA21 = 26575 ====\n";
$filtroSql = 'WHERE ID_GVA21 = 26575';
$endpoint = 'GetByFilter?process=19845&view=&filtroSql=' . rawurlencode($filtroSql);
echo "Endpoint: {$endpoint}\n";
try {
    $res = $raw->get($endpoint);
    echo "Response status: " . ($res['status'] ?? 'n/a') . "\n";
    $list = $res['data']['resultData']['list'] ?? $res['resultData']['list'] ?? [];
    echo "Items devueltos: " . count($list) . "\n";
    if (!empty($list)) {
        echo json_encode($list[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "Cuerpo raw:\n";
        echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Test 3: GetById directo (sabemos que funciona)
echo "\n==== Test 3: GetById?process=19845&id=26575 (control) ====\n";
try {
    $res = $raw->get('GetById', ['process' => 19845, 'id' => 26575]);
    $value = $res['data']['value'] ?? null;
    if (is_array($value)) {
        echo "ID_GVA21: " . ($value['ID_GVA21'] ?? '?') . "\n";
        echo "NRO_PEDIDO: " . ($value['NRO_PEDIDO'] ?? '?') . "\n";
        echo "ESTADO: " . ($value['ESTADO'] ?? '?') . "\n";
    } else {
        echo "No value. Raw:\n";
        echo json_encode($res, JSON_PRETTY_PRINT) . "\n";
    }
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
