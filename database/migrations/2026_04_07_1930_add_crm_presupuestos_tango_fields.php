<?php
declare(strict_types=1);

return function (): void {
    $db = \App\Core\Database::getConnection();
    
    // Add tango sync fields to crm_presupuestos
    try {
        $db->exec('ALTER TABLE crm_presupuestos 
            ADD COLUMN tango_sync_status VARCHAR(50) NULL AFTER usuario_nombre,
            ADD COLUMN nro_comprobante_tango VARCHAR(100) NULL AFTER tango_sync_status,
            ADD COLUMN tango_sync_date DATETIME NULL AFTER nro_comprobante_tango,
            ADD COLUMN tango_sync_log TEXT NULL AFTER tango_sync_date');
    } catch (\Throwable $e) {}
};
