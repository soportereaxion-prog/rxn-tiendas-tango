<?php

declare(strict_types=1);

/**
 * Job de purga de eventos de geo-tracking viejos.
 *
 * Itera las empresas que tienen eventos, lee su `retention_days` configurado
 * (default 90) y borra los eventos más viejos que ese período.
 *
 * USO LOCAL:
 *   php app/modules/RxnGeoTracking/tools/purge_geo_events.php [--dry-run] [--verbose]
 *
 * USO EN PROD (cron diario, recomendado a las 3 AM):
 *   0 3 * * * cd /var/www/rxn_suite && php app/modules/RxnGeoTracking/tools/purge_geo_events.php >> storage/logs/geo_purge.log 2>&1
 *
 * FLAGS:
 *   --dry-run   → NO borra nada, solo reporta cuántas filas borraría.
 *   --verbose   → imprime detalle por empresa incluso si no hay nada que borrar.
 *
 * SEGURIDAD:
 *   Este script corre desde CLI sin sesión. Nunca expone datos. El DELETE filtra
 *   por empresa_id + condición de fecha — no hay forma de que borre más de lo que
 *   corresponde.
 */

// Detectar BASE_PATH robustamente: el script puede invocarse desde distintos cwd.
// dirname 4 veces: tools → RxnGeoTracking → modules → app → proyecto.
define('BASE_PATH', dirname(__DIR__, 4));

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

use App\Core\Database;
use App\Modules\RxnGeoTracking\GeoEventRepository;
use App\Modules\RxnGeoTracking\GeoTrackingConfigRepository;

$args = array_slice($argv, 1);
$dryRun = in_array('--dry-run', $args, true);
$verbose = in_array('--verbose', $args, true);

$startedAt = date('Y-m-d H:i:s');
echo "[$startedAt] === Purga de eventos de geo-tracking ===" . PHP_EOL;
if ($dryRun) {
    echo "[MODO DRY-RUN] No se va a borrar nada, solo se reportan conteos." . PHP_EOL;
}

try {
    $db = Database::getConnection();
    $events = new GeoEventRepository();
    $config = new GeoTrackingConfigRepository();
} catch (\Throwable $e) {
    echo '[ERROR] No se pudo inicializar: ' . $e->getMessage() . PHP_EOL;
    exit(2);
}

// Empresas que tienen al menos un evento — son las únicas candidatas a purga.
$stmt = $db->query('SELECT DISTINCT empresa_id FROM rxn_geo_eventos ORDER BY empresa_id');
$empresaIds = $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];

if ($empresaIds === []) {
    echo 'No hay eventos registrados — nada que purgar.' . PHP_EOL;
    exit(0);
}

$totalBorrados = 0;
$totalEmpresasProcesadas = 0;
$totalEmpresasConPurga = 0;

foreach ($empresaIds as $empresaId) {
    $empresaId = (int) $empresaId;
    $cfg = $config->getConfig($empresaId);
    $retention = (int) $cfg['retention_days'];

    // Contar candidatos antes de borrar para logging.
    $stmt = $db->prepare('SELECT COUNT(*) FROM rxn_geo_eventos
        WHERE empresa_id = :empresa_id
          AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)');
    $stmt->bindValue(':empresa_id', $empresaId, \PDO::PARAM_INT);
    $stmt->bindValue(':days', $retention, \PDO::PARAM_INT);
    $stmt->execute();
    $candidatos = (int) $stmt->fetchColumn();

    $totalEmpresasProcesadas++;

    if ($candidatos === 0) {
        if ($verbose) {
            echo "  Empresa $empresaId — retención {$retention}d — sin eventos viejos." . PHP_EOL;
        }
        continue;
    }

    if ($dryRun) {
        echo "  Empresa $empresaId — retención {$retention}d — BORRARÍA $candidatos evento(s)." . PHP_EOL;
        $totalBorrados += $candidatos;
        $totalEmpresasConPurga++;
        continue;
    }

    $borrados = $events->purgeOlderThan($empresaId, $retention);
    echo "  Empresa $empresaId — retención {$retention}d — borrados: $borrados." . PHP_EOL;
    $totalBorrados += $borrados;
    $totalEmpresasConPurga++;
}

$finishedAt = date('Y-m-d H:i:s');
echo PHP_EOL;
echo "[$finishedAt] === Resumen ===" . PHP_EOL;
echo "  Empresas procesadas:  $totalEmpresasProcesadas" . PHP_EOL;
echo "  Empresas con purga:   $totalEmpresasConPurga" . PHP_EOL;
echo "  Eventos " . ($dryRun ? 'que se borrarían' : 'borrados') . ': ' . $totalBorrados . PHP_EOL;

exit(0);
