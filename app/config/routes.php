<?php

use App\Core\Database;
use App\Core\Router;
use App\Core\View;

return function (Router $router): void {
    $requireTiendas = static function (): void {
        \App\Modules\Empresas\EmpresaAccessService::requireTiendasAccess();
    };

    $requireCrm = static function (): void {
        \App\Modules\Empresas\EmpresaAccessService::requireCrmAccess();
    };

    $requireAnyOperational = static function (): void {
        \App\Modules\Empresas\EmpresaAccessService::requireAnyOperationalAccess();
    };

    $action = static function (string $controllerClass, string $method, ?callable $guard = null): callable {
        return static function (...$params) use ($controllerClass, $method, $guard): void {
            if ($guard !== null) {
                $guard();
            }

            $controller = new $controllerClass();
            $controller->{$method}(...$params);
        };
    };

    // Ruta raiz - Nivel 1 (Launcher Principal)
    $router->get('/', function () {
        View::render('app/modules/dashboard/views/home.php');
    });

    // --- SUBDASHBOARDS (NIVEL 2) ---
    $router->get('/admin/dashboard', function () {
        \App\Modules\Auth\AuthService::requireBackofficeAdmin();
        View::render('app/modules/dashboard/views/admin_dashboard.php');
    });

    $router->get('/mi-empresa/dashboard', function () {
        \App\Modules\Empresas\EmpresaAccessService::requireTiendasAccess();
        View::render('app/modules/dashboard/views/tenant_dashboard.php');
    });

    $router->get('/mi-empresa/crm/dashboard', function () {
        \App\Modules\Empresas\EmpresaAccessService::requireCrmAccess();
        View::render('app/modules/dashboard/views/crm_dashboard.php');
    });

    // --- MI PERFIL (B2B) ---
    $router->get('/mi-perfil', [\App\Modules\Usuarios\UsuarioPerfilController::class, 'index']);
    $router->post('/mi-perfil', [\App\Modules\Usuarios\UsuarioPerfilController::class, 'guardar']);
    $router->post('/mi-perfil/dashboard-order', [\App\Modules\Usuarios\UsuarioPerfilController::class, 'guardarOrdenDashboard']);

    // --- MODULO EMPRESAS ---
    $router->get('/empresas', [\App\Modules\Empresas\EmpresaController::class, 'index']);
    $router->get('/empresas/sugerencias', [\App\Modules\Empresas\EmpresaController::class, 'suggestions']);
    $router->get('/empresas/crear', [\App\Modules\Empresas\EmpresaController::class, 'create']);
    $router->post('/empresas', [\App\Modules\Empresas\EmpresaController::class, 'store']);
    $router->get('/empresas/{id}/editar', [\App\Modules\Empresas\EmpresaController::class, 'edit']);
    $router->post('/empresas/{id}', [\App\Modules\Empresas\EmpresaController::class, 'update']);

    // --- MODULO ADMIN GLOBAL ---
    $router->get('/admin/smtp-global', [\App\Modules\Admin\Controllers\GlobalConfigController::class, 'showSmtpGlobal']);
    $router->post('/admin/smtp-global', [\App\Modules\Admin\Controllers\GlobalConfigController::class, 'updateSmtpGlobal']);
    $router->post('/admin/smtp-global/test', [\App\Modules\Admin\Controllers\GlobalConfigController::class, 'testConnection']);
    $router->get('/admin/notas-modulos', [\App\Modules\Admin\Controllers\ModuleNotesController::class, 'index']);
    $router->post('/admin/notas-modulos', [\App\Modules\Admin\Controllers\ModuleNotesController::class, 'store']);

    // --- MODULO AUTH ---
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

    // --- MODULO EMPRESA CONFIG ---
    $router->get('/mi-empresa/configuracion', $action(\App\Modules\EmpresaConfig\EmpresaConfigController::class, 'index', $requireTiendas));
    $router->post('/mi-empresa/configuracion', $action(\App\Modules\EmpresaConfig\EmpresaConfigController::class, 'store', $requireTiendas));
    $router->post('/mi-empresa/configuracion/test-smtp', $action(\App\Modules\EmpresaConfig\EmpresaConfigController::class, 'testConnection', $requireTiendas));
    $router->post('/mi-empresa/configuracion/test-tango', $action(\App\Modules\EmpresaConfig\EmpresaConfigController::class, 'testConnectTango', $requireTiendas));
    $router->post('/mi-empresa/configuracion/tango-metadata', $action(\App\Modules\EmpresaConfig\EmpresaConfigController::class, 'getConnectTangoMetadata', $requireTiendas));

    $router->get('/mi-empresa/crm/configuracion', $action(\App\Modules\EmpresaConfig\EmpresaConfigController::class, 'index', $requireCrm));
    $router->post('/mi-empresa/crm/configuracion', $action(\App\Modules\EmpresaConfig\EmpresaConfigController::class, 'store', $requireCrm));
    $router->post('/mi-empresa/crm/configuracion/test-smtp', $action(\App\Modules\EmpresaConfig\EmpresaConfigController::class, 'testConnection', $requireCrm));
    $router->post('/mi-empresa/crm/configuracion/test-tango', $action(\App\Modules\EmpresaConfig\EmpresaConfigController::class, 'testConnectTango', $requireCrm));
    $router->post('/mi-empresa/crm/configuracion/tango-metadata', $action(\App\Modules\EmpresaConfig\EmpresaConfigController::class, 'getConnectTangoMetadata', $requireCrm));

    // --- AYUDA OPERATIVA ---
    $router->get('/mi-empresa/ayuda', $action(\App\Modules\Help\HelpController::class, 'operational', $requireAnyOperational));

    // --- MODULO USUARIOS OPERATIVOS ---
    $router->get('/mi-empresa/usuarios', $action(\App\Modules\Usuarios\UsuarioController::class, 'index', $requireAnyOperational));
    $router->get('/mi-empresa/usuarios/sugerencias', $action(\App\Modules\Usuarios\UsuarioController::class, 'suggestions', $requireAnyOperational));
    $router->get('/mi-empresa/usuarios/crear', $action(\App\Modules\Usuarios\UsuarioController::class, 'create', $requireAnyOperational));
    $router->post('/mi-empresa/usuarios', $action(\App\Modules\Usuarios\UsuarioController::class, 'store', $requireAnyOperational));
    $router->get('/mi-empresa/usuarios/{id}/editar', $action(\App\Modules\Usuarios\UsuarioController::class, 'edit', $requireAnyOperational));
    $router->post('/mi-empresa/usuarios/{id}', $action(\App\Modules\Usuarios\UsuarioController::class, 'update', $requireAnyOperational));

    // --- MODULO TANGO CONNECT ---
    $router->get('/mi-empresa/sync/todo', $action(\App\Modules\Tango\Controllers\TangoSyncController::class, 'syncTodo', $requireTiendas));
    $router->get('/mi-empresa/sync/articulos', $action(\App\Modules\Tango\Controllers\TangoSyncController::class, 'syncArticulos', $requireTiendas));
    $router->get('/mi-empresa/sync/precios', $action(\App\Modules\Tango\Controllers\TangoSyncController::class, 'syncPrecios', $requireTiendas));
    $router->get('/mi-empresa/sync/stock', $action(\App\Modules\Tango\Controllers\TangoSyncController::class, 'syncStock', $requireTiendas));

    $router->get('/mi-empresa/crm/sync/todo', $action(\App\Modules\Tango\Controllers\TangoSyncController::class, 'syncTodo', $requireCrm));
    $router->get('/mi-empresa/crm/sync/articulos', $action(\App\Modules\Tango\Controllers\TangoSyncController::class, 'syncArticulos', $requireCrm));
    $router->get('/mi-empresa/crm/sync/clientes', $action(\App\Modules\Tango\Controllers\TangoSyncController::class, 'syncClientes', $requireCrm));
    $router->get('/mi-empresa/crm/sync/precios', $action(\App\Modules\Tango\Controllers\TangoSyncController::class, 'syncPrecios', $requireCrm));
    $router->get('/mi-empresa/crm/sync/stock', $action(\App\Modules\Tango\Controllers\TangoSyncController::class, 'syncStock', $requireCrm));

    // --- MODULO ARTICULOS ---
    $router->get('/mi-empresa/articulos', $action(\App\Modules\Articulos\ArticuloController::class, 'index', $requireTiendas));
    $router->get('/mi-empresa/articulos/sugerencias', $action(\App\Modules\Articulos\ArticuloController::class, 'suggestions', $requireTiendas));
    $router->post('/mi-empresa/articulos/purgar', $action(\App\Modules\Articulos\ArticuloController::class, 'purgar', $requireTiendas));
    $router->post('/mi-empresa/articulos/eliminar-masivo', $action(\App\Modules\Articulos\ArticuloController::class, 'eliminarMasivo', $requireTiendas));
    $router->get('/mi-empresa/articulos/editar', $action(\App\Modules\Articulos\ArticuloController::class, 'editar', $requireTiendas));
    $router->post('/mi-empresa/articulos/editar', $action(\App\Modules\Articulos\ArticuloController::class, 'actualizar', $requireTiendas));

    $router->get('/mi-empresa/crm/articulos', $action(\App\Modules\Articulos\ArticuloController::class, 'index', $requireCrm));
    $router->get('/mi-empresa/crm/articulos/sugerencias', $action(\App\Modules\Articulos\ArticuloController::class, 'suggestions', $requireCrm));
    $router->post('/mi-empresa/crm/articulos/purgar', $action(\App\Modules\Articulos\ArticuloController::class, 'purgar', $requireCrm));
    $router->post('/mi-empresa/crm/articulos/eliminar-masivo', $action(\App\Modules\Articulos\ArticuloController::class, 'eliminarMasivo', $requireCrm));
    $router->get('/mi-empresa/crm/articulos/editar', $action(\App\Modules\Articulos\ArticuloController::class, 'editar', $requireCrm));
    $router->post('/mi-empresa/crm/articulos/editar', $action(\App\Modules\Articulos\ArticuloController::class, 'actualizar', $requireCrm));

    $router->get('/mi-empresa/crm/clientes', $action(\App\Modules\CrmClientes\CrmClienteController::class, 'index', $requireCrm));
    $router->get('/mi-empresa/crm/clientes/sugerencias', $action(\App\Modules\CrmClientes\CrmClienteController::class, 'suggestions', $requireCrm));
    $router->post('/mi-empresa/crm/clientes/purgar', $action(\App\Modules\CrmClientes\CrmClienteController::class, 'purgar', $requireCrm));
    $router->post('/mi-empresa/crm/clientes/eliminar-masivo', $action(\App\Modules\CrmClientes\CrmClienteController::class, 'eliminarMasivo', $requireCrm));
    $router->get('/mi-empresa/crm/clientes/editar', $action(\App\Modules\CrmClientes\CrmClienteController::class, 'editar', $requireCrm));
    $router->post('/mi-empresa/crm/clientes/editar', $action(\App\Modules\CrmClientes\CrmClienteController::class, 'actualizar', $requireCrm));

    // --- MODULO CRM PEDIDOS DE SERVICIO ---
    $router->get('/mi-empresa/crm/pedidos-servicio', $action(\App\Modules\CrmPedidosServicio\PedidoServicioController::class, 'index', $requireCrm));
    $router->get('/mi-empresa/crm/pedidos-servicio/sugerencias', $action(\App\Modules\CrmPedidosServicio\PedidoServicioController::class, 'suggestions', $requireCrm));
    $router->get('/mi-empresa/crm/pedidos-servicio/crear', $action(\App\Modules\CrmPedidosServicio\PedidoServicioController::class, 'create', $requireCrm));
    $router->post('/mi-empresa/crm/pedidos-servicio', $action(\App\Modules\CrmPedidosServicio\PedidoServicioController::class, 'store', $requireCrm));
    $router->get('/mi-empresa/crm/pedidos-servicio/clientes/sugerencias', $action(\App\Modules\CrmPedidosServicio\PedidoServicioController::class, 'clientSuggestions', $requireCrm));
    $router->get('/mi-empresa/crm/pedidos-servicio/articulos/sugerencias', $action(\App\Modules\CrmPedidosServicio\PedidoServicioController::class, 'articleSuggestions', $requireCrm));
    $router->get('/mi-empresa/crm/pedidos-servicio/clasificaciones/sugerencias', $action(\App\Modules\CrmPedidosServicio\PedidoServicioController::class, 'classificationSuggestions', $requireCrm));
    $router->get('/mi-empresa/crm/pedidos-servicio/{id}/editar', $action(\App\Modules\CrmPedidosServicio\PedidoServicioController::class, 'edit', $requireCrm));
    $router->post('/mi-empresa/crm/pedidos-servicio/{id}', $action(\App\Modules\CrmPedidosServicio\PedidoServicioController::class, 'update', $requireCrm));

    // --- MODULO CRM NOTAS ---
    $router->get('/mi-empresa/crm/notas', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'index', $requireCrm));
    $router->get('/mi-empresa/crm/notas/sugerencias-tags', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'tagsSuggestions', $requireCrm));
    $router->get('/mi-empresa/crm/notas/sugerencias-clientes', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'clientSuggestions', $requireCrm));
    $router->get('/mi-empresa/crm/notas/importar', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'showImportForm', $requireCrm));
    $router->post('/mi-empresa/crm/notas/importar', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'processImport', $requireCrm));
    $router->get('/mi-empresa/crm/notas/descargar-plantilla', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'downloadTemplate', $requireCrm));
    $router->get('/mi-empresa/crm/notas/crear', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'create', $requireCrm));
    $router->post('/mi-empresa/crm/notas', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'store', $requireCrm));
    $router->get('/mi-empresa/crm/notas/ver/{id}', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'show', $requireCrm));
    $router->get('/mi-empresa/crm/notas/editar/{id}', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'edit', $requireCrm));
    $router->post('/mi-empresa/crm/notas/{id}', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'update', $requireCrm));
    $router->post('/mi-empresa/crm/notas/{id}/copiar', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'copy', $requireCrm));
    $router->post('/mi-empresa/crm/notas/{id}/eliminar', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'destroy', $requireCrm));
    // --- MODULO CRM PRESUPUESTOS ---
    $router->get('/mi-empresa/crm/presupuestos', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'index', $requireCrm));
    $router->get('/mi-empresa/crm/presupuestos/sugerencias', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'suggestions', $requireCrm));
    $router->get('/mi-empresa/crm/presupuestos/crear', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'create', $requireCrm));
    $router->post('/mi-empresa/crm/presupuestos', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'store', $requireCrm));
    $router->post('/mi-empresa/crm/presupuestos/catalogos/sincronizar', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'syncCatalogs', $requireCrm));
    $router->get('/mi-empresa/crm/presupuestos/clientes/sugerencias', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'clientSuggestions', $requireCrm));
    $router->get('/mi-empresa/crm/presupuestos/clientes/contexto', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'clientContext', $requireCrm));
    $router->get('/mi-empresa/crm/presupuestos/articulos/sugerencias', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'articleSuggestions', $requireCrm));
    $router->get('/mi-empresa/crm/presupuestos/articulos/contexto', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'articleContext', $requireCrm));
    $router->get('/mi-empresa/crm/presupuestos/{id}/editar', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'edit', $requireCrm));
    $router->post('/mi-empresa/crm/presupuestos/{id}', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'update', $requireCrm));
    $router->post('/mi-empresa/crm/presupuestos/{id}/copiar', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'copy', $requireCrm));
    $router->get('/mi-empresa/crm/presupuestos/{id}/imprimir', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'printPreview', $requireCrm));
    $router->post('/mi-empresa/crm/presupuestos/{id}/enviar-email', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'sendEmail', $requireCrm));

    // --- MODULO FORMULARIOS DE IMPRESIÓN ---
    $router->get('/mi-empresa/crm/formularios-impresion', $action(\App\Modules\PrintForms\PrintFormController::class, 'index', $requireCrm));
    $router->get('/mi-empresa/crm/formularios-impresion/{documentKey}', $action(\App\Modules\PrintForms\PrintFormController::class, 'edit', $requireCrm));
    $router->post('/mi-empresa/crm/formularios-impresion/{documentKey}', $action(\App\Modules\PrintForms\PrintFormController::class, 'update', $requireCrm));

    // --- MODULO CATEGORIAS ---
    $router->get('/mi-empresa/categorias', $action(\App\Modules\Categorias\CategoriaController::class, 'index', $requireTiendas));
    $router->get('/mi-empresa/categorias/sugerencias', $action(\App\Modules\Categorias\CategoriaController::class, 'suggestions', $requireTiendas));
    $router->get('/mi-empresa/categorias/crear', $action(\App\Modules\Categorias\CategoriaController::class, 'create', $requireTiendas));
    $router->post('/mi-empresa/categorias', $action(\App\Modules\Categorias\CategoriaController::class, 'store', $requireTiendas));
    $router->get('/mi-empresa/categorias/{id}/editar', $action(\App\Modules\Categorias\CategoriaController::class, 'edit', $requireTiendas));
    $router->post('/mi-empresa/categorias/{id}', $action(\App\Modules\Categorias\CategoriaController::class, 'update', $requireTiendas));

    // --- MODULO CLIENTES WEB ---
    $router->get('/mi-empresa/clientes', $action(\App\Modules\ClientesWeb\Controllers\ClienteWebController::class, 'index', $requireTiendas));
    $router->get('/mi-empresa/clientes/sugerencias', $action(\App\Modules\ClientesWeb\Controllers\ClienteWebController::class, 'suggestions', $requireTiendas));
    $router->get('/mi-empresa/clientes/buscar-tango', $action(\App\Modules\ClientesWeb\Controllers\ClienteWebController::class, 'buscarTango', $requireTiendas));
    $router->get('/mi-empresa/clientes/metadata-tango', $action(\App\Modules\ClientesWeb\Controllers\ClienteWebController::class, 'obtenerMetadataTango', $requireTiendas));
    $router->get('/mi-empresa/clientes/{id}/editar', $action(\App\Modules\ClientesWeb\Controllers\ClienteWebController::class, 'edit', $requireTiendas));
    $router->post('/mi-empresa/clientes/{id}/editar', $action(\App\Modules\ClientesWeb\Controllers\ClienteWebController::class, 'update', $requireTiendas));
    $router->post('/mi-empresa/clientes/{id}/validar-tango', $action(\App\Modules\ClientesWeb\Controllers\ClienteWebController::class, 'validarTango', $requireTiendas));
    $router->post('/mi-empresa/clientes/{id}/enviar-pendientes', $action(\App\Modules\ClientesWeb\Controllers\ClienteWebController::class, 'enviarPendientes', $requireTiendas));

    // --- MODULO PEDIDOS WEB ---
    $router->get('/mi-empresa/pedidos', $action(\App\Modules\Pedidos\Controllers\PedidoWebController::class, 'index', $requireTiendas));
    $router->get('/mi-empresa/pedidos/sugerencias', $action(\App\Modules\Pedidos\Controllers\PedidoWebController::class, 'suggestions', $requireTiendas));
    $router->get('/mi-empresa/pedidos/{id}', $action(\App\Modules\Pedidos\Controllers\PedidoWebController::class, 'show', $requireTiendas));
    $router->post('/mi-empresa/pedidos/{id}/reprocesar', $action(\App\Modules\Pedidos\Controllers\PedidoWebController::class, 'reprocesar', $requireTiendas));
    $router->post('/mi-empresa/pedidos/reprocesar-seleccionados', $action(\App\Modules\Pedidos\Controllers\PedidoWebController::class, 'reprocesarSeleccionados', $requireTiendas));
    $router->post('/mi-empresa/pedidos/reprocesar-pendientes', $action(\App\Modules\Pedidos\Controllers\PedidoWebController::class, 'reprocesarPendientes', $requireTiendas));

    // TEMPORAL - test render de vista. Eliminar cuando haya vista real.
    $router->get('/test-vista', function () {
        View::render('app/modules/dashboard/views/index.php', [
            'mensaje' => 'Variable pasada correctamente desde routes.php',
        ]);
    });

    // TEMPORAL - test conexion DB con SELECT 1. Eliminar cuando haya modelo real.
    $router->get('/test-db', function () {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("SELECT payload_enviado, respuesta_tango FROM pedidos_web WHERE estado_tango = 'error_envio_tango' ORDER BY id DESC LIMIT 1");
            $row = $stmt->fetch();
            header('Content-Type: application/json');
            echo json_encode($row);
        } catch (\RuntimeException $e) {
            http_response_code(500);
            echo 'Conexion DB: ERROR - ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    });

    // --- RUTAS FRONT PUBLICO (AL FINAL DEL ARCHIVO PARA EVITAR COLISIONES) ---
    // NOTA: {slug} capturara dinamicamente cualquier string que no haya matcheado con rutas estaticas superiores
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
