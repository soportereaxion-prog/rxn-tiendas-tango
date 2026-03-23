<?php

use App\Core\Router;
use App\Core\View;
use App\Core\Database;

return function (Router $router): void {

    // Ruta raíz — arranque base.
    $router->get('/', function () {
        echo 'rxnTiendasIA — bootstrap OK';
    });

    // TEMPORAL — test render de vista. Eliminar cuando haya vista real.
    $router->get('/test-vista', function () {
        View::render('app/modules/dashboard/views/index.php', [
            'mensaje' => 'Variable pasada correctamente desde routes.php',
        ]);
    });

    // TEMPORAL — test conexión DB con SELECT 1. Eliminar cuando haya modelo real.
    $router->get('/test-db', function () {
        try {
            $pdo  = Database::getConnection();
            $stmt = $pdo->query('SELECT 1 AS resultado');
            $row  = $stmt->fetch();
            echo 'Conexión DB: OK — resultado: ' . htmlspecialchars((string) $row['resultado'], ENT_QUOTES, 'UTF-8');
        } catch (\RuntimeException $e) {
            http_response_code(500);
            echo 'Conexión DB: ERROR — ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    });

};
