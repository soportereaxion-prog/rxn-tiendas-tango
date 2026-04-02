<?php
require __DIR__ . '/../app/core/Database.php';

$db = App\Core\Database::getConnection();

$tables = ['empresa_config', 'empresa_config_crm'];

foreach ($tables as $t) {
    echo "<h3>Table: $t</h3>";
    $cols = [
        'tango_perfil_pedido_id' => 'INT NULL',
        'tango_perfil_pedido_codigo' => 'VARCHAR(100) NULL',
        'tango_perfil_pedido_nombre' => 'VARCHAR(255) NULL',
        'tango_perfil_snapshot_json' => 'JSON NULL',
        'tango_perfil_snapshot_date' => 'DATETIME NULL'
    ];
    
    foreach ($cols as $col => $type) {
        try {
            $db->exec("ALTER TABLE `$t` ADD COLUMN `$col` $type");
            echo "Added $col to $t<br>";
        } catch (Throwable $e) {
            echo "Failed $col on $t: " . $e->getMessage() . "<br>";
        }
    }
}
