<?php

/**
 * Migration para dividir modulo_rxn_live en tiendas_modulo_rxn_live y crm_modulo_rxn_live
 */

$sql = "
    -- Agregar nuevas columnas independizadas
    ALTER TABLE empresas 
        ADD COLUMN tiendas_modulo_rxn_live TINYINT(1) DEFAULT 0 AFTER crm_modulo_monitoreo,
        ADD COLUMN crm_modulo_rxn_live TINYINT(1) DEFAULT 0 AFTER tiendas_modulo_rxn_live;
        
    -- Migrar datos de la columna vieja hacia las nuevas dependiendo de sus padres
    UPDATE empresas 
        SET tiendas_modulo_rxn_live = modulo_rxn_live 
        WHERE modulo_tiendas = 1;
        
    UPDATE empresas 
        SET crm_modulo_rxn_live = modulo_rxn_live 
        WHERE modulo_crm = 1;

    -- Eliminar la columna vieja
    ALTER TABLE empresas DROP COLUMN modulo_rxn_live;
";
