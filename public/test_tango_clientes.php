<?php
$url = "https://000357-017.connect.axoft.com/Api/Get?process=2117&pageSize=10&pageIndex=0&view=";

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

echo "HTTP Code: $httpCode\n";
echo "Response snippet:\n";
// Print decoded nicely formatted to read the fields
$data = json_decode((string)$response, true);
if (isset($data['value']) && count($data['value']) > 0) {
    print_r($data['value'][0]); // Print the first client to inspect the fields
} else {
    echo "No value array found or empty.\n";
    print_r($data);
}
