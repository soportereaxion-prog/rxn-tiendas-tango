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

    $requireTiendasNotas = static function (): void {
        \App\Modules\Empresas\EmpresaAccessService::requireTiendasNotasAccess();
    };

    $requireCrmNotas = static function (): void {
        \App\Modules\Empresas\EmpresaAccessService::requireCrmNotasAccess();
    };

    $requireAnyOperational = static function (): void {
        \App\Modules\Empresas\EmpresaAccessService::requireAnyOperationalAccess();
    };

    $requireRxnLive = static function (): void {
        \App\Modules\Empresas\EmpresaAccessService::requireRxnLiveAccess();
    };

    $requireCrmLlamadas = static function (): void {
        \App\Modules\Empresas\EmpresaAccessService::requireCrmLlamadasAccess();
    };

    $requireCrmMonitoreo = static function (): void {
        \App\Modules\Empresas\EmpresaAccessService::requireCrmMonitoreoAccess();
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
        if (empty($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }
        View::render('app/modules/Dashboard/views/home.php');
    });

    // --- SUBDASHBOARDS (NIVEL 2) ---
    $router->get('/admin/dashboard', function () {
        \App\Modules\Auth\AuthService::requireRxnAdmin();
        View::render('app/modules/Dashboard/views/admin_dashboard.php');
    });

    $router->get('/mi-empresa/dashboard', function () {
        \App\Modules\Empresas\EmpresaAccessService::requireTiendasAccess();
        View::render('app/modules/Dashboard/views/tenant_dashboard.php');
    });

    $router->get('/mi-empresa/crm/dashboard', function () {
        \App\Modules\Empresas\EmpresaAccessService::requireCrmAccess();
        View::render('app/modules/Dashboard/views/crm_dashboard.php');
    });

    // --- MI PERFIL (B2B) ---
    $router->get('/mi-perfil', [\App\Modules\Usuarios\UsuarioPerfilController::class, 'index']);
    $router->post('/mi-perfil', [\App\Modules\Usuarios\UsuarioPerfilController::class, 'guardar']);
    $router->post('/mi-perfil/toggle-theme', [\App\Modules\Usuarios\UsuarioPerfilController::class, 'toggleTheme']);
    $router->post('/mi-perfil/dashboard-order', [\App\Modules\Usuarios\UsuarioPerfilController::class, 'guardarOrdenDashboard']);
    $router->post('/mi-perfil/smtp/test', [\App\Modules\Usuarios\UsuarioPerfilController::class, 'testSmtp']);
    $router->post('/mi-perfil/horario', [\App\Modules\Usuarios\UsuarioPerfilController::class, 'guardarHorario']);

    // --- MODULO EMPRESAS ---
    $router->get('/empresas', [\App\Modules\Empresas\EmpresaController::class, 'index']);
    $router->get('/empresas/sugerencias', [\App\Modules\Empresas\EmpresaController::class, 'suggestions']);
    $router->post('/empresas/eliminar-masivo', [\App\Modules\Empresas\EmpresaController::class, 'eliminarMasivo']);
    $router->post('/empresas/restore-masivo', [\App\Modules\Empresas\EmpresaController::class, 'restoreMasivo']);
    $router->post('/empresas/force-delete-masivo', [\App\Modules\Empresas\EmpresaController::class, 'forceDeleteMasivo']);
    $router->get('/empresas/crear', [\App\Modules\Empresas\EmpresaController::class, 'create']);
    $router->post('/empresas', [\App\Modules\Empresas\EmpresaController::class, 'store']);
    $router->get('/empresas/{id}/editar', [\App\Modules\Empresas\EmpresaController::class, 'edit']);
    $router->post('/empresas/{id}', [\App\Modules\Empresas\EmpresaController::class, 'update']);
    $router->post('/empresas/{id}/copiar', [\App\Modules\Empresas\EmpresaController::class, 'copy']);
    $router->post('/empresas/{id}/eliminar', [\App\Modules\Empresas\EmpresaController::class, 'eliminar']);
    $router->post('/empresas/{id}/restore', [\App\Modules\Empresas\EmpresaController::class, 'restore']);
    $router->post('/empresas/{id}/force-delete', [\App\Modules\Empresas\EmpresaController::class, 'forceDelete']);
    $router->post('/empresas/{id}/ingresar', [\App\Modules\Empresas\EmpresaController::class, 'ingresar']);

    // --- MODULO ADMIN GLOBAL ---
    $router->get('/admin/mantenimiento', [\App\Modules\Admin\Controllers\MantenimientoController::class, 'index']);
    $router->post('/admin/mantenimiento/migrar', [\App\Modules\Admin\Controllers\MantenimientoController::class, 'runMigrations']);
    $router->post('/admin/mantenimiento/baseline', [\App\Modules\Admin\Controllers\MantenimientoController::class, 'baseline']);
    $router->post('/admin/mantenimiento/upload-update', [\App\Modules\Admin\Controllers\MantenimientoController::class, 'uploadUpdate']);
    $router->post('/admin/mantenimiento/build-release', [\App\Modules\Admin\Controllers\MantenimientoController::class, 'buildRelease']);
    $router->get('/admin/mantenimiento/download-release', [\App\Modules\Admin\Controllers\MantenimientoController::class, 'downloadRelease']);
    $router->post('/admin/mantenimiento/backup-db', [\App\Modules\Admin\Controllers\MantenimientoController::class, 'runDbBackup']);
    $router->post('/admin/mantenimiento/backup-files', [\App\Modules\Admin\Controllers\MantenimientoController::class, 'runFilesBackup']);

    // --- ADMIN: DevDbSwitcher (dev only — inerte en prod por ausencia de config/dev_databases.local.php) ---
    $router->post('/admin/dev-db-switch', [\App\Modules\Admin\Controllers\DevDbSwitchController::class, 'switch']);
    $router->get('/admin/smtp-global', [\App\Modules\Admin\Controllers\GlobalConfigController::class, 'showSmtpGlobal']);
    $router->post('/admin/smtp-global', [\App\Modules\Admin\Controllers\GlobalConfigController::class, 'updateSmtpGlobal']);
    $router->post('/admin/smtp-global/test', [\App\Modules\Admin\Controllers\GlobalConfigController::class, 'testConnection']);
    $router->get('/admin/notas-modulos', [\App\Modules\Admin\Controllers\ModuleNotesController::class, 'index']);
    $router->post('/admin/notas-modulos', [\App\Modules\Admin\Controllers\ModuleNotesController::class, 'store']);
    $router->get('/api/admin/bitacora/sync', [\App\Modules\Admin\Controllers\ModuleNotesController::class, 'syncExport']);

    // --- ADMIN: Gestión cross-user de vistas RXN Live (destrabar configs rotos) ---
    $router->get('/admin/rxn_live/vistas', [\App\Modules\Admin\Controllers\RxnLiveVistasController::class, 'index']);
    $router->get('/admin/rxn_live/vistas/ver', [\App\Modules\Admin\Controllers\RxnLiveVistasController::class, 'ver']);
    $router->post('/admin/rxn_live/vistas/eliminar', [\App\Modules\Admin\Controllers\RxnLiveVistasController::class, 'eliminar']);
    $router->get('/admin/rxn_live/vistas/exportar', [\App\Modules\Admin\Controllers\RxnLiveVistasController::class, 'exportar']);
    $router->post('/admin/rxn_live/vistas/importar', [\App\Modules\Admin\Controllers\RxnLiveVistasController::class, 'importar']);

    // --- MODULO AUTH ---
    $router->get('/login', [\App\Modules\Auth\AuthController::class, 'showLogin']);
    $router->post('/login', [\App\Modules\Auth\AuthController::class, 'processLogin']);
    $router->get('/logout', [\App\Modules\Auth\AuthController::class, 'logout']);

    // --- MODULO RXN GEO TRACKING (endpoints cross-tenant, solo requieren login) ---
    // Banner de consentimiento (Ley 25.326) + reporte de posición post-evento.
    $router->post('/geo-tracking/consent', [\App\Modules\RxnGeoTracking\RxnGeoTrackingConsentController::class, 'store']);
    $router->post('/geo-tracking/report', [\App\Modules\RxnGeoTracking\RxnGeoTrackingReportController::class, 'store']);

    // Dashboard admin + configuración (requireBackofficeAdmin validado dentro del controller).
    $router->get('/mi-empresa/geo-tracking', [\App\Modules\RxnGeoTracking\RxnGeoTrackingController::class, 'index']);
    $router->get('/mi-empresa/geo-tracking/map-points', [\App\Modules\RxnGeoTracking\RxnGeoTrackingController::class, 'mapPoints']);
    $router->get('/mi-empresa/geo-tracking/export', [\App\Modules\RxnGeoTracking\RxnGeoTrackingController::class, 'export']);
    $router->get('/mi-empresa/geo-tracking/config', [\App\Modules\RxnGeoTracking\RxnGeoTrackingConfigController::class, 'show']);
    $router->post('/mi-empresa/geo-tracking/config', [\App\Modules\RxnGeoTracking\RxnGeoTrackingConfigController::class, 'update']);

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
    $router->post('/mi-empresa/usuarios/fetch-tango-profile', $action(\App\Modules\Usuarios\UsuarioController::class, 'fetchTangoProfile', $requireAnyOperational));
    $router->post('/mi-empresa/usuarios/eliminar-masivo', $action(\App\Modules\Usuarios\UsuarioController::class, 'eliminarMasivo', $requireAnyOperational));
    $router->post('/mi-empresa/usuarios/restore-masivo', $action(\App\Modules\Usuarios\UsuarioController::class, 'restoreMasivo', $requireAnyOperational));
    $router->post('/mi-empresa/usuarios/force-delete-masivo', $action(\App\Modules\Usuarios\UsuarioController::class, 'forceDeleteMasivo', $requireAnyOperational));
    $router->get('/mi-empresa/usuarios/crear', $action(\App\Modules\Usuarios\UsuarioController::class, 'create', $requireAnyOperational));
    $router->post('/mi-empresa/usuarios', $action(\App\Modules\Usuarios\UsuarioController::class, 'store', $requireAnyOperational));
    $router->get('/mi-empresa/usuarios/{id}/editar', $action(\App\Modules\Usuarios\UsuarioController::class, 'edit', $requireAnyOperational));
    $router->post('/mi-empresa/usuarios/{id}', $action(\App\Modules\Usuarios\UsuarioController::class, 'update', $requireAnyOperational));
    $router->post('/mi-empresa/usuarios/{id}/copiar', $action(\App\Modules\Usuarios\UsuarioController::class, 'copy', $requireAnyOperational));
    $router->post('/mi-empresa/usuarios/{id}/eliminar', $action(\App\Modules\Usuarios\UsuarioController::class, 'eliminar', $requireAnyOperational));
    $router->post('/mi-empresa/usuarios/{id}/restore', $action(\App\Modules\Usuarios\UsuarioController::class, 'restore', $requireAnyOperational));
    $router->post('/mi-empresa/usuarios/{id}/force-delete', $action(\App\Modules\Usuarios\UsuarioController::class, 'forceDelete', $requireAnyOperational));

    $router->get('/mi-empresa/rxn-sync', $action(\App\Modules\RxnSync\RxnSyncController::class, 'index', $requireTiendas));
    $router->get('/mi-empresa/rxn-sync/clientes/list', $action(\App\Modules\RxnSync\RxnSyncController::class, 'listClientes', $requireTiendas));
    $router->get('/mi-empresa/rxn-sync/articulos/list', $action(\App\Modules\RxnSync\RxnSyncController::class, 'listArticulos', $requireTiendas));
    $router->post('/mi-empresa/rxn-sync/push', $action(\App\Modules\RxnSync\RxnSyncController::class, 'pushToTango', $requireTiendas));
    $router->post('/mi-empresa/rxn-sync/pull', $action(\App\Modules\RxnSync\RxnSyncController::class, 'pullSingle', $requireTiendas));
    $router->post('/mi-empresa/rxn-sync/push-masivo', $action(\App\Modules\RxnSync\RxnSyncController::class, 'pushMasivo', $requireTiendas));
    $router->post('/mi-empresa/rxn-sync/pull-masivo', $action(\App\Modules\RxnSync\RxnSyncController::class, 'pullMasivo', $requireTiendas));
    $router->post('/mi-empresa/rxn-sync/auditar-articulos', $action(\App\Modules\RxnSync\RxnSyncController::class, 'auditarArticulos', $requireTiendas));
    $router->post('/mi-empresa/rxn-sync/auditar-clientes', $action(\App\Modules\RxnSync\RxnSyncController::class, 'auditarClientes', $requireTiendas));
    $router->post('/mi-empresa/rxn-sync/sync-full-articulos', $action(\App\Modules\RxnSync\RxnSyncController::class, 'syncPullArticulos', $requireTiendas));
    $router->post('/mi-empresa/rxn-sync/sync-full-clientes', $action(\App\Modules\RxnSync\RxnSyncController::class, 'syncPullClientes', $requireTiendas));
    $router->get('/mi-empresa/rxn-sync/payload', $action(\App\Modules\RxnSync\RxnSyncController::class, 'getPayload', $requireTiendas));

    $router->get('/mi-empresa/crm/rxn-sync', $action(\App\Modules\RxnSync\RxnSyncController::class, 'index', $requireCrm));
    $router->get('/mi-empresa/crm/rxn-sync/clientes/list', $action(\App\Modules\RxnSync\RxnSyncController::class, 'listClientes', $requireCrm));
    $router->get('/mi-empresa/crm/rxn-sync/articulos/list', $action(\App\Modules\RxnSync\RxnSyncController::class, 'listArticulos', $requireCrm));
    $router->post('/mi-empresa/crm/rxn-sync/push', $action(\App\Modules\RxnSync\RxnSyncController::class, 'pushToTango', $requireCrm));
    $router->post('/mi-empresa/crm/rxn-sync/pull', $action(\App\Modules\RxnSync\RxnSyncController::class, 'pullSingle', $requireCrm));
    $router->post('/mi-empresa/crm/rxn-sync/push-masivo', $action(\App\Modules\RxnSync\RxnSyncController::class, 'pushMasivo', $requireCrm));
    $router->post('/mi-empresa/crm/rxn-sync/pull-masivo', $action(\App\Modules\RxnSync\RxnSyncController::class, 'pullMasivo', $requireCrm));
    $router->post('/mi-empresa/crm/rxn-sync/auditar-articulos', $action(\App\Modules\RxnSync\RxnSyncController::class, 'auditarArticulos', $requireCrm));
    $router->post('/mi-empresa/crm/rxn-sync/auditar-clientes', $action(\App\Modules\RxnSync\RxnSyncController::class, 'auditarClientes', $requireCrm));
    $router->post('/mi-empresa/crm/rxn-sync/sync-full-articulos', $action(\App\Modules\RxnSync\RxnSyncController::class, 'syncPullArticulos', $requireCrm));
    $router->post('/mi-empresa/crm/rxn-sync/sync-full-clientes', $action(\App\Modules\RxnSync\RxnSyncController::class, 'syncPullClientes', $requireCrm));
    $router->post('/mi-empresa/crm/rxn-sync/sync-catalogos', $action(\App\Modules\RxnSync\RxnSyncController::class, 'syncCatalogos', $requireCrm));
    $router->get('/mi-empresa/crm/rxn-sync/payload', $action(\App\Modules\RxnSync\RxnSyncController::class, 'getPayload', $requireCrm));
    $router->get('/mi-empresa/crm/rxn-sync/pedidos/list', $action(\App\Modules\RxnSync\RxnSyncController::class, 'listPedidos', $requireCrm));
    $router->post('/mi-empresa/crm/rxn-sync/sync-pedidos-estados', $action(\App\Modules\RxnSync\RxnSyncController::class, 'syncPedidosEstados', $requireCrm));
    $router->post('/mi-empresa/crm/rxn-sync/sync-pedido-estado', $action(\App\Modules\RxnSync\RxnSyncController::class, 'syncPedidoEstado', $requireCrm));

    // --- Endpoints atómicos Tango Metadata (CORS fix: 1 request por catálogo) ---
    $router->post('/mi-empresa/configuracion/tango-empresas', $action(\App\Modules\EmpresaConfig\EmpresaConfigController::class, 'getTangoEmpresas', $requireTiendas));
    $router->post('/mi-empresa/configuracion/tango-listas', $action(\App\Modules\EmpresaConfig\EmpresaConfigController::class, 'getTangoListas', $requireTiendas));
    $router->post('/mi-empresa/configuracion/tango-depositos', $action(\App\Modules\EmpresaConfig\EmpresaConfigController::class, 'getTangoDepositos', $requireTiendas));
    $router->post('/mi-empresa/configuracion/tango-perfiles', $action(\App\Modules\EmpresaConfig\EmpresaConfigController::class, 'getTangoPerfiles', $requireTiendas));

    $router->post('/mi-empresa/crm/configuracion/tango-empresas', $action(\App\Modules\EmpresaConfig\EmpresaConfigController::class, 'getTangoEmpresas', $requireCrm));
    $router->post('/mi-empresa/crm/configuracion/tango-listas', $action(\App\Modules\EmpresaConfig\EmpresaConfigController::class, 'getTangoListas', $requireCrm));
    $router->post('/mi-empresa/crm/configuracion/tango-depositos', $action(\App\Modules\EmpresaConfig\EmpresaConfigController::class, 'getTangoDepositos', $requireCrm));
    $router->post('/mi-empresa/crm/configuracion/tango-perfiles', $action(\App\Modules\EmpresaConfig\EmpresaConfigController::class, 'getTangoPerfiles', $requireCrm));

    // --- Clasificaciones PDS (process 326) para autollenar catalogo local ---
    $router->post('/mi-empresa/configuracion/tango-clasificaciones', $action(\App\Modules\EmpresaConfig\EmpresaConfigController::class, 'getTangoClasificaciones', $requireTiendas));
    $router->post('/mi-empresa/crm/configuracion/tango-clasificaciones', $action(\App\Modules\EmpresaConfig\EmpresaConfigController::class, 'getTangoClasificaciones', $requireCrm));

    // --- Diagnostico crudo Connect (para cuando el selector de empresa viene vacio) ---
    $router->post('/mi-empresa/configuracion/tango-diagnose', $action(\App\Modules\EmpresaConfig\EmpresaConfigController::class, 'diagnoseTangoConnect', $requireTiendas));
    $router->post('/mi-empresa/crm/configuracion/tango-diagnose', $action(\App\Modules\EmpresaConfig\EmpresaConfigController::class, 'diagnoseTangoConnect', $requireCrm));

    // --- MODULO TANGO CONNECT (Legacy) ---
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
    $router->post('/mi-empresa/articulos/restore-masivo', $action(\App\Modules\Articulos\ArticuloController::class, 'restoreMasivo', $requireTiendas));
    $router->post('/mi-empresa/articulos/force-delete-masivo', $action(\App\Modules\Articulos\ArticuloController::class, 'forceDeleteMasivo', $requireTiendas));
    $router->post('/mi-empresa/articulos/{id}/eliminar', $action(\App\Modules\Articulos\ArticuloController::class, 'eliminar', $requireTiendas));
    $router->post('/mi-empresa/articulos/{id}/restore', $action(\App\Modules\Articulos\ArticuloController::class, 'restore', $requireTiendas));
    $router->post('/mi-empresa/articulos/{id}/force-delete', $action(\App\Modules\Articulos\ArticuloController::class, 'forceDelete', $requireTiendas));
    $router->get('/mi-empresa/articulos/editar', $action(\App\Modules\Articulos\ArticuloController::class, 'editar', $requireTiendas));
    $router->post('/mi-empresa/articulos/editar', $action(\App\Modules\Articulos\ArticuloController::class, 'actualizar', $requireTiendas));
    $router->post('/mi-empresa/articulos/{id}/push-tango', $action(\App\Modules\Articulos\ArticuloController::class, 'pushToTango', $requireTiendas));


    $router->get('/mi-empresa/crm/articulos', $action(\App\Modules\Articulos\ArticuloController::class, 'index', $requireCrm));
    $router->get('/mi-empresa/crm/articulos/sugerencias', $action(\App\Modules\Articulos\ArticuloController::class, 'suggestions', $requireCrm));
    $router->post('/mi-empresa/crm/articulos/purgar', $action(\App\Modules\Articulos\ArticuloController::class, 'purgar', $requireCrm));
    $router->post('/mi-empresa/crm/articulos/eliminar-masivo', $action(\App\Modules\Articulos\ArticuloController::class, 'eliminarMasivo', $requireCrm));
    $router->post('/mi-empresa/crm/articulos/restore-masivo', $action(\App\Modules\Articulos\ArticuloController::class, 'restoreMasivo', $requireCrm));
    $router->post('/mi-empresa/crm/articulos/force-delete-masivo', $action(\App\Modules\Articulos\ArticuloController::class, 'forceDeleteMasivo', $requireCrm));
    $router->post('/mi-empresa/crm/articulos/{id}/eliminar', $action(\App\Modules\Articulos\ArticuloController::class, 'eliminar', $requireCrm));
    $router->post('/mi-empresa/crm/articulos/{id}/restore', $action(\App\Modules\Articulos\ArticuloController::class, 'restore', $requireCrm));
    $router->post('/mi-empresa/crm/articulos/{id}/force-delete', $action(\App\Modules\Articulos\ArticuloController::class, 'forceDelete', $requireCrm));
    $router->post('/mi-empresa/crm/articulos/{id}/push-tango', $action(\App\Modules\Articulos\ArticuloController::class, 'pushToTango', $requireCrm));
    $router->get('/mi-empresa/crm/articulos/editar', $action(\App\Modules\Articulos\ArticuloController::class, 'editar', $requireCrm));
    $router->post('/mi-empresa/crm/articulos/editar', $action(\App\Modules\Articulos\ArticuloController::class, 'actualizar', $requireCrm));

    $router->get('/mi-empresa/crm/clientes', $action(\App\Modules\CrmClientes\CrmClienteController::class, 'index', $requireCrm));
    $router->get('/mi-empresa/crm/clientes/sugerencias', $action(\App\Modules\CrmClientes\CrmClienteController::class, 'suggestions', $requireCrm));
    $router->post('/mi-empresa/crm/clientes/purgar', $action(\App\Modules\CrmClientes\CrmClienteController::class, 'purgar', $requireCrm));
    $router->post('/mi-empresa/crm/clientes/eliminar-masivo', $action(\App\Modules\CrmClientes\CrmClienteController::class, 'eliminarMasivo', $requireCrm));
    $router->post('/mi-empresa/crm/clientes/restore-masivo', $action(\App\Modules\CrmClientes\CrmClienteController::class, 'restoreMasivo', $requireCrm));
    $router->post('/mi-empresa/crm/clientes/force-delete-masivo', $action(\App\Modules\CrmClientes\CrmClienteController::class, 'forceDeleteMasivo', $requireCrm));
    $router->post('/mi-empresa/crm/clientes/{id}/eliminar', $action(\App\Modules\CrmClientes\CrmClienteController::class, 'eliminar', $requireCrm));
    $router->post('/mi-empresa/crm/clientes/{id}/restore', $action(\App\Modules\CrmClientes\CrmClienteController::class, 'restore', $requireCrm));
    $router->post('/mi-empresa/crm/clientes/{id}/force-delete', $action(\App\Modules\CrmClientes\CrmClienteController::class, 'forceDelete', $requireCrm));
    $router->post('/mi-empresa/crm/clientes/{id}/copiar', $action(\App\Modules\CrmClientes\CrmClienteController::class, 'copy', $requireCrm));
    $router->post('/mi-empresa/crm/clientes/{id}/push-tango', $action(\App\Modules\CrmClientes\CrmClienteController::class, 'pushToTango', $requireCrm));
    $router->get('/mi-empresa/crm/clientes/editar', $action(\App\Modules\CrmClientes\CrmClienteController::class, 'editar', $requireCrm));
    $router->post('/mi-empresa/crm/clientes/editar', $action(\App\Modules\CrmClientes\CrmClienteController::class, 'actualizar', $requireCrm));

    // --- MODULO CRM PEDIDOS DE SERVICIO ---
    $router->get('/mi-empresa/crm/pedidos-servicio', $action(\App\Modules\CrmPedidosServicio\PedidoServicioController::class, 'index', $requireCrm));
    $router->post('/mi-empresa/crm/pedidos-servicio/eliminar-masivo', $action(\App\Modules\CrmPedidosServicio\PedidoServicioController::class, 'eliminarMasivo', $requireCrm));
    $router->post('/mi-empresa/crm/pedidos-servicio/restore-masivo', $action(\App\Modules\CrmPedidosServicio\PedidoServicioController::class, 'restoreMasivo', $requireCrm));
    $router->post('/mi-empresa/crm/pedidos-servicio/force-delete-masivo', $action(\App\Modules\CrmPedidosServicio\PedidoServicioController::class, 'forceDeleteMasivo', $requireCrm));
    $router->post('/mi-empresa/crm/pedidos-servicio/{id}/eliminar', $action(\App\Modules\CrmPedidosServicio\PedidoServicioController::class, 'eliminar', $requireCrm));
    $router->post('/mi-empresa/crm/pedidos-servicio/{id}/restore', $action(\App\Modules\CrmPedidosServicio\PedidoServicioController::class, 'restore', $requireCrm));
    $router->post('/mi-empresa/crm/pedidos-servicio/{id}/force-delete', $action(\App\Modules\CrmPedidosServicio\PedidoServicioController::class, 'forceDelete', $requireCrm));
    $router->get('/mi-empresa/crm/pedidos-servicio/sugerencias', $action(\App\Modules\CrmPedidosServicio\PedidoServicioController::class, 'suggestions', $requireCrm));
    $router->get('/mi-empresa/crm/pedidos-servicio/crear', $action(\App\Modules\CrmPedidosServicio\PedidoServicioController::class, 'create', $requireCrm));
    $router->post('/mi-empresa/crm/pedidos-servicio', $action(\App\Modules\CrmPedidosServicio\PedidoServicioController::class, 'store', $requireCrm));
    $router->get('/mi-empresa/crm/pedidos-servicio/clientes/sugerencias', $action(\App\Modules\CrmPedidosServicio\PedidoServicioController::class, 'clientSuggestions', $requireCrm));
    $router->get('/mi-empresa/crm/pedidos-servicio/articulos/sugerencias', $action(\App\Modules\CrmPedidosServicio\PedidoServicioController::class, 'articleSuggestions', $requireCrm));
    $router->get('/mi-empresa/crm/pedidos-servicio/clasificaciones/sugerencias', $action(\App\Modules\CrmPedidosServicio\PedidoServicioController::class, 'classificationSuggestions', $requireCrm));
    $router->get('/mi-empresa/crm/pedidos-servicio/{id}/editar', $action(\App\Modules\CrmPedidosServicio\PedidoServicioController::class, 'edit', $requireCrm));
    $router->post('/mi-empresa/crm/pedidos-servicio/{id}', $action(\App\Modules\CrmPedidosServicio\PedidoServicioController::class, 'update', $requireCrm));
    $router->post('/mi-empresa/crm/pedidos-servicio/{id}/copiar', $action(\App\Modules\CrmPedidosServicio\PedidoServicioController::class, 'copy', $requireCrm));
    $router->get('/mi-empresa/crm/pedidos-servicio/{id}/imprimir', $action(\App\Modules\CrmPedidosServicio\PedidoServicioController::class, 'printPreview', $requireCrm));
    $router->post('/mi-empresa/crm/pedidos-servicio/{id}/enviar-correo', $action(\App\Modules\CrmPedidosServicio\PedidoServicioController::class, 'sendEmail', $requireCrm));

    // --- MODULO CRM NOTAS ---
    $router->get('/mi-empresa/crm/notas', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'index', $requireCrmNotas));
    // Split view: partials AJAX (panel derecho + lista izquierda). Deben ir antes de /{id}.
    $router->get('/mi-empresa/crm/notas/lista', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'listPartial', $requireCrmNotas));
    $router->get('/mi-empresa/crm/notas/panel/{id}', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'panel', $requireCrmNotas));
    $router->post('/mi-empresa/crm/notas/eliminar-masivo', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'eliminarMasivo', $requireCrmNotas));
    $router->post('/mi-empresa/crm/notas/restore-masivo', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'restoreMasivo', $requireCrmNotas));
    $router->post('/mi-empresa/crm/notas/force-delete-masivo', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'forceDeleteMasivo', $requireCrmNotas));
    $router->get('/mi-empresa/crm/notas/exportar', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'export', $requireCrmNotas));
    $router->get('/mi-empresa/crm/notas/sugerencias-tags', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'tagsSuggestions', $requireCrmNotas));
    $router->get('/mi-empresa/crm/notas/sugerencias-clientes', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'clientSuggestions', $requireCrmNotas));
    $router->get('/mi-empresa/crm/notas/sugerencias-tratativas', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'tratativaSuggestions', $requireCrmNotas));
    $router->get('/mi-empresa/crm/notas/importar', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'showImportForm', $requireCrmNotas));
    $router->post('/mi-empresa/crm/notas/importar', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'processImport', $requireCrmNotas));
    $router->get('/mi-empresa/crm/notas/descargar-plantilla', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'downloadTemplate', $requireCrmNotas));
    $router->get('/mi-empresa/crm/notas/crear', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'create', $requireCrmNotas));
    $router->post('/mi-empresa/crm/notas/crear', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'store', $requireCrmNotas));
    $router->get('/mi-empresa/crm/notas/{id}', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'show', $requireCrmNotas));
    $router->get('/mi-empresa/crm/notas/{id}/editar', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'edit', $requireCrmNotas));
    $router->post('/mi-empresa/crm/notas/{id}/editar', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'update', $requireCrmNotas));
    $router->post('/mi-empresa/crm/notas/{id}/copiar', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'copy', $requireCrmNotas));
    $router->post('/mi-empresa/crm/notas/{id}/eliminar', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'eliminar', $requireCrmNotas));
    $router->post('/mi-empresa/crm/notas/{id}/restore', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'restore', $requireCrmNotas));
    $router->post('/mi-empresa/crm/notas/{id}/force-delete', $action(\App\Modules\CrmNotas\CrmNotasController::class, 'forceDelete', $requireCrmNotas));
    // --- MODULO CRM PRESUPUESTOS ---
    $router->get('/mi-empresa/crm/presupuestos', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'index', $requireCrm));
    $router->post('/mi-empresa/crm/presupuestos/eliminar-masivo', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'eliminarMasivo', $requireCrm));
    $router->post('/mi-empresa/crm/presupuestos/restore-masivo', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'restoreMasivo', $requireCrm));
    $router->post('/mi-empresa/crm/presupuestos/force-delete-masivo', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'forceDeleteMasivo', $requireCrm));
    $router->post('/mi-empresa/crm/presupuestos/{id}/eliminar', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'eliminar', $requireCrm));
    $router->post('/mi-empresa/crm/presupuestos/{id}/restore', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'restore', $requireCrm));
    $router->post('/mi-empresa/crm/presupuestos/{id}/force-delete', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'forceDelete', $requireCrm));
    $router->get('/mi-empresa/crm/presupuestos/sugerencias', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'suggestions', $requireCrm));
    $router->get('/mi-empresa/crm/presupuestos/crear', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'create', $requireCrm));
    $router->post('/mi-empresa/crm/presupuestos', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'store', $requireCrm));
    $router->get('/mi-empresa/crm/presupuestos/clientes/sugerencias', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'clientSuggestions', $requireCrm));
    $router->get('/mi-empresa/crm/presupuestos/clientes/contexto', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'clientContext', $requireCrm));
    $router->get('/mi-empresa/crm/presupuestos/articulos/sugerencias', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'articleSuggestions', $requireCrm));
    $router->get('/mi-empresa/crm/presupuestos/articulos/contexto', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'articleContext', $requireCrm));
    $router->get('/mi-empresa/crm/presupuestos/{id}/editar', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'edit', $requireCrm));
    $router->post('/mi-empresa/crm/presupuestos/{id}', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'update', $requireCrm));
    $router->post('/mi-empresa/crm/presupuestos/{id}/copiar', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'copy', $requireCrm));
    $router->post('/mi-empresa/crm/presupuestos/{id}/nueva-version', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'nuevaVersion', $requireCrm));
    $router->post('/mi-empresa/crm/presupuestos/{id}/sync-tango', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'syncTango', $requireCrm));
    $router->get('/mi-empresa/crm/presupuestos/{id}/imprimir', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'printPreview', $requireCrm));
    $router->post('/mi-empresa/crm/presupuestos/{id}/enviar-correo', $action(\App\Modules\CrmPresupuestos\PresupuestoController::class, 'sendEmail', $requireCrm));

    // --- MODULO CRM AGENDA (calendario unificado + sync push a Google Calendar) ---
    $router->get('/mi-empresa/crm/agenda', $action(\App\Modules\CrmAgenda\AgendaController::class, 'index', $requireCrm));
    $router->get('/mi-empresa/crm/agenda/events', $action(\App\Modules\CrmAgenda\AgendaController::class, 'eventsFeed', $requireCrm));
    $router->get('/mi-empresa/crm/agenda/crear', $action(\App\Modules\CrmAgenda\AgendaController::class, 'create', $requireCrm));
    $router->post('/mi-empresa/crm/agenda', $action(\App\Modules\CrmAgenda\AgendaController::class, 'store', $requireCrm));
    $router->post('/mi-empresa/crm/agenda/rescan', $action(\App\Modules\CrmAgenda\AgendaController::class, 'rescan', $requireCrm));
    $router->post('/mi-empresa/crm/agenda/google/config', $action(\App\Modules\CrmAgenda\AgendaController::class, 'googleConfig', $requireCrm));
    $router->get('/mi-empresa/crm/agenda/google/connect', $action(\App\Modules\CrmAgenda\AgendaController::class, 'googleConnect', $requireCrm));
    $router->get('/mi-empresa/crm/agenda/google/callback', $action(\App\Modules\CrmAgenda\AgendaController::class, 'googleCallback', $requireCrm));
    $router->post('/mi-empresa/crm/agenda/google/disconnect', $action(\App\Modules\CrmAgenda\AgendaController::class, 'googleDisconnect', $requireCrm));
    $router->get('/mi-empresa/crm/agenda/{id}/editar', $action(\App\Modules\CrmAgenda\AgendaController::class, 'edit', $requireCrm));
    $router->post('/mi-empresa/crm/agenda/{id}/eliminar', $action(\App\Modules\CrmAgenda\AgendaController::class, 'eliminar', $requireCrm));
    $router->post('/mi-empresa/crm/agenda/{id}', $action(\App\Modules\CrmAgenda\AgendaController::class, 'update', $requireCrm));

    // --- MODULO CRM TRATATIVAS (agregador comercial: agrupa PDS + Presupuestos) ---
    $router->get('/mi-empresa/crm/tratativas', $action(\App\Modules\CrmTratativas\TratativaController::class, 'index', $requireCrm));
    $router->post('/mi-empresa/crm/tratativas/eliminar-masivo', $action(\App\Modules\CrmTratativas\TratativaController::class, 'eliminarMasivo', $requireCrm));
    $router->post('/mi-empresa/crm/tratativas/restore-masivo', $action(\App\Modules\CrmTratativas\TratativaController::class, 'restoreMasivo', $requireCrm));
    $router->post('/mi-empresa/crm/tratativas/force-delete-masivo', $action(\App\Modules\CrmTratativas\TratativaController::class, 'forceDeleteMasivo', $requireCrm));
    $router->get('/mi-empresa/crm/tratativas/sugerencias', $action(\App\Modules\CrmTratativas\TratativaController::class, 'suggestions', $requireCrm));
    $router->get('/mi-empresa/crm/tratativas/clientes/sugerencias', $action(\App\Modules\CrmTratativas\TratativaController::class, 'clientSuggestions', $requireCrm));
    $router->get('/mi-empresa/crm/tratativas/crear', $action(\App\Modules\CrmTratativas\TratativaController::class, 'create', $requireCrm));
    $router->post('/mi-empresa/crm/tratativas', $action(\App\Modules\CrmTratativas\TratativaController::class, 'store', $requireCrm));
    $router->get('/mi-empresa/crm/tratativas/{id}/editar', $action(\App\Modules\CrmTratativas\TratativaController::class, 'edit', $requireCrm));
    $router->post('/mi-empresa/crm/tratativas/{id}/eliminar', $action(\App\Modules\CrmTratativas\TratativaController::class, 'eliminar', $requireCrm));
    $router->post('/mi-empresa/crm/tratativas/{id}/restore', $action(\App\Modules\CrmTratativas\TratativaController::class, 'restore', $requireCrm));
    $router->post('/mi-empresa/crm/tratativas/{id}/force-delete', $action(\App\Modules\CrmTratativas\TratativaController::class, 'forceDelete', $requireCrm));
    $router->post('/mi-empresa/crm/tratativas/{id}', $action(\App\Modules\CrmTratativas\TratativaController::class, 'update', $requireCrm));
    $router->get('/mi-empresa/crm/tratativas/{id}', $action(\App\Modules\CrmTratativas\TratativaController::class, 'show', $requireCrm));

    // --- MODULO CRM PRE-IMPRESION ---
    $router->get('/mi-empresa/crm/formularios-impresion', $action(\App\Modules\PrintForms\PrintFormController::class, 'index', $requireCrm));
    $router->get('/mi-empresa/crm/formularios-impresion/{documentKey}', $action(\App\Modules\PrintForms\PrintFormController::class, 'edit', $requireCrm));
    $router->post('/mi-empresa/crm/formularios-impresion/{documentKey}', $action(\App\Modules\PrintForms\PrintFormController::class, 'update', $requireCrm));

    // --- MODULO CRM LLAMADAS ---
    $router->get('/mi-empresa/crm/llamadas', $action(\App\Modules\CrmLlamadas\CrmLlamadasController::class, 'index', $requireCrmLlamadas));
    $router->get('/mi-empresa/crm/llamadas/sugerencias', $action(\App\Modules\CrmLlamadas\CrmLlamadasController::class, 'suggestions', $requireCrmLlamadas));
    $router->post('/mi-empresa/crm/llamadas/eliminar-masivo', $action(\App\Modules\CrmLlamadas\CrmLlamadasController::class, 'eliminarMasivo', $requireCrmLlamadas));
    $router->post('/mi-empresa/crm/llamadas/restore-masivo', $action(\App\Modules\CrmLlamadas\CrmLlamadasController::class, 'restoreMasivo', $requireCrmLlamadas));
    $router->post('/mi-empresa/crm/llamadas/force-delete-masivo', $action(\App\Modules\CrmLlamadas\CrmLlamadasController::class, 'forceDeleteMasivo', $requireCrmLlamadas));
    $router->post('/mi-empresa/crm/llamadas/vincular-cliente-api', $action(\App\Modules\CrmLlamadas\CrmLlamadasController::class, 'vincularClienteApi', $requireCrmLlamadas));
    $router->post('/mi-empresa/crm/llamadas/{id}/eliminar', $action(\App\Modules\CrmLlamadas\CrmLlamadasController::class, 'eliminar', $requireCrmLlamadas));
    $router->post('/mi-empresa/crm/llamadas/{id}/restore', $action(\App\Modules\CrmLlamadas\CrmLlamadasController::class, 'restore', $requireCrmLlamadas));
    $router->post('/mi-empresa/crm/llamadas/{id}/force-delete', $action(\App\Modules\CrmLlamadas\CrmLlamadasController::class, 'forceDelete', $requireCrmLlamadas));
    $router->post('/mi-empresa/crm/llamadas/{id}/desvincular', $action(\App\Modules\CrmLlamadas\CrmLlamadasController::class, 'desvincular', $requireCrmLlamadas));

    // --- MODULO CRM MONITOREO USUARIOS ---
    $router->get('/mi-empresa/crm/monitoreo-usuarios', $action(\App\Modules\CrmMonitoreoUsuarios\CrmMonitoreoUsuariosController::class, 'index', $requireCrmMonitoreo));

    // --- MODULO CRM MAIL MASIVOS ---
    $router->get('/mi-empresa/crm/mail-masivos', $action(\App\Modules\CrmMailMasivos\MailMasivosDashboardController::class, 'index', $requireCrm));
    $router->get('/mi-empresa/crm/mail-masivos/reportes', $action(\App\Modules\CrmMailMasivos\ReportController::class, 'index', $requireCrm));
    $router->get('/mi-empresa/crm/mail-masivos/reportes/crear', $action(\App\Modules\CrmMailMasivos\ReportController::class, 'create', $requireCrm));
    $router->post('/mi-empresa/crm/mail-masivos/reportes', $action(\App\Modules\CrmMailMasivos\ReportController::class, 'store', $requireCrm));
    $router->get('/mi-empresa/crm/mail-masivos/reportes/metamodel', $action(\App\Modules\CrmMailMasivos\ReportController::class, 'metamodel', $requireCrm));
    $router->post('/mi-empresa/crm/mail-masivos/reportes/preview', $action(\App\Modules\CrmMailMasivos\ReportController::class, 'preview', $requireCrm));
    $router->get('/mi-empresa/crm/mail-masivos/reportes/{id}/editar', $action(\App\Modules\CrmMailMasivos\ReportController::class, 'edit', $requireCrm));
    $router->post('/mi-empresa/crm/mail-masivos/reportes/{id}', $action(\App\Modules\CrmMailMasivos\ReportController::class, 'update', $requireCrm));
    $router->post('/mi-empresa/crm/mail-masivos/reportes/{id}/eliminar', $action(\App\Modules\CrmMailMasivos\ReportController::class, 'delete', $requireCrm));

    // Plantillas HTML (Fase 3)
    $router->get('/mi-empresa/crm/mail-masivos/plantillas', $action(\App\Modules\CrmMailMasivos\TemplateController::class, 'index', $requireCrm));
    $router->get('/mi-empresa/crm/mail-masivos/plantillas/crear', $action(\App\Modules\CrmMailMasivos\TemplateController::class, 'create', $requireCrm));
    $router->post('/mi-empresa/crm/mail-masivos/plantillas', $action(\App\Modules\CrmMailMasivos\TemplateController::class, 'store', $requireCrm));
    $router->post('/mi-empresa/crm/mail-masivos/plantillas/preview-render', $action(\App\Modules\CrmMailMasivos\TemplateController::class, 'previewRender', $requireCrm));
    $router->get('/mi-empresa/crm/mail-masivos/plantillas/available-vars/{reportId}', $action(\App\Modules\CrmMailMasivos\TemplateController::class, 'availableVars', $requireCrm));
    $router->get('/mi-empresa/crm/mail-masivos/plantillas/{id}/editar', $action(\App\Modules\CrmMailMasivos\TemplateController::class, 'edit', $requireCrm));
    $router->post('/mi-empresa/crm/mail-masivos/plantillas/{id}', $action(\App\Modules\CrmMailMasivos\TemplateController::class, 'update', $requireCrm));
    $router->post('/mi-empresa/crm/mail-masivos/plantillas/{id}/eliminar', $action(\App\Modules\CrmMailMasivos\TemplateController::class, 'delete', $requireCrm));

    // Envíos Masivos (Fase 4)
    $router->get('/mi-empresa/crm/mail-masivos/envios', $action(\App\Modules\CrmMailMasivos\JobController::class, 'index', $requireCrm));
    $router->get('/mi-empresa/crm/mail-masivos/envios/crear', $action(\App\Modules\CrmMailMasivos\JobController::class, 'create', $requireCrm));
    $router->post('/mi-empresa/crm/mail-masivos/envios', $action(\App\Modules\CrmMailMasivos\JobController::class, 'store', $requireCrm));
    $router->post('/mi-empresa/crm/mail-masivos/envios/preview-recipients', $action(\App\Modules\CrmMailMasivos\JobController::class, 'previewRecipients', $requireCrm));
    // Callback de n8n (público, protegido con X-RXN-Token). NO lleva guard requireCrm.
    $router->post('/mi-empresa/crm/mail-masivos/envios/callback', $action(\App\Modules\CrmMailMasivos\JobController::class, 'callback'));
    // Procesador de batch (público, protegido con X-RXN-Token). Llamado por n8n en loop.
    $router->post('/mi-empresa/crm/mail-masivos/envios/process-batch', $action(\App\Modules\CrmMailMasivos\JobController::class, 'processBatch'));
    $router->get('/mi-empresa/crm/mail-masivos/envios/{id}', $action(\App\Modules\CrmMailMasivos\JobController::class, 'monitor', $requireCrm));
    $router->get('/mi-empresa/crm/mail-masivos/envios/{id}/status', $action(\App\Modules\CrmMailMasivos\JobController::class, 'status', $requireCrm));
    $router->post('/mi-empresa/crm/mail-masivos/envios/{id}/cancelar', $action(\App\Modules\CrmMailMasivos\JobController::class, 'cancel', $requireCrm));
    $router->post('/mi-empresa/crm/mail-masivos/envios/{id}/reactivar', $action(\App\Modules\CrmMailMasivos\JobController::class, 'reactivate', $requireCrm));

    // Tracking público (Fase 5) — sin login, identificado por tracking_token único por item.
    $router->get('/m/open/{token}', $action(\App\Modules\CrmMailMasivos\TrackingController::class, 'open'));
    $router->get('/m/click/{token}', $action(\App\Modules\CrmMailMasivos\TrackingController::class, 'click'));

    // --- MODULO CATEGORIAS ---
    $router->get('/mi-empresa/categorias', $action(\App\Modules\Categorias\CategoriaController::class, 'index', $requireTiendas));
    $router->get('/mi-empresa/categorias/sugerencias', $action(\App\Modules\Categorias\CategoriaController::class, 'suggestions', $requireTiendas));
    $router->get('/mi-empresa/categorias/crear', $action(\App\Modules\Categorias\CategoriaController::class, 'create', $requireTiendas));
    $router->post('/mi-empresa/categorias/eliminar-masivo', $action(\App\Modules\Categorias\CategoriaController::class, 'eliminarMasivo', $requireTiendas));
    $router->post('/mi-empresa/categorias/restore-masivo', $action(\App\Modules\Categorias\CategoriaController::class, 'restoreMasivo', $requireTiendas));
    $router->post('/mi-empresa/categorias/force-delete-masivo', $action(\App\Modules\Categorias\CategoriaController::class, 'forceDeleteMasivo', $requireTiendas));
    $router->post('/mi-empresa/categorias/{id}/copiar', $action(\App\Modules\Categorias\CategoriaController::class, 'copy', $requireTiendas));
    $router->post('/mi-empresa/categorias/{id}/eliminar', $action(\App\Modules\Categorias\CategoriaController::class, 'eliminar', $requireTiendas));
    $router->post('/mi-empresa/categorias/{id}/restore', $action(\App\Modules\Categorias\CategoriaController::class, 'restore', $requireTiendas));
    $router->post('/mi-empresa/categorias/{id}/force-delete', $action(\App\Modules\Categorias\CategoriaController::class, 'forceDelete', $requireTiendas));
    $router->post('/mi-empresa/categorias', $action(\App\Modules\Categorias\CategoriaController::class, 'store', $requireTiendas));
    $router->get('/mi-empresa/categorias/{id}/editar', $action(\App\Modules\Categorias\CategoriaController::class, 'edit', $requireTiendas));
    $router->post('/mi-empresa/categorias/{id}', $action(\App\Modules\Categorias\CategoriaController::class, 'update', $requireTiendas));

    // --- MODULO CLIENTES WEB ---
    $router->get('/mi-empresa/clientes', $action(\App\Modules\ClientesWeb\Controllers\ClienteWebController::class, 'index', $requireTiendas));
    $router->get('/mi-empresa/clientes/sugerencias', $action(\App\Modules\ClientesWeb\Controllers\ClienteWebController::class, 'suggestions', $requireTiendas));
    $router->post('/mi-empresa/clientes/eliminar-masivo', $action(\App\Modules\ClientesWeb\Controllers\ClienteWebController::class, 'eliminarMasivo', $requireTiendas));
    $router->post('/mi-empresa/clientes/restore-masivo', $action(\App\Modules\ClientesWeb\Controllers\ClienteWebController::class, 'restoreMasivo', $requireTiendas));
    $router->post('/mi-empresa/clientes/force-delete-masivo', $action(\App\Modules\ClientesWeb\Controllers\ClienteWebController::class, 'forceDeleteMasivo', $requireTiendas));
    $router->post('/mi-empresa/clientes/{id}/eliminar', $action(\App\Modules\ClientesWeb\Controllers\ClienteWebController::class, 'eliminar', $requireTiendas));
    $router->post('/mi-empresa/clientes/{id}/restore', $action(\App\Modules\ClientesWeb\Controllers\ClienteWebController::class, 'restore', $requireTiendas));
    $router->post('/mi-empresa/clientes/{id}/force-delete', $action(\App\Modules\ClientesWeb\Controllers\ClienteWebController::class, 'forceDelete', $requireTiendas));
    $router->get('/mi-empresa/clientes/buscar-tango', $action(\App\Modules\ClientesWeb\Controllers\ClienteWebController::class, 'buscarTango', $requireTiendas));
    $router->get('/mi-empresa/clientes/metadata-tango', $action(\App\Modules\ClientesWeb\Controllers\ClienteWebController::class, 'obtenerMetadataTango', $requireTiendas));
    $router->get('/mi-empresa/clientes/{id}/editar', $action(\App\Modules\ClientesWeb\Controllers\ClienteWebController::class, 'edit', $requireTiendas));
    $router->post('/mi-empresa/clientes/{id}/editar', $action(\App\Modules\ClientesWeb\Controllers\ClienteWebController::class, 'update', $requireTiendas));
    $router->post('/mi-empresa/clientes/{id}/validar-tango', $action(\App\Modules\ClientesWeb\Controllers\ClienteWebController::class, 'validarTango', $requireTiendas));
    $router->post('/mi-empresa/clientes/{id}/enviar-pendientes', $action(\App\Modules\ClientesWeb\Controllers\ClienteWebController::class, 'enviarPendientes', $requireTiendas));

    // --- MODULO PEDIDOS WEB ---
    $router->get('/mi-empresa/pedidos', $action(\App\Modules\Pedidos\Controllers\PedidoWebController::class, 'index', $requireTiendas));
    $router->get('/mi-empresa/pedidos/sugerencias', $action(\App\Modules\Pedidos\Controllers\PedidoWebController::class, 'suggestions', $requireTiendas));
    $router->post('/mi-empresa/pedidos/eliminar-masivo', $action(\App\Modules\Pedidos\Controllers\PedidoWebController::class, 'eliminarMasivo', $requireTiendas));
    $router->post('/mi-empresa/pedidos/restore-masivo', $action(\App\Modules\Pedidos\Controllers\PedidoWebController::class, 'restoreMasivo', $requireTiendas));
    $router->post('/mi-empresa/pedidos/force-delete-masivo', $action(\App\Modules\Pedidos\Controllers\PedidoWebController::class, 'forceDeleteMasivo', $requireTiendas));
    $router->post('/mi-empresa/pedidos/{id}/eliminar', $action(\App\Modules\Pedidos\Controllers\PedidoWebController::class, 'eliminar', $requireTiendas));
    $router->post('/mi-empresa/pedidos/{id}/restore', $action(\App\Modules\Pedidos\Controllers\PedidoWebController::class, 'restore', $requireTiendas));
    $router->post('/mi-empresa/pedidos/{id}/force-delete', $action(\App\Modules\Pedidos\Controllers\PedidoWebController::class, 'forceDelete', $requireTiendas));
    $router->get('/mi-empresa/pedidos/{id}', $action(\App\Modules\Pedidos\Controllers\PedidoWebController::class, 'show', $requireTiendas));
    $router->post('/mi-empresa/pedidos/{id}/reprocesar', $action(\App\Modules\Pedidos\Controllers\PedidoWebController::class, 'reprocesar', $requireTiendas));
    $router->post('/mi-empresa/pedidos/reprocesar-seleccionados', $action(\App\Modules\Pedidos\Controllers\PedidoWebController::class, 'reprocesarSeleccionados', $requireTiendas));
    $router->post('/mi-empresa/pedidos/reprocesar-pendientes', $action(\App\Modules\Pedidos\Controllers\PedidoWebController::class, 'reprocesarPendientes', $requireTiendas));

    // --- MODULO RXN LIVE ---
    $router->get('/rxn_live', $action(\App\Modules\RxnLive\RxnLiveController::class, 'index', $requireRxnLive));
    $router->get('/rxn_live/dataset', $action(\App\Modules\RxnLive\RxnLiveController::class, 'dataset', $requireRxnLive));
    $router->post('/rxn_live/dataset', $action(\App\Modules\RxnLive\RxnLiveController::class, 'dataset', $requireRxnLive));
    $router->post('/rxn_live/guardar-vista', $action(\App\Modules\RxnLive\RxnLiveController::class, 'guardarVista', $requireRxnLive));
    $router->post('/rxn_live/eliminar-vista', $action(\App\Modules\RxnLive\RxnLiveController::class, 'eliminarVista', $requireRxnLive));
    $router->post('/rxn_live/exportar', $action(\App\Modules\RxnLive\RxnLiveController::class, 'exportar', $requireRxnLive));

    // TEMPORAL - test render de vista. Eliminar cuando haya vista real.
    $router->get('/test-vista', function () {
        View::render('app/modules/Dashboard/views/index.php', [
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

    // --- ATTACHMENTS (adjuntos polimórficos reusables por cualquier módulo) ---
    $router->post('/attachments/upload', [\App\Shared\Controllers\AttachmentsController::class, 'upload']);
    $router->post('/attachments/{id}/delete', [\App\Shared\Controllers\AttachmentsController::class, 'delete']);
    $router->get('/attachments/{id}/download', [\App\Shared\Controllers\AttachmentsController::class, 'download']);
    $router->get('/attachments/{id}/preview', [\App\Shared\Controllers\AttachmentsController::class, 'preview']);

    // --- CRM TRATATIVAS ↔ HORAS (vincular/desvincular existentes) ---
    $router->get('/mi-empresa/crm/tratativas/{id}/horas-sueltas.json', $action(\App\Modules\CrmTratativas\TratativaController::class, 'listarHorasSueltas', $requireCrm));
    $router->post('/mi-empresa/crm/tratativas/{id}/vincular-hora', $action(\App\Modules\CrmTratativas\TratativaController::class, 'vincularHora', $requireCrm));
    $router->post('/mi-empresa/crm/tratativas/{id}/desvincular-hora/{horaId}', $action(\App\Modules\CrmTratativas\TratativaController::class, 'desvincularHora', $requireCrm));

    // --- CRM HORAS (turnero) ---
    $router->get('/mi-empresa/crm/horas', $action(\App\Modules\CrmHoras\HoraController::class, 'turnero', $requireCrm));
    $router->post('/mi-empresa/crm/horas/iniciar', $action(\App\Modules\CrmHoras\HoraController::class, 'iniciar', $requireCrm));
    $router->post('/mi-empresa/crm/horas/cerrar', $action(\App\Modules\CrmHoras\HoraController::class, 'cerrar', $requireCrm));
    $router->get('/mi-empresa/crm/horas/diferido', $action(\App\Modules\CrmHoras\HoraController::class, 'diferido', $requireCrm));
    $router->post('/mi-empresa/crm/horas/diferido', $action(\App\Modules\CrmHoras\HoraController::class, 'diferidoStore', $requireCrm));
    $router->get('/mi-empresa/crm/horas/listado', $action(\App\Modules\CrmHoras\HoraController::class, 'listado', $requireCrm));
    $router->get('/mi-empresa/crm/horas/{id}/editar', $action(\App\Modules\CrmHoras\HoraController::class, 'editarForm', $requireCrm));
    $router->post('/mi-empresa/crm/horas/{id}/editar', $action(\App\Modules\CrmHoras\HoraController::class, 'editarStore', $requireCrm));
    $router->post('/mi-empresa/crm/horas/{id}/anular', $action(\App\Modules\CrmHoras\HoraController::class, 'anular', $requireCrm));
    $router->get('/mi-empresa/crm/horas/{id}', $action(\App\Modules\CrmHoras\HoraController::class, 'detalle', $requireCrm));
    $router->post('/mi-empresa/crm/horas/{id}/adjuntos', $action(\App\Modules\CrmHoras\HoraController::class, 'uploadAdjunto', $requireCrm));
    $router->post('/mi-empresa/crm/horas/{id}/adjuntos/{attId}/borrar', $action(\App\Modules\CrmHoras\HoraController::class, 'deleteAdjunto', $requireCrm));

    // --- CRM HORAS — Audit log (super admin) ---
    $router->get('/admin/horas/audit', [\App\Modules\CrmHoras\HoraAuditController::class, 'index']);

    // --- NOTIFICACIONES (sistema global in-app, sirve a toda la suite) ---
    $router->get('/notifications', [\App\Modules\Notifications\NotificationController::class, 'index']);
    $router->get('/notifications/feed.json', [\App\Modules\Notifications\NotificationController::class, 'feed']);
    $router->post('/notifications/marcar-todas-leidas', [\App\Modules\Notifications\NotificationController::class, 'markAllRead']);
    $router->post('/notifications/{id}/leer', [\App\Modules\Notifications\NotificationController::class, 'markRead']);
    $router->post('/notifications/{id}/eliminar', [\App\Modules\Notifications\NotificationController::class, 'softDelete']);
    // Tick global de recordatorios (público, protegido con X-RXN-Token). Llamado por n8n cada 1 min.
    $router->post('/api/internal/notifications/tick', [\App\Modules\Notifications\NotificationController::class, 'tick']);

    // --- WEB PUSH (notificaciones nativas del navegador) ---
    $router->get('/mi-perfil/web-push/status', [\App\Modules\WebPush\WebPushController::class, 'status']);
    $router->post('/mi-perfil/web-push/subscribe', [\App\Modules\WebPush\WebPushController::class, 'subscribe']);
    $router->post('/mi-perfil/web-push/unsubscribe', [\App\Modules\WebPush\WebPushController::class, 'unsubscribe']);
    $router->post('/mi-perfil/web-push/test', [\App\Modules\WebPush\WebPushController::class, 'test']);

    // --- SESSION (heartbeat para aviso preventivo de expiración) ---
    $router->get('/api/internal/session/heartbeat', [\App\Modules\Auth\SessionController::class, 'heartbeat']);

    // --- DRAFTS (autoguardado de borradores de formularios largos: PDS, Presupuestos) ---
    $router->get('/mi-perfil/borradores', [\App\Modules\Drafts\DraftsController::class, 'index']);
    $router->get('/api/internal/drafts/get', [\App\Modules\Drafts\DraftsController::class, 'get']);
    $router->post('/api/internal/drafts/save', [\App\Modules\Drafts\DraftsController::class, 'save']);
    $router->post('/api/internal/drafts/discard', [\App\Modules\Drafts\DraftsController::class, 'discard']);

    // --- INTEGRACIONES (WEBHOOKS) ---
    $router->post('/api/webhooks/anura/{slug}', [\App\Modules\CrmLlamadas\WebhookController::class, 'handleAnura']);
    // Hook de prueba interno (emulación manual vía navegador)
    $router->get('/api/webhooks/anura/{slug}/test', [\App\Modules\CrmLlamadas\WebhookController::class, 'testHook']);

    // --- RXN PWA — Launcher / sub-menú raíz con todas las PWAs disponibles ---
    $router->get('/rxnpwa', [\App\Modules\RxnPwa\RxnPwaController::class, 'launcher']);

    // --- RXN PWA (Presupuestos mobile offline) — Bloques A + B ---
    $router->get('/rxnpwa/presupuestos', [\App\Modules\RxnPwa\RxnPwaController::class, 'presupuestosShell']);
    $router->get('/rxnpwa/presupuestos/nuevo', [\App\Modules\RxnPwa\RxnPwaController::class, 'presupuestoNuevo']);
    $router->get('/rxnpwa/presupuestos/editar/{tmpUuid}', [\App\Modules\RxnPwa\RxnPwaController::class, 'presupuestoEditar']);
    $router->get('/api/rxnpwa/catalog/version', [\App\Modules\RxnPwa\RxnPwaController::class, 'catalogVersion']);
    $router->get('/api/rxnpwa/catalog/full', [\App\Modules\RxnPwa\RxnPwaController::class, 'catalogFull']);

    // --- RXN PWA — Bloque C (Fase 3): sync queue + envío a Tango ---
    $router->post('/api/rxnpwa/presupuestos/sync', [\App\Modules\RxnPwa\RxnPwaController::class, 'syncPresupuesto']);
    $router->post('/api/rxnpwa/presupuestos/{id}/attachments', [\App\Modules\RxnPwa\RxnPwaController::class, 'uploadAttachment']);
    $router->post('/api/rxnpwa/presupuestos/{id}/emit-tango', [\App\Modules\RxnPwa\RxnPwaController::class, 'emitTango']);

    // --- RXN PWA — Horas mobile (turnero CrmHoras) ---
    // Sin Tango (no aplica). Adjuntos sí (certificados médicos, fotos del trabajo).
    $router->get('/rxnpwa/horas', [\App\Modules\RxnPwa\RxnPwaController::class, 'horasShell']);
    $router->get('/rxnpwa/horas/nuevo', [\App\Modules\RxnPwa\RxnPwaController::class, 'horasNuevo']);
    $router->get('/rxnpwa/horas/editar/{tmpUuid}', [\App\Modules\RxnPwa\RxnPwaController::class, 'horasEditar']);
    $router->post('/api/rxnpwa/horas/sync', [\App\Modules\RxnPwa\RxnPwaController::class, 'syncHora']);
    $router->post('/api/rxnpwa/horas/{id}/attachments', [\App\Modules\RxnPwa\RxnPwaController::class, 'uploadHoraAttachment']);

};
