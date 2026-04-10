<?php

declare(strict_types=1);

use App\Core\Database;

return function (): void {
    $db = Database::getConnection();

    // 1. Validar si ya existe tiendas_modulo_rxn_live
    $stmt = $db->query("SHOW COLUMNS FROM empresas LIKE 'tiendas_modulo_rxn_live'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE empresas ADD COLUMN tiendas_modulo_rxn_live TINYINT(1) DEFAULT 0 AFTER crm_modulo_monitoreo");
    }

    // 2. Validar si crm_modulo_rxn_live ya existe
    $stmt = $db->query("SHOW COLUMNS FROM empresas LIKE 'crm_modulo_rxn_live'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE empresas ADD COLUMN crm_modulo_rxn_live TINYINT(1) DEFAULT 0 AFTER tiendas_modulo_rxn_live");
    }

    // 3. Si existe la vieja, migrar y eliminar
    $stmt = $db->query("SHOW COLUMNS FROM empresas LIKE 'modulo_rxn_live'");
    if ($stmt->fetch()) {
        $db->exec("UPDATE empresas SET tiendas_modulo_rxn_live = modulo_rxn_live WHERE modulo_tiendas = 1");
        $db->exec("UPDATE empresas SET crm_modulo_rxn_live = modulo_rxn_live WHERE modulo_crm = 1");
        $db->exec("ALTER TABLE empresas DROP COLUMN modulo_rxn_live");
    }
};
