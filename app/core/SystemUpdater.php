<?php

declare(strict_types=1);

namespace App\Core;

use Exception;
use ZipArchive;
use App\Core\BackupManager;

class SystemUpdater
{
    private string $updatesDir;
    private BackupManager $backupManager;

    /**
     * Lista negra crítica de rutas que JAMÁS deben ser sobrescritas 
     * por un Update ZIP. (Rutas relativas a BASE_PATH)
     */
    protected const EXCLUDED_PREFIXES = [
        '.env',
        '.htaccess', // Excluido al aplicar, el config del server es sagrado.
        'storage/',
        'public/uploads/',
        '.git/',
        'build_release.zip'
    ];

    public function __construct()
    {
        $this->updatesDir = BASE_PATH . '/storage/backups/updates';
        if (!is_dir($this->updatesDir)) {
            mkdir($this->updatesDir, 0755, true);
        }
        $this->backupManager = new BackupManager();
    }

    /**
     * Procesa la subida, ejecuta backups de resguardo y extrae la actualización.
     * 
     * @param array $fileInfo Arreglo proveniente de $_FILES['update_zip']
     * @return array ['status' => 'success|error', 'message' => '...']
     */
    public function processUpdate(array $fileInfo, bool $autoMigrate = false, ?int $userId = null): array
    {
        // 1. Validación Básica de Subida PHP
        if ($fileInfo['error'] !== UPLOAD_ERR_OK) {
            return [
                'status' => 'error', 
                'message' => 'Error al subir el archivo (Código: ' . $fileInfo['error'] . '). Verifique las directivas upload_max_filesize de su PHP.ini.'
            ];
        }

        $tmpFile = $fileInfo['tmp_name'];
        if (!file_exists($tmpFile)) {
             return ['status' => 'error', 'message' => 'El archivo temporal no existe en el servidor.'];
        }

        // 2. Mover a permanente para validación e historial
        $timestamp = date('Ymd_His');
        $fileNameRaw = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $fileInfo['name']);
        $finalZipPath = $this->updatesDir . '/update_' . $timestamp . '_' . $fileNameRaw;

        if (!move_uploaded_file($tmpFile, $finalZipPath)) {
            return ['status' => 'error', 'message' => 'No se pudo almacenar el archivo de actualización en el directorio protegido.'];
        }

        // 3. Validar integridad de Archivo ZIP
        $zip = new ZipArchive();
        if ($zip->open($finalZipPath) !== true) {
            unlink($finalZipPath);
            return ['status' => 'error', 'message' => 'El archivo subido está corrupto o no es un formato ZIP válido.'];
        }

        // Inspección profunda de estructura
        $hasCoreFiles = false;
        $totalFiles = $zip->numFiles;
        for ($i = 0; $i < $totalFiles; $i++) {
            $name = $zip->getNameIndex($i);
            // El zip de build correcto debe contener la raíz de la app u otros core
            if (str_starts_with($name, 'app/') || str_starts_with($name, 'public/') || str_starts_with($name, 'database/') || str_starts_with($name, 'vendor/')) {
                $hasCoreFiles = true;
                break;
            }
        }

        if (!$hasCoreFiles) {
            $zip->close();
            unlink($finalZipPath); // Lo descartamos porque es basura.
            return ['status' => 'error', 'message' => 'El archivo ZIP carece de la estructura troncal del sistema operativo (app/, public/, database/). Abortado por seguridad.'];
        }
        
        // 4. Backups Preventivos (Resguardo Obligatorio antes de pisar)
        // El usuario pidió explícitamente "Backup previo obligatorio (archivos + DB)"
        $bkpDb = $this->backupManager->backupDatabase();
        if ($bkpDb['status'] !== 'success') {
            $zip->close();
            return ['status' => 'error', 'message' => 'Abortado: Falló el Backup previo obligatorio de la Base de Datos. Detalles: ' . $bkpDb['message']];
        }
        
        $bkpFile = $this->backupManager->backupFiles();
        if ($bkpFile['status'] !== 'success') {
            $zip->close();
            return ['status' => 'error', 'message' => 'Abortado: Falló el Backup previo obligatorio de los Archivos del sistema. Detalles: ' . $bkpFile['message']];
        }
        
        // 5. Extracción Segura
        $extractedFiles = 0;
        $ignoredFiles = 0;
        
        for ($i = 0; $i < $totalFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            
            // Impedir Directory Traversal (Zip Slip Vulnerability)
            if (strpos($filename, '..') !== false) {
                 continue; 
            }

            // Aplicar matriz de exclusiones de seguridad
            if ($this->shouldIgnoreFile($filename)) {
                $ignoredFiles++;
                continue;
            }
            
            $targetPath = BASE_PATH . DIRECTORY_SEPARATOR . $filename;
            $isDir = str_ends_with($filename, '/');
            
            if ($isDir) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                $dir = dirname($targetPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                
                // Extraemos en memoria e inyectamos al destino (Sobreescritura destructiva controlada)
                $contents = $zip->getFromIndex($i);
                if ($contents !== false) {
                    file_put_contents($targetPath, $contents);
                    $extractedFiles++;
                }
            }
        }
        
        $zip->close();
        
        $msgAddition = "";
        // 6. Integración Funcional DB (Novedad)
        if ($autoMigrate) {
            try {
                $migrationRunner = new \App\Core\MigrationRunner();
                $pending = $migrationRunner->getPendingMigrations();
                if (count($pending) > 0) {
                    $results = $migrationRunner->runPending($userId ?? 0);
                    $successes = 0;
                    if (isset($results['run']) && is_array($results['run'])) {
                        foreach ($results['run'] as $res) {
                            if (($res['status'] ?? '') === 'SUCCESS') $successes++;
                        }
                    } else if (isset($results[0])) { // Compatibilidad si retorna array de arrays
                        foreach ($results as $res) {
                            if (($res['status'] ?? '') === 'SUCCESS') $successes++;
                        }
                    }
                    $msgAddition = " Además, se detectaron y ejecutaron exitosamente {$successes} migraciones de BD.";
                } else {
                    $msgAddition = " El paquete no requería modificaciones en la Base de Datos.";
                }
            } catch (\Exception $e) {
                return [
                    'status' => 'error', 
                    'message' => "Se instalaron $extractedFiles archivos, pero la inicialización de Base de Datos interrumpió la operación: " . $e->getMessage()
                ];
            }
        }
        
        return [
            'status' => 'success', 
            'message' => "Sistema actualizado exitosamente. Se aplicaron $extractedFiles archivos (Protegidos $ignoredFiles).$msgAddition"
        ];
    }
    
    /**
     * Determina si el archivo listado en el ZIP debe bloquearse de ser grabado.
     */
    private function shouldIgnoreFile(string $filename): bool
    {
        $normalized = strtolower(str_replace('\\', '/', ltrim($filename, '/')));
        
        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                return true;
            }
        }
        return false;
    }
}
