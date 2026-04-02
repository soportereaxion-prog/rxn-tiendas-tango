<?php
$db = new PDO('mysql:host=localhost;dbname=rxn_tiendas_tango;charset=utf8', 'root', '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $db->exec("ALTER TABLE empresas ADD COLUMN tiendas_modulo_notas TINYINT(1) NOT NULL DEFAULT 0 AFTER modulo_tiendas");
    echo "Added tiendas_modulo_notas\n";
} catch (Exception $e) {
    echo "tiendas_modulo_notas already exists or error: " . $e->getMessage() . "\n";
}

try {
    $db->exec("ALTER TABLE empresas ADD COLUMN crm_modulo_notas TINYINT(1) NOT NULL DEFAULT 0 AFTER modulo_crm");
    echo "Added crm_modulo_notas\n";
} catch (Exception $e) {
    echo "crm_modulo_notas already exists or error: " . $e->getMessage() . "\n";
}

$s=$db->query('show columns from empresas');
print_r($s->fetchAll((PDO::FETCH_ASSOC)));
