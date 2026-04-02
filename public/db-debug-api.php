<?php
$pdo = new PDO("mysql:host=127.0.0.1;port=3307;dbname=rxn_tiendas_core;charset=utf8", 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$crm = $pdo->query("SELECT * FROM empresa_config_crm WHERE empresa_id = 1;")->fetch(PDO::FETCH_ASSOC);

echo "tango_perfil_snapshot_json in CRM:\n";
var_dump($crm['tango_perfil_snapshot_json']);

$tiendas = $pdo->query("SELECT * FROM empresa_config WHERE empresa_id = 1;")->fetch(PDO::FETCH_ASSOC);

echo "\ntango_perfil_snapshot_json in TIENDAS:\n";
var_dump($tiendas['tango_perfil_snapshot_json']);
