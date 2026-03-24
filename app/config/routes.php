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
    $router->get('/empresas', [\App\Modules\Empresas\EmpresaController::class, 'index']);
    $router->get('/empresas/crear', [\App\Modules\Empresas\EmpresaController::class, 'create']);
    $router->post('/empresas', [\App\Modules\Empresas\EmpresaController::class, 'store']);
    $router->get('/empresas/{id}/editar', [\App\Modules\Empresas\EmpresaController::class, 'edit']);
    $router->post('/empresas/{id}', [\App\Modules\Empresas\EmpresaController::class, 'update']);

    // --- MÓDULO AUTH ---
    $router->get('/login', [\App\Modules\Auth\AuthController::class, 'showLogin']);
    $router->post('/login', [\App\Modules\Auth\AuthController::class, 'processLogin']);
    $router->get('/logout', [\App\Modules\Auth\AuthController::class, 'logout']);

    // --- MÓDULO EMPRESA CONFIG ---
    $router->get('/mi-empresa/configuracion', [\App\Modules\EmpresaConfig\EmpresaConfigController::class, 'index']);
    $router->post('/mi-empresa/configuracion', [\App\Modules\EmpresaConfig\EmpresaConfigController::class, 'store']);

    // --- MÓDULO USUARIOS OPERATIVOS ---
    $router->get('/mi-empresa/usuarios', [\App\Modules\Usuarios\UsuarioController::class, 'index']);
    $router->get('/mi-empresa/usuarios/crear', [\App\Modules\Usuarios\UsuarioController::class, 'create']);
    $router->post('/mi-empresa/usuarios', [\App\Modules\Usuarios\UsuarioController::class, 'store']);
    $router->get('/mi-empresa/usuarios/{id}/editar', [\App\Modules\Usuarios\UsuarioController::class, 'edit']);
    $router->post('/mi-empresa/usuarios/{id}', [\App\Modules\Usuarios\UsuarioController::class, 'update']);

    // --- MÓDULO TANGO CONNECT ---
    $router->get('/mi-empresa/sync/articulos', [\App\Modules\Tango\Controllers\TangoSyncController::class, 'syncArticulos']);
    $router->get('/mi-empresa/sync/precios', [\App\Modules\Tango\Controllers\TangoSyncController::class, 'syncPrecios']);
    $router->get('/mi-empresa/sync/stock', [\App\Modules\Tango\Controllers\TangoSyncController::class, 'syncStock']);
    
    // --- MÓDULO ARTÍCULOS ---
    $router->get('/mi-empresa/articulos', [\App\Modules\Articulos\ArticuloController::class, 'index']);
    $router->post('/mi-empresa/articulos/purgar', [\App\Modules\Articulos\ArticuloController::class, 'purgar']);
    $router->post('/mi-empresa/articulos/eliminar-masivo', [\App\Modules\Articulos\ArticuloController::class, 'eliminarMasivo']);
    $router->get('/mi-empresa/articulos/editar', [\App\Modules\Articulos\ArticuloController::class, 'editar']);
    $router->post('/mi-empresa/articulos/editar', [\App\Modules\Articulos\ArticuloController::class, 'actualizar']);

    // --- MÓDULO PEDIDOS WEB ---
    $router->get('/mi-empresa/pedidos', [\App\Modules\Pedidos\Controllers\PedidoWebController::class, 'index']);
    $router->get('/mi-empresa/pedidos/{id}', [\App\Modules\Pedidos\Controllers\PedidoWebController::class, 'show']);

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

    // --- RUTAS FRONT PÚBLICO (AL FINAL DEL ARCHIVO PARA EVITAR COLISIONES) ---
    // NOTA: {slug} capturará dinámicamente cualquier string que no haya matcheado con rutas estáticas superiores
    $router->get('/public-error', function () {
        View::render('app/modules/Store/views/error_tienda.php');
    });
    
    $router->get('/{slug}', [\App\Modules\Store\Controllers\StoreController::class, 'index']);
    $router->get('/{slug}/producto/{id}', [\App\Modules\Store\Controllers\StoreController::class, 'showProduct']);
    $router->get('/{slug}/carrito', [\App\Modules\Store\Controllers\CartController::class, 'index']);
    $router->post('/{slug}/carrito/add', [\App\Modules\Store\Controllers\CartController::class, 'add']);
    $router->post('/{slug}/carrito/update', [\App\Modules\Store\Controllers\CartController::class, 'update']);
    $router->post('/{slug}/carrito/remove', [\App\Modules\Store\Controllers\CartController::class, 'remove']);
    $router->get('/{slug}/checkout', [\App\Modules\Store\Controllers\CheckoutController::class, 'index']);
    $router->post('/{slug}/checkout/confirmar', [\App\Modules\Store\Controllers\CheckoutController::class, 'confirm']);

};
