<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Core\Controller;
use App\Core\View;
use App\Core\EnvManager;
use App\Modules\Auth\AuthService;

class GlobalConfigController extends Controller
{
    private EnvManager $envManager;

    public function __construct()
    {
        $this->envManager = new EnvManager();
    }

    /**
     * Muestra la interfaz administrativa del SMTP RXN Global.
     */
    public function showSmtpGlobal(): void
    {
        // Regla de Negocio: En un futuro aquí se forzará rol === 'superadmin'
        AuthService::requireLogin();
        
        $envVars = $this->envManager->getParsedVariables();

        // Extraer específicamente las vars inherentes al SMTP
        $smtpData = [
            'host'       => $envVars['MAIL_HOST'] ?? '',
            'port'       => $envVars['MAIL_PORT'] ?? '587',
            'user'       => $envVars['MAIL_USER'] ?? '',
            'pass'       => $envVars['MAIL_PASS'] ?? '', // Se entregará ofuscado o vacío según prefiera UI
            'secure'     => $envVars['MAIL_SECURE'] ?? 'tls',
            'from_email' => $envVars['MAIL_FROM_ADDRESS'] ?? '',
            'from_name'  => trim($envVars['MAIL_FROM_NAME'] ?? '', '"\''),
        ];

        View::render('app/modules/Admin/views/smtp_global.php', [
            'smtp' => $smtpData,
            'success' => $_GET['success'] ?? null,
            'error'   => $_GET['error'] ?? null
        ]);
    }

    /**
     * Procesa la mutación sobre el .env y persiste los cambios estructurales.
     */
    public function updateSmtpGlobal(): void
    {
        AuthService::requireLogin();

        try {
            $updates = [
                'MAIL_HOST'         => trim($_POST['host'] ?? ''),
                'MAIL_PORT'         => (int)($_POST['port'] ?? 587),
                'MAIL_USER'         => trim($_POST['user'] ?? ''),
                'MAIL_SECURE'       => trim($_POST['secure'] ?? ''),
                'MAIL_FROM_ADDRESS' => trim($_POST['from_email'] ?? ''),
                'MAIL_FROM_NAME'    => '"' . trim($_POST['from_name'] ?? '') . '"'
            ];

            // Solo pisamos la Contraseña global si han tapeado algo explícitamente, de lo contrario la preservamos
            if (!empty($_POST['pass'])) {
                $updates['MAIL_PASS'] = trim($_POST['pass']);
            }

            $this->envManager->updateVariables($updates);

            header('Location: /rxnTiendasIA/public/admin/smtp-global?success=Configuración+Master+actualizada+correctamente');
            exit;

        } catch (\Exception $e) {
            header('Location: /rxnTiendasIA/public/admin/smtp-global?error=' . urlencode($e->getMessage()));
            exit;
        }
    }

    /**
     * Validador AJAX de Handshake SMTP Master RXN en tiempo de ejecución
     */
    public function testConnection(): void
    {
        AuthService::requireLogin();
        
        header('Content-Type: application/json');
        
        $config = [
            'host' => trim($_POST['host'] ?? ''),
            'port' => (int)($_POST['port'] ?? 587),
            'user' => trim($_POST['user'] ?? ''),
            'pass' => trim($_POST['pass'] ?? ''),
            'secure' => trim($_POST['secure'] ?? ''),
            'from_email' => trim($_POST['from_email'] ?? ''),
            'from_name' => trim($_POST['from_name'] ?? '')
        ];

        // Si se envió un campo password en blanco, deducimos recuperar la del ENV (para no fallar el test localmente guardado)
        if (empty($config['pass'])) {
            $envVars = $this->envManager->getParsedVariables();
            $config['pass'] = $envVars['MAIL_PASS'] ?? '';
        }

        $mailService = new \App\Core\Services\MailService();
        $result = $mailService->testConnection($config);

        echo json_encode($result);
        exit;
    }
}
