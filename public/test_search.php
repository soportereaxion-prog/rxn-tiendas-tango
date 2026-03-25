<?php
require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use App\Core\Database;
use App\Modules\ClientesWeb\ClienteWebRepository;

try {
    $repo = new ClienteWebRepository();
    // Test a search with "a" to trigger the LIKE clause
    $res = $repo->findAllPaginated(1, 1, 20, 'a', 'id', 'DESC');
    echo "SUCCESS! " . count($res) . " results found.\n";
    
    $count = $repo->countAll(1, 'a');
    echo "COUNT SUCCESS! " . $count . " counted.\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
