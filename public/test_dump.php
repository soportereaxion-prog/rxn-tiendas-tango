<?php
require __DIR__ . "/../vendor/autoload.php";
$configRepo = new \App\Modules\EmpresaConfig\EmpresaConfigRepository();
$config = $configRepo->findByEmpresaId(1);
$client = \App\Modules\Tango\TangoApiFactory::create($config->tango_connect_token, $config->tango_connect_company_id);
$res = $client->client->get("GetById", ["process" => 20020, "id" => 7]);
echo json_encode(array_keys($res["value"] ?? $res["resultData"] ?? $res["data"][0] ?? $res["data"] ?? []));

