<?php

declare(strict_types=1);

/**
 * Ejecutor de migraciones pendientes en desarrollo local.
 *
 * Uso:
 *   php tools/run_migrations.php
 *
 * Corre TODAS las migraciones pendientes de database/migrations/ contra la
 * base de datos configurada en .env, usando el mismo MigrationRunner que el
 * módulo de Mantenimiento aplica en producción.
 *
 * Este script es el equivalente CLI de lo que el módulo de Mantenimiento hace
 * al instalar un paquete OTA. Úsalo inmediatamente después de crear una
 * migración nueva para probarla en desarrollo.
 */

define('BASE_PATH', dirname(__DIR__));

// Cargar variables de entorno
if (is_file(BASE_PATH . '/.env')) {
    foreach (file(BASE_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        putenv($line);
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

require_once BASE_PATH . '/vendor/autoload.php';

use App\Core\MigrationRunner;

$runner = new MigrationRunner();

$pending = $runner->getPendingMigrations();

if (empty($pending)) {
    echo "[OK] No hay migraciones pendientes.\n";
    exit(0);
}

echo "Migraciones pendientes detectadas (" . count($pending) . "):\n";
foreach ($pending as $file) {
    echo "  - $file\n";
}
echo "\nEjecutando...\n\n";

// userId = 0 para indicar ejecución CLI de desarrollo
$result = $runner->runPending(0);

// runPending siempre devuelve ['status' => ..., 'run' => [...]].
// Si 'run' está vacío, no había pendientes (early return interno).
$run = $result['run'] ?? [];

if (empty($run)) {
    echo "[OK] " . ($result['message'] ?? 'Sin migraciones pendientes.') . "\n";
    exit(0);
}

$ok = 0;
$fail = 0;

foreach ($run as $item) {
    if ($item['status'] === 'SUCCESS') {
        echo "  [OK] " . $item['file'] . "\n";
        $ok++;
    } else {
        echo "  [ERROR] " . $item['file'] . "\n";
        echo "    " . ($item['error'] ?? 'Error desconocido') . "\n";
        $fail++;
    }
}

echo "\nResumen: $ok OK, $fail ERROR\n";

exit($fail > 0 ? 1 : 0);
