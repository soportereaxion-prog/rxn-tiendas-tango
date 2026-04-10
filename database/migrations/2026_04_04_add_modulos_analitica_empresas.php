<?php

return function (\PDO $db) {
    // 1. modulo_rxn_live
    $sqlAddLive = "
        SELECT COUNT(*)
        FROM information_schema.columns 
        WHERE table_schema = DATABASE()
        AND table_name = 'empresas' 
        AND column_name = 'modulo_rxn_live'
    ";
    if ($db->query($sqlAddLive)->fetchColumn() == 0) {
        $db->exec("ALTER TABLE empresas ADD COLUMN modulo_rxn_live TINYINT(1) NOT NULL DEFAULT 0 AFTER crm_modulo_notas");
    }

    // 2. crm_modulo_llamadas
    $sqlAddLlamadas = "
        SELECT COUNT(*)
        FROM information_schema.columns 
        WHERE table_schema = DATABASE()
        AND table_name = 'empresas' 
        AND column_name = 'crm_modulo_llamadas'
    ";
    if ($db->query($sqlAddLlamadas)->fetchColumn() == 0) {
        $db->exec("ALTER TABLE empresas ADD COLUMN crm_modulo_llamadas TINYINT(1) NOT NULL DEFAULT 0 AFTER modulo_rxn_live");
    }

    // 3. crm_modulo_monitoreo
    $sqlAddMonitoreo = "
        SELECT COUNT(*)
        FROM information_schema.columns 
        WHERE table_schema = DATABASE()
        AND table_name = 'empresas' 
        AND column_name = 'crm_modulo_monitoreo'
    ";
    if ($db->query($sqlAddMonitoreo)->fetchColumn() == 0) {
        $db->exec("ALTER TABLE empresas ADD COLUMN crm_modulo_monitoreo TINYINT(1) NOT NULL DEFAULT 0 AFTER crm_modulo_llamadas");
    }
};
