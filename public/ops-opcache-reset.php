<?php
/**
 * OPcache reset puntual — utilidad de dev para cuando PHP está sirviendo
 * versiones stale de archivos editados durante una iteración.
 *
 * Uso: abrir en el browser http://{server}/ops-opcache-reset.php
 *
 * Motivo para existir: cuando movemos un archivo PHP entre módulos o editamos
 * un catch/handler, OPcache puede mantener en memoria la versión previa del
 * archivo si su mtime no se actualizó (algunas herramientas de edit no tocan
 * correctamente el filesystem timestamp). Ese opcode cacheado sirve el código
 * viejo → el comportamiento no cambia aunque el archivo nuevo esté en disco.
 *
 * Pensado como archivo efímero — borrar después de validar que el problema
 * estaba en OPcache (ver release 1.12.6).
 *
 * Seguridad: solo disponible cuando APP_ENV no es producción. En prod devuelve
 * 404 para evitar exposición de información sensible.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$appEnv = getenv('APP_ENV') ?: 'dev';
if (strtolower($appEnv) === 'prod' || strtolower($appEnv) === 'production') {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Not found']);
    exit;
}

$response = [
    'success' => false,
    'opcache_enabled' => false,
    'php_version' => PHP_VERSION,
    'actions' => [],
];

if (!function_exists('opcache_reset')) {
    $response['message'] = 'OPcache no está instalado en este PHP.';
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

$status = @opcache_get_status(false);
$response['opcache_enabled'] = is_array($status) && !empty($status['opcache_enabled']);

if (!$response['opcache_enabled']) {
    $response['message'] = 'OPcache está instalado pero deshabilitado.';
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

// Reset completo
$resetOk = @opcache_reset();
$response['actions'][] = ['op' => 'opcache_reset', 'ok' => $resetOk];

// Invalidar específicamente los archivos de la iteración reciente
$filesToInvalidate = [
    __DIR__ . '/../app/modules/RxnSync/RxnSyncController.php',
    __DIR__ . '/../app/modules/RxnSync/Services/CommercialCatalogSyncService.php',
    __DIR__ . '/../app/modules/CrmPresupuestos/CommercialCatalogSyncService.php',
    __DIR__ . '/../app/modules/CrmPresupuestos/PresupuestoController.php',
    __DIR__ . '/../app/modules/Tango/TangoApiClient.php',
    __DIR__ . '/../app/modules/Tango/Services/TangoSyncService.php',
    __DIR__ . '/../app/modules/EmpresaConfig/EmpresaConfigController.php',
    __DIR__ . '/../app/modules/EmpresaConfig/views/index.php',
    __DIR__ . '/../app/modules/RxnSync/views/index.php',
    __DIR__ . '/../app/config/routes.php',
    __DIR__ . '/../app/config/version.php',
];

foreach ($filesToInvalidate as $path) {
    $exists = file_exists($path);
    $invalidated = false;
    if ($exists) {
        $invalidated = @opcache_invalidate($path, true);
    }
    $response['actions'][] = [
        'op' => 'opcache_invalidate',
        'file' => str_replace('\\', '/', realpath($path) ?: $path),
        'exists' => $exists,
        'ok' => $invalidated,
    ];
}

$response['success'] = true;
$response['message'] = 'OPcache reseteado. Recargá la página del módulo y probá de nuevo.';

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
