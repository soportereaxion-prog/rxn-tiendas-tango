<?php

return function (): void {
    $db = \App\Core\Database::getConnection();
    
    // Add clasificacion_codigo and clasificacion_id_tango to crm_presupuestos
    $db->exec('ALTER TABLE crm_presupuestos 
        ADD COLUMN clasificacion_codigo VARCHAR(50) NULL AFTER vendedor_id_interno,
        ADD COLUMN clasificacion_id_tango VARCHAR(50) NULL AFTER clasificacion_codigo');
};
