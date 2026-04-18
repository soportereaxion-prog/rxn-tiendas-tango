<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Core\Controller;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\View;
use PDO;
use Exception;

class PasswordResetController extends Controller
{
    /**
     * Pantalla de Ingreso de Email (Universal B2C/B2B)
     */
    public function showForgot(): void
    {
        View::render('app/modules/Auth/views/forgot.php', []);
    }

    /**
     * Procesa la solicitud de recuperación disparando el Token.
     */
    public function processForgot(): void
    {
        $this->verifyCsrfOrAbort();

        $email = trim($_POST['email'] ?? '');
        if (empty($email)) {
            View::render('app/modules/Auth/views/forgot.php', ['error' => 'Por favor, ingrese un e-mail válido.']);
            return;
        }

        // Throttle: 3 solicitudes cada 15 min por email+IP — mitiga brute-force y abuso del envío de mail.
        $rateKey = RateLimiter::clientKey('forgot', $email);
        if (!RateLimiter::attempt($rateKey, 3, 900)) {
            // Respuesta genérica idéntica al caso exitoso — mantiene fallo silencioso.
            View::render('app/modules/Auth/views/forgot.php', ['success' => 'Si el e-mail está registrado, recibirás un enlace de recuperación en breve.']);
            return;
        }

        $pdo = Database::getConnection();

        // El sistema es ciego para el usuario. Siempre dice "Si el email existe te llegará un correo." para evitar enumeración.
        $this->triggerResetForTable($pdo, 'clientes_web', $email);
        $this->triggerResetForTable($pdo, 'usuarios', $email);

        View::render('app/modules/Auth/views/forgot.php', ['success' => 'Si el e-mail está registrado, recibirás un enlace de recuperación en breve.']);
    }

    private function triggerResetForTable(PDO $pdo, string $table, string $email): void
    {
        $stmt = $pdo->prepare("SELECT id, nombre, empresa_id FROM {$table} WHERE email = :email AND activo = 1 LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            $tokenStr = bin2hex(random_bytes(16));
            $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));

            $stmtUpd = $pdo->prepare("UPDATE {$table} SET reset_token = :token, reset_expires = :expires WHERE id = :id");
            $stmtUpd->execute([
                'token' => $tokenStr,
                'expires' => $expires,
                'id' => (int)$user['id']
            ]);

            $mailService = new \App\Core\Services\MailService();
            $mailService->sendPasswordReset($email, $user['nombre'], $tokenStr, (int)$user['empresa_id']);
        }
    }

    /**
     * Despliega la UI de Nueva Contraseña (validando por GET_TOKEN)
     */
    public function showReset(): void
    {
        $token = $_GET['token'] ?? '';
        if (empty($token) || strlen($token) !== 32) {
            $this->renderGenericFatal('Token ausente o corrupto. Por favor, solicitá un nuevo enlace.');
            return;
        }

        $pdo = Database::getConnection();
        $user = $this->findUserByResetToken($pdo, $token);

        if (!$user) {
            $this->renderGenericFatal('El enlace expiró o ya fue utilizado. Solicitá uno nuevo.');
            return;
        }

        View::render('app/modules/Auth/views/reset.php', ['token' => $token]);
    }

    /**
     * Impacta la nueva Pass si el Token sirve
     */
    public function processReset(): void
    {
        $this->verifyCsrfOrAbort();

        $token = $_POST['token'] ?? '';
        $pass1 = $_POST['password'] ?? '';
        $pass2 = $_POST['password_confirmation'] ?? '';

        if (empty($token)) {
            $this->renderGenericFatal('Operación inválida.');
            return;
        }

        if (empty($pass1) || $pass1 !== $pass2) {
            View::render('app/modules/Auth/views/reset.php', ['token' => $token, 'error' => 'Las contraseñas no coinciden o están vacías.']);
            return;
        }

        $pdo = Database::getConnection();
        $user = $this->findUserByResetToken($pdo, $token);

        if (!$user) {
            $this->renderGenericFatal('Enlace de recuperación caducado. Volvé a pedir el reseteo.');
            return;
        }

        $hash = password_hash($pass1, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE {$user['table_name']} SET password_hash = :hash, reset_token = NULL, reset_expires = NULL WHERE id = :id");
        $stmt->execute([
            'hash' => $hash,
            'id' => (int)$user['id']
        ]);

        $redirect = $user['table_name'] === 'usuarios' ? '/login' : '/';
        header("Location: {$redirect}?msg=pass_actualizada");
        exit;
    }

    private function findUserByResetToken(PDO $pdo, string $token): ?array
    {
        // Revisar B2C
        $stmtC = $pdo->prepare("SELECT id, reset_expires, 'clientes_web' as table_name FROM clientes_web WHERE reset_token = :token LIMIT 1");
        $stmtC->execute(['token' => $token]);
        $row = $stmtC->fetch();
        if ($row && time() <= strtotime($row['reset_expires'])) return $row;

        // Revisar B2B
        $stmtU = $pdo->prepare("SELECT id, reset_expires, 'usuarios' as table_name FROM usuarios WHERE reset_token = :token LIMIT 1");
        $stmtU->execute(['token' => $token]);
        $row2 = $stmtU->fetch();
        if ($row2 && time() <= strtotime($row2['reset_expires'])) return $row2;

        return null;
    }

    private function renderGenericFatal(string $msg): void
    {
        http_response_code(400);
        echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><title>Error</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
        </head><body class='bg-light d-flex align-items-center justify-content-center' style='height: 100vh;'>
        <div class='card shadow-sm p-4' style='max-width: 500px;'>
            <h3 class='text-danger'>❌ Error de Recuperación</h3>
            <p class='text-secondary mt-3'>$msg</p>
            <a href='/auth/forgot' class='btn btn-outline-primary mt-3'>Intentar nuevamente</a>
        </div>
        </body></html>";
    }
}
