<?php

declare(strict_types=1);

/**
 * CLI worker para procesar un envío masivo completo.
 *
 * Uso:
 *   php tools/process_mail_job.php <job_id> [batch_size]
 *
 * Procesa el job en loop, respetando:
 *   - max_per_batch de la SMTP config del usuario
 *   - pause_seconds entre batches
 *   - cancel_flag del job (corta si se solicita)
 *
 * Útil para:
 *   - Testing end-to-end sin depender de n8n
 *   - Reintentar un job que quedó en cola porque n8n no contestó
 *   - Fallback si n8n está caído
 *
 * Output: una línea por batch + resumen final.
 */

define('BASE_PATH', dirname(__DIR__));

if (is_file(BASE_PATH . '/.env')) {
    foreach (file(BASE_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        putenv($line);
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v);
    }
}

require_once BASE_PATH . '/vendor/autoload.php';

use App\Modules\CrmMailMasivos\Services\BatchProcessor;

$jobId = (int) ($argv[1] ?? 0);
$batchSize = (int) ($argv[2] ?? 0);

if ($jobId <= 0) {
    echo "Uso: php tools/process_mail_job.php <job_id> [batch_size]\n";
    exit(1);
}

$processor = new BatchProcessor();
$iteration = 0;
$totalSent = 0;
$totalFailed = 0;
$totalSkipped = 0;

echo "Procesando job #{$jobId}...\n";

while (true) {
    $iteration++;

    try {
        $result = $processor->processBatch($jobId, $batchSize > 0 ? $batchSize : 50);
    } catch (Throwable $e) {
        echo "  [ERROR] Iter {$iteration}: " . $e->getMessage() . "\n";
        exit(2);
    }

    $batchSent = (int) ($result['batch_sent'] ?? 0);
    $batchFailed = (int) ($result['batch_failed'] ?? 0);
    $batchSkipped = (int) ($result['batch_skipped'] ?? 0);
    $totalSent += $batchSent;
    $totalFailed += $batchFailed;
    $totalSkipped += $batchSkipped;

    echo sprintf(
        "  [iter %d] estado=%s  sent=%d  fail=%d  skip=%d  remaining=%d\n",
        $iteration,
        (string) $result['estado'],
        $batchSent,
        $batchFailed,
        $batchSkipped,
        (int) ($result['remaining'] ?? 0)
    );

    if (!empty($result['is_final'])) {
        break;
    }

    // Esperar pause_seconds antes del próximo batch
    $pause = (int) ($result['pause_seconds'] ?? 5);
    if ($pause > 0) {
        echo "    pausa {$pause}s antes del próximo batch...\n";
        sleep($pause);
    }
}

echo "\n─────────────────────────────────────────────\n";
echo "Resultado final:\n";
echo "  Job #{$jobId}: {$result['estado']}\n";
echo "  Enviados:  {$result['total_enviados']}\n";
echo "  Fallidos:  {$result['total_fallidos']}\n";
echo "  Saltados:  {$result['total_skipped']}\n";
echo "  Total:     {$result['total_destinatarios']}\n";
echo "  Iteraciones: {$iteration}\n";
echo "─────────────────────────────────────────────\n";

exit($result['estado'] === 'completed' ? 0 : 1);
