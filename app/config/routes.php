<?php

use App\Core\Router;
use App\Core\View;
use App\Core\Database;

return function (Router $router): void {

    // Ruta raíz — Home Principal con menú
    $router->get('/', function () {
        View::render('app/modules/dashboard/views/home.php');
    });

    // --- MÓDULO EMPRESAS ---
    $router->get('/empresas', [new \App\Modules\Empresas\EmpresaController(), 'index']);
    $router->get('/empresas/crear', [new \App\Modules\Empresas\EmpresaController(), 'create']);
    $router->post('/empresas', [new \App\Modules\Empresas\EmpresaController(), 'store']);
    $router->get('/empresas/{id}/editar', [new \App\Modules\Empresas\EmpresaController(), 'edit']);
    $router->post('/empresas/{id}', [new \App\Modules\Empresas\EmpresaController(), 'update']);

    // --- MÓDULO AUTH ---
    $router->get('/login', [new \App\Modules\Auth\AuthController(), 'showLogin']);
    $router->post('/login', [new \App\Modules\Auth\AuthController(), 'processLogin']);
    $router->get('/logout', [new \App\Modules\Auth\AuthController(), 'logout']);

    // --- MÓDULO EMPRESA CONFIG ---
    $router->get('/mi-empresa/configuracion', [new \App\Modules\EmpresaConfig\EmpresaConfigController(), 'index']);
    $router->post('/mi-empresa/configuracion', [new \App\Modules\EmpresaConfig\EmpresaConfigController(), 'store']);

    // --- MÓDULO USUARIOS OPERATIVOS ---
    $router->get('/mi-empresa/usuarios', [new \App\Modules\Usuarios\UsuarioController(), 'index']);
    $router->get('/mi-empresa/usuarios/crear', [new \App\Modules\Usuarios\UsuarioController(), 'create']);
    $router->post('/mi-empresa/usuarios', [new \App\Modules\Usuarios\UsuarioController(), 'store']);
    $router->get('/mi-empresa/usuarios/{id}/editar', [new \App\Modules\Usuarios\UsuarioController(), 'edit']);
    $router->post('/mi-empresa/usuarios/{id}', [new \App\Modules\Usuarios\UsuarioController(), 'update']);

    // --- MÓDULO TANGO CONNECT ---
    $router->get('/mi-empresa/sync/articulos', [new \App\Modules\Tango\Controllers\TangoSyncController(), 'syncArticulos']);
    
    // --- MÓDULO ARTÍCULOS ---
    $router->get('/mi-empresa/articulos', [new \App\Modules\Articulos\ArticuloController(), 'index']);

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

