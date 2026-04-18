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

Context::setEmpresaId(1);

$service = TangoService::forCrm();
$raw = $service->getApiClient()->getRawClient();

$probeId = (int) ($argv[1] ?? 26576);

echo "==== 1) GetById?process=19845&id={$probeId} ====\n";
try {
    $res = $raw->get('GetById', ['process' => 19845, 'id' => $probeId]);
    echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} catch (\Throwable $e) {
    echo "ERROR GetById: " . $e->getMessage() . "\n";
}

echo "\n==== 2) Get?process=19845 (primera página, pageSize=50) ====\n";
try {
    $res = $raw->get('Get', ['process' => 19845, 'pageSize' => 50, 'pageIndex' => 0, 'view' => '']);
    $list = $res['data']['resultData']['list'] ?? $res['resultData']['list'] ?? [];
    echo "Items devueltos: " . count($list) . "\n\n";

    if (!empty($list)) {
        echo "--- Primer item (schema completo) ---\n";
        echo json_encode($list[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

        echo "--- Resumen de estados encontrados ---\n";
        $estados = [];
        $campoEstadoCandidatos = ['ESTADO', 'ID_ESTADO', 'ID_ESTADO_NOTA', 'COD_ESTADO', 'ESTADO_NOTA', 'INACTIVO', 'ANULADO', 'FECHA_ANULACION'];
        foreach ($list as $item) {
            foreach ($campoEstadoCandidatos as $campo) {
                if (array_key_exists($campo, $item)) {
                    $v = is_scalar($item[$campo]) ? (string) $item[$campo] : json_encode($item[$campo]);
                    $estados[$campo][$v] = ($estados[$campo][$v] ?? 0) + 1;
                }
            }
        }
        foreach ($estados as $campo => $valores) {
            echo "Campo '$campo': ";
            foreach ($valores as $val => $cnt) {
                echo "[$val x$cnt] ";
            }
            echo "\n";
        }

        echo "\n--- Claves disponibles en el primer item ---\n";
        echo implode(', ', array_keys($list[0])) . "\n";
    }
} catch (\Throwable $e) {
    echo "ERROR Get list: " . $e->getMessage() . "\n";
}
