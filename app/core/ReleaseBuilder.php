<?php

declare(strict_types=1);

namespace App\Core;

use ZipArchive;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class ReleaseBuilder
{
    private string $baseDir;
    private string $releaseDir;

    public function __construct()
    {
        $this->baseDir = BASE_PATH;
        $this->releaseDir = BASE_PATH . '/build_ota';
        
        if (!is_dir($this->releaseDir)) {
            if (!mkdir($this->releaseDir, 0777, true)) {
                throw new RuntimeException("No se pudo crear el directorio de releases: " . $this->releaseDir);
            }
        }
    }

    /**
     * Compila y genera el archivo ZIP de actualización OTA.
     *
     * @return array ['status' => 'success|error', 'file' => '/path/to/zip', 'count' => int, 'message' => string]
     */
    public function compile(): array
    {
        $zipFile = $this->releaseDir . '/rxn_update_' . date('Ymd_Hi') . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return [
                'status' => 'error',
                'message' => "No se pudo habilitar ZipArchive para crear $zipFile"
            ];
        }

        // Listas de Inclusión troncales
        $whitelistRegex = '/^(' . implode('|', [
            'app',
            'public',
            'vendor',
            'database',
            'deploy_db',
            'composer\.json',
            'composer\.lock'
        ]) . ')/i';

        // Exclusiones en public
        $publicExclusionsRegex = '/^(test_.*|db-debug.*|cli_.*|dump\.php|tmp_.*|.*\.bak|.*\.old|uploads)$/i';

        $iterator = new RecursiveDirectoryIterator($this->baseDir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST);

        $count = 0;
        foreach ($files as $file) {
            $realPath = $file->getRealPath();
            
            // Ignorar nuestra propia carpeta de builds y git
            if (str_starts_with($realPath, $this->releaseDir) || str_contains($realPath, '.git') || str_contains($realPath, 'build')) {
                continue;
            }
            
            $relativePath = str_replace($this->baseDir . DIRECTORY_SEPARATOR, '', $realPath);
            $relativePathNormalized = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

            // Ignorar root si no es carpeta permitida
            if (!preg_match($whitelistRegex, $relativePathNormalized)) {
                continue;
            }

            // Reglas de exclusión public
            if (str_starts_with($relativePathNormalized, 'public/')) {
                if (str_starts_with($relativePathNormalized, 'public/uploads')) {
                    continue;
                }
                if ($file->isFile() && preg_match($publicExclusionsRegex, $file->getFilename())) {
                    continue;
                }
            }

            // Reglas PSR-4 Casing de Linux
            $pathParts = explode('/', $relativePathNormalized);
            if (isset($pathParts[0])) {
                $base = strtolower($pathParts[0]);
                if (in_array($base, ['app', 'public', 'vendor'])) {
                    $pathParts[0] = $base;
                }
            }

            if (isset($pathParts[0]) && $pathParts[0] === 'app') {
                if (isset($pathParts[1])) {
                    $sub = strtolower($pathParts[1]);
                    if (in_array($sub, ['core', 'config', 'modules', 'shared', 'storage'])) {
                        $pathParts[1] = $sub;
                        
                        if ($sub === 'modules' && isset($pathParts[2])) {
                            $pathParts[2] = ucfirst($pathParts[2]);
                        }
                        
                        if ($sub === 'shared' && isset($pathParts[2])) {
                            $lowerP2 = strtolower($pathParts[2]);
                            if (in_array($lowerP2, ['views', 'middleware'])) {
                                $pathParts[2] = $lowerP2;
                            } else {
                                $pathParts[2] = ucfirst($pathParts[2]);
                            }
                        }
                    } elseif ($sub === 'infrastructure') {
                        $pathParts[1] = 'Infrastructure';
                    }
                }
            }
            
            $finalZipPath = implode('/', $pathParts);

            if ($file->isDir()) {
                $zip->addEmptyDir($finalZipPath);
            } else {
                $zip->addFile($realPath, $finalZipPath);
                $count++;
            }
        }

        $zip->close();

        return [
            'status' => 'success',
            'file' => $zipFile,
            'filename' => basename($zipFile),
            'count' => $count,
            'message' => "Paquete OTA compilado correctamente ($count archivos)."
        ];
    }
}
