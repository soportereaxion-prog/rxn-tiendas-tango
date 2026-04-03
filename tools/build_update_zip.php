<?php

/**
 * Creador de Release ZIP para RXN Suite (Actualización OTA)
 * Ejecutar localmente en desarrollo para empaquetar una release limpia.
 */

$baseDir = dirname(__DIR__);
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $baseDir);
}

require_once $baseDir . '/vendor/autoload.php';

use App\Core\ReleaseBuilder;

try {
    echo "Inicializando motor de Releases (OTA)...\n";
    $builder = new ReleaseBuilder();
    $result = $builder->compile();

    if ($result['status'] === 'success') {
        echo ">> ¡Paquete Limpio Generado con Éxito!\n";
        echo ">> Archivo: " . $result['file'] . "\n";
        echo ">> Total archivos compilados: " . $result['count'] . "\n\n";
        echo "Próximo paso: Suba este archivo en el Módulo 'Mantenimiento' de Producción.\n";
    } else {
        echo ">> ERROR FATAL: " . $result['message'] . "\n";
    }
} catch (\Exception $e) {
    echo ">> EXCEPCIÓN FATAL: " . $e->getMessage() . "\n";
}
