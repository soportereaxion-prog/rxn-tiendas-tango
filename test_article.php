<?php
require __DIR__ . '/app/bootstrap.php';

$pdo = \App\Core\Database::getConnection();
$stmt = $pdo->query("SELECT * FROM articulos WHERE codigo_externo = '002BOR'");
$art = $stmt->fetch(PDO::FETCH_ASSOC);

var_dump($art);

$cache = \App\Core\FileCache::get('catalogo_empresa_1_p1_s' . md5(''));
if ($cache) {
    foreach ($cache['articulos'] as $a) {
        if ($a['codigo_externo'] === '002BOR') {
            echo "CACHE ENCONTRADO:\n";
            var_dump($a);
            break;
        }
    }
}
