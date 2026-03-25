<?php

use App\Core\Router;
use App\Core\View;
use App\Core\Database;

return function (Router $router): void {

    // Ruta raíz — Home Principal con menú
    $router->get('/', function () {
        View::render('app/modules/dashboard/views/home.php');
    });
    
    // --- MI PERFIL (B2B) ---
    $router->get('/mi-perfil', [\App\Modules\Usuarios\UsuarioPerfilController::class, 'index']);
    $router->post('/mi-perfil', [\App\Modules\Usuarios\UsuarioPerfilController::class, 'guardar']);
    $router->post('/mi-perfil/dashboard-order', [\App\Modules\Usuarios\UsuarioPerfilController::class, 'guardarOrdenDashboard']);

    // --- MÓDULO EMPRESAS ---
    $router->get('/empresas', [\App\Modules\Empresas\EmpresaController::class, 'index']);
    $router->get('/empresas/crear', [\App\Modules\Empresas\EmpresaController::class, 'create']);
    $router->post('/empresas', [\App\Modules\Empresas\EmpresaController::class, 'store']);
    $router->get('/empresas/{id}/editar', [\App\Modules\Empresas\EmpresaController::class, 'edit']);
    $router->post('/empresas/{id}', [\App\Modules\Empresas\EmpresaController::class, 'update']);

    // --- MÓDULO ADMIN GLOBAL ---
    $router->get('/admin/smtp-global', [\App\Modules\Admin\Controllers\GlobalConfigController::class, 'showSmtpGlobal']);
    $router->post('/admin/smtp-global', [\App\Modules\Admin\Controllers\GlobalConfigController::class, 'updateSmtpGlobal']);
    $router->post('/admin/smtp-global/test', [\App\Modules\Admin\Controllers\GlobalConfigController::class, 'testConnection']);

    // --- MÓDULO AUTH ---
    $router->get('/login', [\App\Modules\Auth\AuthController::class, 'showLogin']);
    $router->post('/login', [\App\Modules\Auth\AuthController::class, 'processLogin']);
    $router->get('/logout', [\App\Modules\Auth\AuthController::class, 'logout']);

    // Email Lifecycle & Recovery
    $router->get('/auth/verify', [\App\Modules\Auth\VerificationController::class, 'verify']);
    $router->get('/auth/resend-verify', [\App\Modules\Auth\VerificationController::class, 'showResend']);
    $router->post('/auth/resend-verify', [\App\Modules\Auth\VerificationController::class, 'processResend']);
    
    // Password Recovery Universal
    $router->get('/auth/forgot', [\App\Modules\Auth\PasswordResetController::class, 'showForgot']);
    $router->post('/auth/forgot', [\App\Modules\Auth\PasswordResetController::class, 'processForgot']);
    $router->get('/auth/reset', [\App\Modules\Auth\PasswordResetController::class, 'showReset']);
    $router->post('/auth/reset', [\App\Modules\Auth\PasswordResetController::class, 'processReset']);

    // --- MÓDULO EMPRESA CONFIG ---
    $router->get('/mi-empresa/configuracion', [\App\Modules\EmpresaConfig\EmpresaConfigController::class, 'index']);
    $router->post('/mi-empresa/configuracion', [\App\Modules\EmpresaConfig\EmpresaConfigController::class, 'store']);
    $router->post('/mi-empresa/configuracion/test-smtp', [\App\Modules\EmpresaConfig\EmpresaConfigController::class, 'testConnection']);

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

    // --- MÓDULO CLIENTES WEB ---
    $router->get('/mi-empresa/clientes', [\App\Modules\ClientesWeb\Controllers\ClienteWebController::class, 'index']);
    $router->get('/mi-empresa/clientes/{id}/editar', [\App\Modules\ClientesWeb\Controllers\ClienteWebController::class, 'edit']);
    $router->post('/mi-empresa/clientes/{id}/editar', [\App\Modules\ClientesWeb\Controllers\ClienteWebController::class, 'update']);
    $router->post('/mi-empresa/clientes/{id}/validar-tango', [\App\Modules\ClientesWeb\Controllers\ClienteWebController::class, 'validarTango']);
    $router->post('/mi-empresa/clientes/{id}/enviar-pendientes', [\App\Modules\ClientesWeb\Controllers\ClienteWebController::class, 'enviarPendientes']);

    // --- MÓDULO PEDIDOS WEB ---
    $router->get('/mi-empresa/pedidos', [\App\Modules\Pedidos\Controllers\PedidoWebController::class, 'index']);
    $router->get('/mi-empresa/pedidos/{id}', [\App\Modules\Pedidos\Controllers\PedidoWebController::class, 'show']);
    $router->post('/mi-empresa/pedidos/{id}/reprocesar', [\App\Modules\Pedidos\Controllers\PedidoWebController::class, 'reprocesar']);

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
            $stmt = $pdo->query("SELECT payload_enviado, respuesta_tango FROM pedidos_web WHERE estado_tango = 'error_envio_tango' ORDER BY id DESC LIMIT 1");
            $row  = $stmt->fetch();
            header('Content-Type: application/json');
            echo json_encode($row);
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
    
    // Auth & Dashboards B2C
    $router->get('/{slug}/login', [\App\Modules\ClientesWeb\Controllers\ClienteAuthController::class, 'showLoginForm']);
    $router->post('/{slug}/login', [\App\Modules\ClientesWeb\Controllers\ClienteAuthController::class, 'processLogin']);
    $router->get('/{slug}/registro', [\App\Modules\ClientesWeb\Controllers\ClienteAuthController::class, 'showRegisterForm']);
    $router->post('/{slug}/registro', [\App\Modules\ClientesWeb\Controllers\ClienteAuthController::class, 'processRegister']);
    $router->get('/{slug}/logout', [\App\Modules\ClientesWeb\Controllers\ClienteAuthController::class, 'logout']);

    $router->get('/{slug}/mis-pedidos', [\App\Modules\Store\Controllers\MisPedidosController::class, 'index']);
    $router->get('/{slug}/mis-pedidos/ver/{id}', [\App\Modules\Store\Controllers\MisPedidosController::class, 'show']);

    // Rutas Store Clientes
    $router->get('/{slug}', [\App\Modules\Store\Controllers\StoreController::class, 'index']);
    $router->get('/{slug}/producto/{id}', [\App\Modules\Store\Controllers\StoreController::class, 'showProduct']);
    $router->get('/{slug}/carrito', [\App\Modules\Store\Controllers\CartController::class, 'index']);
    $router->post('/{slug}/carrito/add', [\App\Modules\Store\Controllers\CartController::class, 'add']);
    $router->post('/{slug}/carrito/update', [\App\Modules\Store\Controllers\CartController::class, 'update']);
    $router->post('/{slug}/carrito/remove', [\App\Modules\Store\Controllers\CartController::class, 'remove']);
    $router->get('/{slug}/checkout', [\App\Modules\Store\Controllers\CheckoutController::class, 'index']);
    $router->post('/{slug}/checkout/confirmar', [\App\Modules\Store\Controllers\CheckoutController::class, 'confirm']);

};
