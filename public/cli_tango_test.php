<?php
define('BASE_PATH', 'd:\RXNAPP\3.3\www\rxnTiendasIA');
require 'd:\RXNAPP\3.3\www\rxnTiendasIA\vendor\autoload.php';

// Cargar .env
$envFile = 'd:\RXNAPP\3.3\www\rxnTiendasIA\.env';
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#')) continue;
    putenv($line);
}

$db = \App\Core\Database::getConnection();

// 2. Obtener config tango (asumiendo empresa_id = 1 para la prueba)
$stmtConf = $db->query("SELECT tango_connect_token, tango_connect_company_id, tango_connect_key FROM empresa_config WHERE empresa_id = 1 LIMIT 1");
$conf = $stmtConf->fetch(PDO::FETCH_ASSOC);

if (!$conf) {
    die("Faltan datos de config");
}

$apiUrl = "https://" . str_replace('/', '-', $conf['tango_connect_key']) . ".connect.axoft.com/Api/Create?process=19845";

$headers = [
    'ApiAuthorization: ' . $conf['tango_connect_token'],
    'Company: ' . $conf['tango_connect_company_id'],
    'Client-Id: ' . $conf['tango_connect_key'],
    'Accept: application/json',
    'Content-Type: application/json'
];

$renglonesVariations = [
    'RENGLONES',
    'ITEMS',
    'Items',
    'Renglones',
    'RENGLONES_PEDIDO',
    'PEDIDO_RENGLONES',
    'PEDIDO_ITEMS',
    'GVA03',
    'PEDIDOS_DETALLES',
    'DETALLE',
    'Detalle',
    'Articulos',
    'ARTICULOS'
];

foreach ($renglonesVariations as $key) {
    $payloadArr = [
        'FECHA_PEDIDO' => '2026-03-24',
        'ID_GVA14' => 1,
        'ES_CLIENTE_HABITUAL' => true,
        'NOTA_PEDIDO_WEB' => 'RXN_TEST',
        'ID_GVA43_TALON_PED' => 6,
        'ID_STA22' => 1, // At ROOT
        $key => [
            [
                'ARTICULO_CODIGO' => '0100100272',
                'CANTIDAD' => 1,
                'PRECIO_UNITARIO' => 127600
            ]
        ]
    ];
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

    if (strpos($response, 'El campo ID_STA22 es requerido') === false) {
        echo "¡ÉXITO o ERROR DIFERENTE EN CLAVE $key!\n";
        echo "HTTP Code: $httpCode\n";
        echo "Response: $response\n\n";
    }
}
