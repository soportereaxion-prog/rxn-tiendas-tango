<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Core\Controller;
use App\Core\Database;
use App\Core\View;
use PDO;

class VerificationController extends Controller
{
    /**
     * Endpoint universal para ingestar tokens de activación.
     * Escanea simultáneamente la bóveda de "clientes_web" y "usuarios" admin.
     */
    public function verify(): void
    {
        $token = $_GET['token'] ?? '';
        if (empty($token) || strlen($token) !== 32) {
            $this->renderError("Enlace inválido o corrupto.", "Asegurate de haber copiado el enlace completo desde tu correo.");
            return;
        }

        $pdo = Database::getConnection();

        // 1. Check Store Clients (B2C)
        $stmtC = $pdo->prepare("SELECT id, empresa_id, verification_expires, email_verificado FROM clientes_web WHERE verification_token = :token LIMIT 1");
        $stmtC->execute(['token' => $token]);
        $cliente = $stmtC->fetch();

        if ($cliente) {
            $this->processVerification($pdo, 'clientes_web', $cliente, $token);
            // Redirigir al store
            $slug = $this->getEmpresaSlug((int)$cliente['empresa_id'], $pdo);
            header("Location: /{$slug}/login?msg=cuenta_verificada");
            exit;
        }

        // 2. Check Admin Users (B2B)
        $stmtU = $pdo->prepare("SELECT id, empresa_id, verification_expires, email_verificado FROM usuarios WHERE verification_token = :token LIMIT 1");
        $stmtU->execute(['token' => $token]);
        $usuario = $stmtU->fetch();

        if ($usuario) {
            $this->processVerification($pdo, 'usuarios', $usuario, $token);
            // Redirigir al backoffice admin
            header("Location: /login?msg=cuenta_verificada");
            exit;
        }

        $this->renderError("Enlace expirado o inexistente.", "El token provisto no coincide con ninguna cuenta pendiente de activación.");
    }

    private function processVerification(PDO $pdo, string $table, array $row, string $token): void
    {
        if ((int)$row['email_verificado'] === 1) {
            return; // Ya estaba verificadx
        }

        $expires = strtotime($row['verification_expires']);
        if (time() > $expires) {
            $this->renderError("Enlace Caducado", "Tu enlace de verificación superó las 24 horas estipuladas. Solicitá uno nuevo desde la pantalla de Login.");
            exit;
        }

        $stmt = $pdo->prepare("UPDATE {$table} SET email_verificado = 1, email_verificado_at = NOW(), verification_token = NULL, verification_expires = NULL WHERE id = :id");
        $stmt->execute(['id' => (int)$row['id']]);
    }

    public function showResend(): void
    {
        View::render('app/modules/Auth/views/resend.php', []);
    }

    public function processResend(): void
    {
        $email = trim($_POST['email'] ?? '');
        if (empty($email)) {
            View::render('app/modules/Auth/views/resend.php', ['error' => 'Por favor, ingresá un e-mail válido.']);
            return;
        }

        $pdo = Database::getConnection();
        
        $this->resendForTable($pdo, 'clientes_web', $email);
        $this->resendForTable($pdo, 'usuarios', $email);

        View::render('app/modules/Auth/views/resend.php', ['success' => 'Si tu cuenta existe y aún no estaba verificada, te enviamos un nuevo enlace. Revise su bandeja y Spam.']);
    }

    private function resendForTable(PDO $pdo, string $table, string $email): void
    {
        $stmt = $pdo->prepare("SELECT id, nombre, empresa_id FROM {$table} WHERE email = :email AND activo = 1 AND email_verificado = 0 LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            $tokenStr = bin2hex(random_bytes(16));
            $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            $stmtUpd = $pdo->prepare("UPDATE {$table} SET verification_token = :token, verification_expires = :expires WHERE id = :id");
            $stmtUpd->execute(['token' => $tokenStr, 'expires' => $expires, 'id' => (int)$user['id']]);

            $mailService = new \App\Core\Services\MailService();
            $mailService->sendVerificationEmail($email, $user['nombre'], $tokenStr, (int)$user['empresa_id']);
        }
    }

    private function getEmpresaSlug(int $empresaId, PDO $pdo): string
    {
        $stmt = $pdo->prepare("SELECT slug FROM empresas WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $empresaId]);
        $slug = $stmt->fetchColumn();
        return $slug ?: '';
    }

    private function renderError(string $title, string $message): void
    {
        http_response_code(400);
        echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><title>$title</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
        </head><body class='bg-light d-flex align-items-center justify-content-center' style='height: 100vh;'>
        <div class='card shadow-sm p-4' style='max-width: 500px;'>
            <h3 class='text-danger'>❌ $title</h3>
            <p class='text-secondary mt-3'>$message</p>
            <a href='/' class='btn btn-outline-secondary mt-3'>Volver al Inicio</a>
        </div>
        </body></html>";
    }
}
