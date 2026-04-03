<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Core\Controller;
use App\Core\View;
use App\Modules\Auth\AuthService;
use App\Core\MigrationRunner;
use App\Core\BackupManager;
use App\Core\SystemUpdater;
use App\Core\ReleaseBuilder;

class MantenimientoController extends Controller
{
    /**
     * Muestra la interfaz del Módulo de Mantenimiento (Backups y Migraciones).
     */
    public function index(): void
    {
        AuthService::requireRxnAdmin();

        $migrationRunner = new MigrationRunner();
        $backupManager = new BackupManager();

        $pendingMigrations = $migrationRunner->getPendingMigrations();
        $executedMigrations = $migrationRunner->getExecutedMigrations();
        $errorMigrations = $migrationRunner->getErrorMigrations();
        $backupsHistory = $backupManager->listHistory();

        // Extraer Version si está disponible
        $appVersion = 'Desconocida';
        if (file_exists(__DIR__ . '/../../../config/version.php')) {
            $versionData = require __DIR__ . '/../../../config/version.php';
            if (is_array($versionData) && isset($versionData['build'])) {
                $appVersion = $versionData['version'] . ' (Build ' . $versionData['build'] . ')';
            }
        }

        View::render('app/modules/Admin/views/mantenimiento.php', [
            'appVersion' => $appVersion,
            'pendingMigrations' => $pendingMigrations,
            'executedCount' => count($executedMigrations),
            'errorMigrations' => $errorMigrations,
            'backupsHistory' => $backupsHistory,
            'success' => $_GET['success'] ?? null,
            'error' => $_GET['error'] ?? null
        ]);
    }

    /**
     * Ejecuta todas las migraciones de Base de Datos pendientes
     */
    public function runMigrations(): void
    {
        AuthService::requireRxnAdmin();

        $migrationRunner = new MigrationRunner();
        $userId = $_SESSION['user_id'] ?? 0;

        $result = $migrationRunner->runPending((int)$userId);

        if ($result['status'] === 'success') {
            header('Location: /admin/mantenimiento?success=Migraciones+ejecutadas+correctamente');
        } else {
            // Buscamos el error
            $err = '';
            if (isset($result['run']) && is_array($result['run'])) {
                foreach ($result['run'] as $r) {
                    if ($r['status'] === 'ERROR') {
                        $err = $r['error'];
                        break;
                    }
                }
            }
            header('Location: /admin/mantenimiento?error=Fallo+en+migracion:+' . urlencode($err));
        }
        exit;
    }

    /**
     * Genera un volcado de BD
     */
    public function runDbBackup(): void
    {
        AuthService::requireRxnAdmin();

        $backupManager = new BackupManager();
        $result = $backupManager->backupDatabase();

        if ($result['status'] === 'success') {
            header('Location: /admin/mantenimiento?success=Respaldo+DB+creado:+' . urlencode($result['file']));
        } else {
            header('Location: /admin/mantenimiento?error=Fallo+backup+BD:+' . urlencode($result['message']));
        }
        exit;
    }

    /**
     * Genera un paquete Zip del source code (sin vendor ni storage)
     */
    public function runFilesBackup(): void
    {
        AuthService::requireRxnAdmin();

        $backupManager = new BackupManager();
        $result = $backupManager->backupFiles();

        if ($result['status'] === 'success') {
            header('Location: /admin/mantenimiento?success=Respaldo+Archivos+creado:+' . urlencode($result['file']));
        } else {
            header('Location: /admin/mantenimiento?error=Fallo+backup+Archivos:+' . urlencode($result['message']));
        }
        exit;
    }

    /**
     * Establece el Baseline manual. Solo para entornos productivos con datos preexistentes.
     */
    public function baseline(): void
    {
        AuthService::requireRxnAdmin();

        $migrationRunner = new MigrationRunner();
        $userId = $_SESSION['user_id'] ?? 0;

        $result = $migrationRunner->markBaseline((int)$userId);

        if ($result['status'] === 'success') {
            header('Location: /admin/mantenimiento?success=Lineabase+creada+correctamente');
        } else {
            $err = '';
            if (isset($result['run']) && is_array($result['run'])) {
                foreach ($result['run'] as $r) {
                    if ($r['status'] === 'ERROR') {
                        $err = $r['error'];
                        break;
                    }
                }
            }
            header('Location: /admin/mantenimiento?error=Fallo+al+establecer+lineabase:+' . urlencode($err));
        }
        exit;
    }

    /**
     * Procesa la subida de un paquete de actualización ZIP (OTA).
     */
    public function uploadUpdate(): void
    {
        AuthService::requireRxnAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['update_zip'])) {
            header('Location: /admin/mantenimiento?error=Solicitud+inválida.');
            exit;
        }

        $updater = new SystemUpdater();
        $result = $updater->processUpdate($_FILES['update_zip']);

        if ($result['status'] === 'success') {
            header('Location: /admin/mantenimiento?success=' . urlencode($result['message']));
        } else {
            header('Location: /admin/mantenimiento?error=' . urlencode($result['message']));
        }
        exit;
    }

    /**
     * Construye dinámicamente el paquete ZIP (Factory OTA) si está en entorno de desarrollo
     */
    public function buildRelease(): void
    {
        AuthService::requireRxnAdmin();

        $env = getenv('APP_ENV') ?: 'production';
        if (!in_array($env, ['local', 'dev', 'development'])) {
            header('Location: /admin/mantenimiento?error=Operación+restringida+solo+a+entornos+de+desarrollo.');
            exit;
        }

        try {
            $builder = new ReleaseBuilder();
            $result = $builder->compile();

            if ($result['status'] === 'success') {
                $_SESSION['last_ota_release'] = $result['filename'];
                header('Location: /admin/mantenimiento?success=' . urlencode($result['message']));
            } else {
                header('Location: /admin/mantenimiento?error=' . urlencode($result['message']));
            }
        } catch (\Exception $e) {
            header('Location: /admin/mantenimiento?error=' . urlencode('Fallo crítico al compilar release: ' . $e->getMessage()));
        }
        exit;
    }

    /**
     * Descarga el último paquete OTA generado (Factory)
     */
    public function downloadRelease(): void
    {
        AuthService::requireRxnAdmin();

        if (empty($_GET['file'])) {
            die("Archivo no especificado.");
        }

        $filename = basename($_GET['file']);
        $filepath = BASE_PATH . '/build_ota/' . $filename;

        if (!file_exists($filepath)) {
            die("El paquete solicitado ya no existe en el disco.");
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }
}
