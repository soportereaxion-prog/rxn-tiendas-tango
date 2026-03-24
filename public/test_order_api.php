<?php
$url = "https://000357-017.connect.axoft.com/Api/Create?process=19845";

for ($i = 0; $i <= 3; $i++) {
    $payload = [
        "FECHA_PEDIDO" => "2026-03-24",
        "ID_GVA14" => 1,
        "ES_CLIENTE_HABITUAL" => true,
        "NOTA_PEDIDO_WEB" => "RXN_7",
        "ID_GVA43_TALON_PED" => 6,
        "ID_STA22" => 1,
        "ESTADO" => 2,
        "OBSERVACIONES" => "Test",
        "ID_PERFIL_PEDIDO" => $i,
        "RENGLON_DTO" => [
            [
                "ARTICULO_CODIGO" => "ART_EXPORTAC.",
                "CANTIDAD_A_FACTURAR" => 1,
                "CANTIDAD_PEDIDA" => 1,
                "PRECIO" => 90000
            ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "ApiAuthorization: 390A44F6-EE7C-4AFB-80CC-227CBA68FFC3",
        "Company: 157",
        "Content-Type: application/json"
    ]);
    $res = curl_exec($ch);
    echo "ID $i: " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . " RES: " . substr($res, 0, 150) . "\n";
}

echo "\n--- DB SCHEMA ---\n";
$pdo = new PDO('mysql:host=127.0.0.1;port=3307;dbname=rxn_tiendas_core;charset=utf8mb4', 'root', '');
$stmt = $pdo->query('DESCRIBE articulos');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
