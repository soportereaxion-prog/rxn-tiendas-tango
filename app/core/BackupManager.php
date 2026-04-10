<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Database;
use PDO;
use RuntimeException;
use ZipArchive;

class BackupManager
{
    private string $backupsDir;
    private string $appDir;

    public function __construct()
    {
        $this->backupsDir = __DIR__ . '/../../storage/backups';
        $this->appDir = realpath(__DIR__ . '/../../');
        
        if (!is_dir($this->backupsDir)) {
            mkdir($this->backupsDir, 0755, true);
        }
    }

    /**
     * Backup estructural nativo en PHP de la DB para evadir limitaciones de shell/exec.
     */
    public function backupDatabase(): array
    {
        $timestamp = date('Ymd_His');
        $filename = "db_backup_{$timestamp}.sql";
        $filePath = $this->backupsDir . '/' . $filename;

        try {
            $pdo = Database::getConnection();
            $tables = [];
            $query = $pdo->query('SHOW TABLES');
            while ($row = $query->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }

            $sqlScript = "-- RXN Suite DB Native Backup\n";
            $sqlScript .= "-- Fecha: " . date('Y-m-d H:i:s') . "\n\n";
            $sqlScript .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

            foreach ($tables as $table) {
                // Estructura
                $query = $pdo->query("SHOW CREATE TABLE `$table`");
                $row = $query->fetch(PDO::FETCH_NUM);
                $sqlScript .= "-- Estructura para la tabla `$table`\n";
                $sqlScript .= "DROP TABLE IF EXISTS `$table`;\n";
                $sqlScript .= $row[1] . ";\n\n";

                // Datos
                $query = $pdo->query("SELECT * FROM `$table`");
                $numFields = $query->columnCount();
                $rowCount = $query->rowCount();

                if ($rowCount > 0) {
                    $sqlScript .= "-- Datos para la tabla `$table`\n";
                    $sqlScript .= "INSERT INTO `$table` VALUES ";
                    $rows = [];
                    while ($row = $query->fetch(PDO::FETCH_NUM)) {
                        $vals = [];
                        for ($i = 0; $i < $numFields; $i++) {
                            if (isset($row[$i])) {
                                $vals[] = $pdo->quote((string)$row[$i]);
                            } else {
                                $vals[] = 'NULL';
                            }
                        }
                        $rows[] = "(" . implode(',', $vals) . ")";
                    }
                    // Insertamos todo junto (ATENCION: si la tabla es MASIVA esto de nativo revienta RAM, 
                    // se debe seccionar por bytes, pero para la escala pedida de primer refactor nativo, sirve).
                    $sqlScript .= implode(",\n", $rows) . ";\n\n";
                }
            }

            $sqlScript .= "SET FOREIGN_KEY_CHECKS=1;\n";

            file_put_contents($filePath, $sqlScript);

            return [
                'status' => 'success',
                'file' => $filename,
                'path' => $filePath,
                'size' => filesize($filePath)
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Backup del código fuente (ignora vendors y storage).
     */
    public function backupFiles(): array
    {
        $timestamp = date('Ymd_His');
        $filename = "files_backup_{$timestamp}.zip";
        $filePath = $this->backupsDir . '/' . $filename;

        if (!extension_loaded('zip')) {
            return [
                'status' => 'error',
                'message' => 'La extensión ZipArchive no está habilitada en PHP.'
            ];
        }

        try {
            $zip = new ZipArchive();
            if ($zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException("No se pudo crear el archivo ZIP en: $filePath");
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->appDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            // Exclusiones básicas de peso y cache
            $excludeDirs = [
                DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR,
                DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR,
                DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR
            ];

            foreach ($iterator as $file) {
                if (!$file->isDir()) {
                    $realPath = $file->getRealPath();
                    $relativePath = substr($realPath, strlen($this->appDir) + 1);
                    
                    // Chequear si el path relativo ignora carpetas
                    $shouldExclude = false;
                    foreach ($excludeDirs as $exclude) {
                        if (str_contains($realPath, $exclude)) {
                            $shouldExclude = true;
                            break;
                        }
                    }

                    if (!$shouldExclude) {
                        $zip->addFile($realPath, $relativePath);
                    }
                }
            }

            $zip->close();

            return [
                'status' => 'success',
                'file' => $filename,
                'path' => $filePath,
                'size' => filesize($filePath)
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Lista los backups disponibles y los parsea
     */
    public function listHistory(): array
    {
        if (!is_dir($this->backupsDir)) {
            return [];
        }

        $files = scandir($this->backupsDir);
        $history = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || $file === '.gitkeep') continue;
            
            $path = $this->backupsDir . '/' . $file;
            $history[] = [
                'file' => $file,
                'type' => str_starts_with($file, 'db_backup') ? 'Base de Datos' : 'Archivos CSS/PHP',
                'size' => round(filesize($path) / 1024 / 1024, 2) . ' MB',
                'date' => date('Y-m-d H:i:s', filemtime($path))
            ];
        }

        // Orden más reciente primero
        usort($history, function($a, $b) {
            return strtotime($b['date']) <=> strtotime($a['date']);
        });

        return $history;
    }
}
