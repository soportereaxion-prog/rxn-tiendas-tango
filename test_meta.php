<?php
$cwd = 'd:/RXNAPP/3.3/www/rxnTiendasIA';
define('BASE_PATH', $cwd);
require $cwd . '/vendor/autoload.php';

// Cargar .env
$envFile = $cwd . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        putenv(trim($line));
    }
}
$_SERVER['HTTP_HOST'] = 'localhost';

try {
    $repo = new \App\Modules\EmpresaConfig\EmpresaConfigRepository();
    $stmt = \App\Core\Database::getConnection()->query('SELECT * FROM empresa_config WHERE empresa_id=1 LIMIT 1');
    $config = $stmt->fetch(PDO::FETCH_OBJ);

    if (!$config || empty($config->tango_connect_token)) {
        die("No hay token para probar.\n");
    }

    $apiUrl = rtrim($config->tango_api_url ?? '', '/');
    $tangoKeyParsed = str_replace('/', '-', $config->tango_connect_key ?? '');
    $finalUrl = rtrim(sprintf("https://%s.connect.axoft.com/Api", $tangoKeyParsed), '/');
    if (empty($config->tango_connect_key) && filter_var($apiUrl, FILTER_VALIDATE_URL)) {
        $finalUrl = rtrim($apiUrl, '/');
    }

    $client = new \App\Modules\Tango\TangoApiClient($finalUrl, $config->tango_connect_token, $config->tango_connect_company_id ?? '1', $config->tango_connect_key ?? '');

    $reflector = new ReflectionClass($client);
    $prop = $reflector->getProperty('client');
    $prop->setAccessible(true);
    $apiClient = $prop->getValue($client);

    echo "============= RAW PROCESS 2941 (Depositos) =============\n";
    try {
        $res = $apiClient->get('/Api/Get', [
            'process' => 2941,
            'pageSize' => 10,
            'pageIndex' => 0,
            'view' => ''
        ]);
        echo json_encode($res, JSON_PRETTY_PRINT) . "\n";
    } catch (\Exception $e) { echo "ERROR: " . $e->getMessage() . "\n"; }

    echo "\n============= RAW PROCESS 984 (Listas) =============\n";
    try {
        $res = $apiClient->get('/Api/Get', [
            'process' => 984,
            'pageSize' => 10,
            'pageIndex' => 0,
            'view' => ''
        ]);
        echo json_encode($res, JSON_PRETTY_PRINT) . "\n";
    } catch (\Exception $e) { echo "ERROR: " . $e->getMessage() . "\n"; }
    
    echo "\n============= RESULTADO PARSEADO LOCAL =============\n";
    $depositos = $client->getMaestroDepositos();
    $listas = $client->getMaestroListasPrecio();
    echo "DEPOSITOS DB: " . $config->deposito_codigo . "\n";
    echo "LISTAS DB [1]: " . $config->lista_precio_1 . "\n";
    echo "LISTAS DB [2]: " . $config->lista_precio_2 . "\n";
    echo "DEPOSITOS FINAL: \n" . json_encode($depositos, JSON_PRETTY_PRINT) . "\n";
    echo "LISTAS FINAL: \n" . json_encode($listas, JSON_PRETTY_PRINT) . "\n";

} catch (\Exception $e) {
    echo "Falla: " . $e->getMessage() . "\n";
}
