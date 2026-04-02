<?php
$pdo = new PDO("mysql:host=127.0.0.1;port=3307;dbname=rxn_tiendas_core;charset=utf8mb4", "root", "", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
$stmt = $pdo->query("SELECT * FROM usuarios WHERE id = 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "USUARIO 1:\n";
echo json_encode(["id" => $row["id"], "nombre" => $row["nombre"], "tango_perfil_snapshot_json" => $row["tango_perfil_snapshot_json"]], JSON_PRETTY_PRINT) . "\n\n";

$stmt2 = $pdo->query("SELECT tango_perfil_snapshot_json FROM empresas_crm_config WHERE empresa_id = 1");
$row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
echo "EMPRESA CRM 1:\n";
echo json_encode($row2, JSON_PRETTY_PRINT) . "\n\n";

