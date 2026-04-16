<?php
define('BASE_PATH', 'd:/RXNAPP/3.3/www/rxn_suite');
require BASE_PATH . '/app/core/Database.php';
$db = App\Core\Database::getConnection();
$stmt = $db->query('SHOW TABLES'); 
while ($row = $stmt->fetch(PDO::FETCH_NUM)) { 
    echo $row[0] . PHP_EOL; 
}
