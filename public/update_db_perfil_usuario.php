<?php
require __DIR__ . '/vendor/autoload.php';

$db = App\Core\Database::getConnection();

$db->exec("ALTER TABLE usuarios 
    ADD COLUMN tango_perfil_pedido_id INT NULL,
    ADD COLUMN tango_perfil_pedido_codigo VARCHAR(100) NULL,
    ADD COLUMN tango_perfil_pedido_nombre VARCHAR(255) NULL,
    ADD COLUMN tango_perfil_snapshot_json TEXT NULL,
    ADD COLUMN tango_perfil_snapshot_synced_at DATETIME NULL;
");

echo "Columns added to usuarios successfully.\n";
