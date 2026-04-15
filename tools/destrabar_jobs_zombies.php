<?php

declare(strict_types=1);

/**
 * CLI: destraba jobs en estado "zombie" (queued + cancel_flag=1).
 *
 * Caso de uso: el webhook a n8n falló (p. ej. URL de prod mal configurada,
 * n8n caído), el usuario clickeó Cancelar, y el job quedó esperando un batch
 * que nunca va a llegar. El UI muestra "En cola + cancelación solicitada"
 * indefinidamente.
 *
 * Este tool recorre toda la DB, detecta esos jobs y los cierra como
 * cancelled marcando sus items pending como skipped. Scope opcional por
 * empresa.
 *
 * Uso:
 *   php tools/destrabar_jobs_zombies.php              # todas las empresas
 *   php tools/destrabar_jobs_zombies.php <empresa_id> # solo una empresa
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

use App\Modules\CrmMailMasivos\JobRepository;

$empresaId = (int) ($argv[1] ?? 0);

$repo = new JobRepository();
$count = $repo->destrabarZombies(
    $empresaId,
    'Destrabado por CLI: queued con cancel solicitado sin progreso del worker/n8n'
);

if ($empresaId > 0) {
    echo "Destrabados {$count} job(s) zombie de empresa #{$empresaId}.\n";
} else {
    echo "Destrabados {$count} job(s) zombie (todas las empresas).\n";
}

exit(0);
