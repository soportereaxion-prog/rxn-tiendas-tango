<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Database;
use PDO;
use RuntimeException;

class MigrationRunner
{
    private string $migrationsDir;

    public function __construct()
    {
        $this->migrationsDir = __DIR__ . '/../../database/migrations';
    }

    /**
     * Asegura que la tabla de trazabilidad RXN_MIGRACIONES exista.
     */
    private function checkAndCreateTrackerTable(): void
    {
        $pdo = Database::getConnection();
        $query = "
            CREATE TABLE IF NOT EXISTS RXN_MIGRACIONES (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migracion VARCHAR(255) NOT NULL UNIQUE,
                fecha_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
                resultado VARCHAR(50) NOT NULL,
                observaciones TEXT NULL,
                usuario_ejecutor INT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $pdo->exec($query);
    }

    /**
     * Devuelve migraciones ejecutadas.
     */
    public function getExecutedMigrations(): array
    {
        $this->checkAndCreateTrackerTable();
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT migracion FROM RXN_MIGRACIONES WHERE resultado = 'SUCCESS' ORDER BY fecha_hora ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * Devuelve las migraciones fallidas o con otro estado.
     */
    public function getErrorMigrations(): array
    {
        $this->checkAndCreateTrackerTable();
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT * FROM RXN_MIGRACIONES WHERE resultado != 'SUCCESS' ORDER BY fecha_hora DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Obtiene el listado de archivos en la carpeta de migraciones.
     */
    public function getAvailableMigrations(): array
    {
        if (!is_dir($this->migrationsDir)) {
            return [];
        }

        $files = scandir($this->migrationsDir);
        $migrations = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || substr($file, -4) !== '.php') {
                continue;
            }
            $migrations[] = $file;
        }

        sort($migrations); // Orden alfabético/cronológico
        return $migrations;
    }

    /**
     * Calcula cuáles migraciones faltan ejecutarse.
     */
    public function getPendingMigrations(): array
    {
        $executed = $this->getExecutedMigrations();
        $available = $this->getAvailableMigrations();

        return array_values(array_diff($available, $executed));
    }

    /**
     * Ejecuta todas las migraciones pendientes.
     * Retorna array con resultados por archivo.
     */
    public function runPending(int $userId): array
    {
        $this->checkAndCreateTrackerTable();
        $pending = $this->getPendingMigrations();
        $results = [];

        if (empty($pending)) {
            return [
                'status' => 'success',
                'message' => 'No hay migraciones pendientes.',
                'run' => []
            ];
        }

        $pdo = Database::getConnection();

        foreach ($pending as $migrationFile) {
            $filePath = $this->migrationsDir . '/' . $migrationFile;
            
            try {
                // Iniciar transacción de BD si el script llega a usarla, 
                // pero lo mejor es envolver todo aquí si es posible (cuidado con DDL que autocommitean en MySQL)
                $pdo->beginTransaction();
                
                $migrationScript = require $filePath;
                
                if (is_callable($migrationScript)) {
                    $migrationScript($pdo);
                } else {
                    // Script Legacy Procedural.
                    // Si el require no arrojó Throw/Excepción, asumimos que su lógica
                    // procedural se ejecutó correctamente in situ al incluir el archivo.
                }

                try {
                    if ($pdo->inTransaction()) {
                        $pdo->commit();
                    }
                } catch (\PDOException $pdoE) {
                    if (strpos($pdoE->getMessage(), 'There is no active transaction') === false) {
                        throw $pdoE;
                    }
                }
                
                $this->logMigration($migrationFile, 'SUCCESS', 'Ejecución completada', $userId);
                
                $results[] = [
                    'file' => $migrationFile,
                    'status' => 'SUCCESS'
                ];
            } catch (\Throwable $e) {
                try {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                } catch (\PDOException $pdoE) {
                    // Ignorar si no hay transacción activa (posible auto-commit previo por DDL)
                }
                
                $errorMsg = $e->getMessage();
                $this->logMigration($migrationFile, 'ERROR', $errorMsg, $userId);
                
                $results[] = [
                    'file' => $migrationFile,
                    'status' => 'ERROR',
                    'error' => $errorMsg
                ];
                
                // Cortar la cadena para no romper dependencias posteriores
                break;
            }
        }

        return [
            'status' => (count(array_filter($results, fn($r) => $r['status'] === 'ERROR')) > 0) ? 'error' : 'success',
            'run' => $results
        ];
    }

    private function logMigration(string $migracion, string $resultado, string $observaciones, int $userId): void
    {
        $pdo = Database::getConnection();
        // Insertamos o actualizamos en caso de re-intentos de error
        $stmt = $pdo->prepare("
            INSERT INTO RXN_MIGRACIONES (migracion, resultado, observaciones, usuario_ejecutor, fecha_hora)
            VALUES (:mig, :res, :obs, :usr, NOW())
            ON DUPLICATE KEY UPDATE 
                resultado = VALUES(resultado), 
                observaciones = VALUES(observaciones),
                usuario_ejecutor = VALUES(usuario_ejecutor),
                fecha_hora = NOW()
        ");
        
        $stmt->execute([
            ':mig' => $migracion,
            ':res' => $resultado,
            ':obs' => $observaciones,
            ':usr' => $userId
        ]);
    }

    /**
     * Establece el Baseline simulando que todas las migraciones disponibles fueron exitosas.
     * Ideal para enganchar una base de datos de producción existente al sistema.
     */
    public function markBaseline(int $userId): array
    {
        $this->checkAndCreateTrackerTable();
        $available = $this->getAvailableMigrations();
        $results = [];

        if (empty($available)) {
            return [
                'status' => 'success',
                'message' => 'No hay migraciones en disco para asentar.',
                'run' => []
            ];
        }

        foreach ($available as $migrationFile) {
            try {
                // Registrar sin ejecutar
                $this->logMigration($migrationFile, 'SUCCESS', 'Baseline (Marcado manual inicial en Prod)', $userId);
                
                $results[] = [
                    'file' => $migrationFile,
                    'status' => 'SUCCESS'
                ];
            } catch (\Throwable $e) {
                $errorMsg = $e->getMessage();
                $this->logMigration($migrationFile, 'ERROR', "Fallo al asentar Baseline: " . $errorMsg, $userId);
                
                $results[] = [
                    'file' => $migrationFile,
                    'status' => 'ERROR',
                    'error' => $errorMsg
                ];
                break;
            }
        }

        return [
            'status' => (count(array_filter($results, fn($r) => $r['status'] === 'ERROR')) > 0) ? 'error' : 'success',
            'run' => $results,
            'message' => 'Baseline establecido satisfactoriamente.'
        ];
    }
}
