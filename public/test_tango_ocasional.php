<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
define('BASE_PATH', 'd:\RXNAPP\3.3\www\rxnTiendasIA');
require 'd:\RXNAPP\3.3\www\rxnTiendasIA\vendor\autoload.php';

$envFile = 'd:\RXNAPP\3.3\www\rxnTiendasIA\.env';
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#')) continue;
    putenv($line);
}

$db = \App\Core\Database::getConnection();

$stmtConf = $db->query("SELECT tango_connect_token, tango_connect_company_id, tango_connect_key FROM empresa_config WHERE empresa_id = 1 LIMIT 1");
$conf = $stmtConf->fetch(PDO::FETCH_ASSOC);

$apiUrl = "https://" . str_replace('/', '-', $conf['tango_connect_key']) . ".connect.axoft.com/Api/Create?process=19845";

$headers = [
    'ApiAuthorization: ' . $conf['tango_connect_token'],
    'Company: ' . $conf['tango_connect_company_id'],
    'Client-Id: ' . $conf['tango_connect_key'],
    'Accept: application/json',
    'Content-Type: application/json'
];

function testPayload($name, $payloadArr, $apiUrl, $headers) {
    $out = "========================================\n";
    $out .= "Testing $name\n";
    $payload = json_encode($payloadArr);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $out .= "HTTP Code: $httpCode\n";
    $out .= "Response: $response\n";
    $out .= "========================================\n\n";
    file_put_contents(__DIR__ . '/tango_results.log', $out, FILE_APPEND);
}

$basePayload = [
    'FECHA_PEDIDO' => '2026-03-24',
    'NOTA_PEDIDO_WEB' => 'RXN_TEST_OCASIONAL',
    'ID_GVA43_TALON_PED' => 6,
    'ID_STA22' => 1,
    'ITEMS' => [
        [
            'ARTICULO_CODIGO' => '0100100272',
            'CANTIDAD' => 1,
            'PRECIO_UNITARIO' => 127600
        ]
    ]
];

$ocasionalData = [
    "RAZON_SOCIAL" => "TEST CLIENTE",
    "DOMICILIO" => "TEST DOMICILIO",
    "NRO_DOCUMENTO" => "11111111",
    "EMAIL" => "test@test.com",
    "TELEFONO" => "123456789",
    "LOCALIDAD" => "San Luis",
    "ID_PROVINCIA" => 1,
    "CODIGO_POSTAL" => "5700",
    "CONTACTO" => "TEST CONTACTO"
];

$nodeVariations = [
    'CLIENTE_OCASIONAL',
    'ClienteOcasional',
    'OCASIONAL',
    'CLIENTE',
    'DATOS_CLIENTE_OCASIONAL',
    'GVA14',
    'Ocasional',
    'DatosOcasional',
    'Cliente',
    'DetalleClienteOcasional'
];

foreach ($nodeVariations as $node) {
    $payload = $basePayload;
    $payload['ES_CLIENTE_HABITUAL'] = false;
    $payload['ID_GVA14'] = 0; // The C# backend might expect integer 0
    $payload['CODIGO_CLIENTE'] = ''; // Or empty string?
    $payload[$node] = $ocasionalData;
    testPayload("Node: $node with ID_GVA14=0 and CODIGO_CLIENTE=''", $payload, $apiUrl, $headers);
}
