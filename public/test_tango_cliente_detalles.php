<?php
$filtro = urlencode("WHERE COD_GVA14 = '010001'");
$url = "https://000357-017.connect.axoft.com/Api/GetByFilter?process=2117&view=&filtroSql=" . $filtro;
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "ApiAuthorization: 390A44F6-EE7C-4AFB-80CC-227CBA68FFC3",
    "Company: 157",
    "Accept: application/json"
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode((string)$response, true);
echo json_encode($data['list'][0] ?? $data, JSON_PRETTY_PRINT);
