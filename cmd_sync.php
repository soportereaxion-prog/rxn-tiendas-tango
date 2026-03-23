<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

// Mock del contexto para CLI
$_SESSION = ['empresa_id' => 1];

// Como Context usa isset($_SESSION), lo vamos a forzar en Runtime
class_alias('App\Core\Context', 'MockContext'); // No needed, Context reads $_SESSION

use App\Modules\Tango\Services\TangoSyncService;

try {
    $service = new TangoSyncService();
    $stats = $service->syncStock();
    echo "SYNC SUCCESSFUL\n";
    print_r($stats);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
