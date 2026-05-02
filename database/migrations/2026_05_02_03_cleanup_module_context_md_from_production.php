<?php

declare(strict_types=1);

/**
 * Cleanup — release 1.43.6.
 *
 * Elimina los archivos `MODULE_CONTEXT.md` que ya están en producción de
 * versiones anteriores. A partir de esta release el ReleaseBuilder los excluye
 * del ZIP del OTA (son docs internas del equipo, no deben estar en el server),
 * pero los archivos previamente subidos siguen ahí porque el SystemUpdater no
 * borra archivos.
 *
 * No es una migración de DB — toca el filesystem. Igual la corremos desde el
 * MigrationRunner para que se ejecute UNA sola vez como parte del OTA.
 *
 * Idempotente: si los archivos ya no existen, no hace nada.
 */
return function (): void {
    // Salvaguarda local: si BASE_PATH es un working tree git (tiene .git/),
    // estamos en dev — NO borrar las docs. La migración se marca como aplicada
    // pero no ejecuta lógica destructiva. En producción el .git no existe
    // (ReleaseBuilder lo excluye) así que sí corre.
    if (is_dir(BASE_PATH . '/.git')) {
        return;
    }

    $modulesDir = BASE_PATH . '/app/modules';
    if (!is_dir($modulesDir)) {
        return;
    }

    $deleted = 0;
    $kept = 0;
    $errors = [];

    $entries = @scandir($modulesDir) ?: [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $modulePath = $modulesDir . '/' . $entry;
        if (!is_dir($modulePath)) continue;

        $contextFile = $modulePath . '/MODULE_CONTEXT.md';
        if (is_file($contextFile)) {
            if (@unlink($contextFile)) {
                $deleted++;
            } else {
                $errors[] = $contextFile;
                $kept++;
            }
        }
    }

    // Log silencioso para auditoría — útil si Charly quiere chequear qué pasó.
    $logDir = BASE_PATH . '/storage/logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
    $logFile = $logDir . '/cleanup-module-context.log';
    $line = sprintf(
        "[%s] Migration cleanup MODULE_CONTEXT.md: deleted=%d kept=%d errors=%s\n",
        date('Y-m-d H:i:s'),
        $deleted,
        $kept,
        $errors === [] ? 'none' : implode(',', $errors)
    );
    @file_put_contents($logFile, $line, FILE_APPEND);
};
