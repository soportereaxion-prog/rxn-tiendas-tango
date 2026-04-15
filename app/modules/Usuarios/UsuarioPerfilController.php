<?php
declare(strict_types=1);

namespace App\Modules\Usuarios;

use App\Core\View;
use App\Core\Database;
use App\Core\Context;
use App\Core\Services\MailService;
use App\Shared\Services\OperationalAreaService;
use PDO;
use PHPMailer\PHPMailer\PHPMailer;

class UsuarioPerfilController
{
    public function index(): void
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $area = OperationalAreaService::resolveFromRequest();

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        // Cargar config SMTP de mail masivos del usuario (si existe)
        $smtpConfig = null;
        $empresaId = Context::getEmpresaId();
        if ($empresaId) {
            $stmtSmtp = $pdo->prepare(
                "SELECT * FROM crm_mail_smtp_configs
                 WHERE empresa_id = ? AND usuario_id = ? AND deleted_at IS NULL
                 LIMIT 1"
            );
            $stmtSmtp->execute([$empresaId, $_SESSION['user_id']]);
            $smtpConfig = $stmtSmtp->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        View::render('app/modules/Usuarios/views/mi_perfil.php', [
            'usuario' => $usuario,
            'smtpConfig' => $smtpConfig,
            'area' => $area,
            'dashboardPath' => OperationalAreaService::dashboardPath($area),
            'helpPath' => OperationalAreaService::helpPath($area),
            'formPath' => OperationalAreaService::profilePath($area),
        ]);
    }

    public function guardar(): void
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $area = OperationalAreaService::resolveFromRequest();

        $tema = $_POST['preferencia_tema'] ?? 'light';
        $fuente = $_POST['preferencia_fuente'] ?? 'md';
        $colorCalendario = $_POST['color_calendario'] ?? '#007bff';

        // Validar color hex
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $colorCalendario)) {
            $colorCalendario = '#007bff';
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE usuarios SET preferencia_tema = ?, preferencia_fuente = ?, color_calendario = ? WHERE id = ?");
        $stmt->execute([$tema, $fuente, $colorCalendario, $_SESSION['user_id']]);

        // Actualizar caché de sesión en vivo
        $_SESSION['pref_theme'] = $tema;
        $_SESSION['pref_font'] = $fuente;
        $_SESSION['color_calendario'] = $colorCalendario;

        // Persistir config SMTP para mail masivos (si vino en el form)
        $this->persistSmtpFromPost($pdo, (int) $_SESSION['user_id']);

        $redirect = OperationalAreaService::profilePath($area);
        $separator = str_contains($redirect, '?') ? '&' : '?';
        header('Location: ' . $redirect . $separator . 'success=Preferencias+actualizadas');
        exit;
    }

    /**
     * Upsert de la config SMTP para mail masivos del usuario en sesión.
     *
     * Se guarda el password en texto plano siguiendo el patrón actual del
     * proyecto (ver EmpresaConfig.smtp_pass). TODO: cuando se implemente un
     * servicio de cifrado general (App\Core\Crypto), migrar este campo.
     *
     * Si el form no tiene smtp_host, no se hace nada (evita blanquear config
     * existente cuando el usuario guarda solo sus preferencias visuales).
     */
    private function persistSmtpFromPost(PDO $pdo, int $usuarioId): void
    {
        $host = trim((string) ($_POST['smtp_host'] ?? ''));
        if ($host === '') {
            return;
        }

        $empresaId = Context::getEmpresaId();
        if (!$empresaId) {
            return;
        }

        $port = (int) ($_POST['smtp_port'] ?? 587);
        if ($port <= 0 || $port > 65535) {
            $port = 587;
        }

        $username = trim((string) ($_POST['smtp_username'] ?? ''));
        $encryption = in_array(($_POST['smtp_encryption'] ?? 'tls'), ['none','ssl','tls'], true)
            ? $_POST['smtp_encryption']
            : 'tls';
        $fromEmail = trim((string) ($_POST['smtp_from_email'] ?? ''));
        $fromName = trim((string) ($_POST['smtp_from_name'] ?? ''));
        $maxPerBatch = max(1, min(1000, (int) ($_POST['smtp_max_per_batch'] ?? 50)));
        $pauseSeconds = max(0, min(300, (int) ($_POST['smtp_pause_seconds'] ?? 5)));
        $activo = isset($_POST['smtp_activo']) ? 1 : 0;

        // Validar email "from" básico
        if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $fromEmail = $username;
        }

        // Buscar config existente
        $stmt = $pdo->prepare(
            "SELECT id, password_encrypted FROM crm_mail_smtp_configs
             WHERE empresa_id = ? AND usuario_id = ? AND deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([$empresaId, $usuarioId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si el campo password vino vacío y ya hay config, mantener la anterior
        $passwordInput = (string) ($_POST['smtp_password'] ?? '');
        $passwordToStore = $passwordInput !== ''
            ? $passwordInput
            : ($existing['password_encrypted'] ?? '');

        if ($existing) {
            $update = $pdo->prepare(
                "UPDATE crm_mail_smtp_configs SET
                    host = :host,
                    port = :port,
                    username = :username,
                    password_encrypted = :password,
                    encryption = :encryption,
                    from_email = :from_email,
                    from_name = :from_name,
                    max_per_batch = :max_per_batch,
                    pause_seconds = :pause_seconds,
                    activo = :activo
                 WHERE id = :id"
            );
            $update->execute([
                ':host' => $host,
                ':port' => $port,
                ':username' => $username,
                ':password' => $passwordToStore,
                ':encryption' => $encryption,
                ':from_email' => $fromEmail,
                ':from_name' => $fromName ?: null,
                ':max_per_batch' => $maxPerBatch,
                ':pause_seconds' => $pauseSeconds,
                ':activo' => $activo,
                ':id' => $existing['id'],
            ]);
            return;
        }

        $insert = $pdo->prepare(
            "INSERT INTO crm_mail_smtp_configs
                (empresa_id, usuario_id, nombre, host, port, username, password_encrypted,
                 encryption, from_email, from_name, max_per_batch, pause_seconds, activo)
             VALUES
                (:empresa_id, :usuario_id, 'SMTP Masivo', :host, :port, :username, :password,
                 :encryption, :from_email, :from_name, :max_per_batch, :pause_seconds, :activo)"
        );
        $insert->execute([
            ':empresa_id' => $empresaId,
            ':usuario_id' => $usuarioId,
            ':host' => $host,
            ':port' => $port,
            ':username' => $username,
            ':password' => $passwordToStore,
            ':encryption' => $encryption,
            ':from_email' => $fromEmail,
            ':from_name' => $fromName ?: null,
            ':max_per_batch' => $maxPerBatch,
            ':pause_seconds' => $pauseSeconds,
            ':activo' => $activo,
        ]);
    }

    /**
     * Endpoint AJAX: prueba de conexión SMTP para mail masivos.
     *
     * Recibe por POST los datos del form del perfil (o del JSON del fetch),
     * arma el array de config que entiende MailService::testConnection y
     * responde con JSON { success: bool, message: string }.
     *
     * Si el password viene vacío pero hay config existente, usa el guardado.
     */
    public function testSmtp(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (empty($_SESSION['user_id'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            exit;
        }

        $empresaId = Context::getEmpresaId();
        if (!$empresaId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Empresa no resuelta']);
            exit;
        }

        // Aceptar tanto form data como JSON body
        $input = $_POST;
        if (empty($input)) {
            $raw = file_get_contents('php://input');
            $decoded = $raw ? json_decode($raw, true) : null;
            if (is_array($decoded)) {
                $input = $decoded;
            }
        }

        $host = trim((string) ($input['smtp_host'] ?? ''));
        if ($host === '') {
            echo json_encode(['success' => false, 'message' => 'Falta el host SMTP']);
            exit;
        }

        // Fallback del password: si el form vino con vacío, buscar el guardado
        $password = (string) ($input['smtp_password'] ?? '');
        if ($password === '') {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare(
                "SELECT password_encrypted FROM crm_mail_smtp_configs
                 WHERE empresa_id = ? AND usuario_id = ? AND deleted_at IS NULL LIMIT 1"
            );
            $stmt->execute([$empresaId, $_SESSION['user_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $password = (string) $row['password_encrypted'];
            }
        }

        $config = [
            'host'   => $host,
            'port'   => (int) ($input['smtp_port'] ?? 587),
            'user'   => trim((string) ($input['smtp_username'] ?? '')),
            'pass'   => $password,
            'secure' => in_array(($input['smtp_encryption'] ?? 'tls'), ['none','ssl','tls'], true)
                        ? $input['smtp_encryption']
                        : 'tls',
            'from_email' => trim((string) ($input['smtp_from_email'] ?? '')),
            'from_name'  => trim((string) ($input['smtp_from_name'] ?? '')),
        ];

        $result = (new MailService())->testConnection($config);

        // Persistir resultado del test para mostrar en UI la próxima vez
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare(
                "UPDATE crm_mail_smtp_configs
                 SET last_test_at = NOW(),
                     last_test_status = :status,
                     last_test_error = :error
                 WHERE empresa_id = :empresa_id AND usuario_id = :usuario_id
                   AND deleted_at IS NULL"
            );
            $stmt->execute([
                ':status' => $result['success'] ? 'ok' : 'fail',
                ':error' => $result['success'] ? null : ($result['message'] ?? null),
                ':empresa_id' => $empresaId,
                ':usuario_id' => $_SESSION['user_id'],
            ]);
        } catch (\Throwable $e) {
            // No bloqueamos la respuesta del test si falla el log
            error_log('testSmtp: no se pudo persistir last_test_*: ' . $e->getMessage());
        }

        echo json_encode($result);
        exit;
    }

    public function guardarOrdenDashboard(): void
    {
        if (empty($_SESSION['user_id'])) {
            http_response_code(403);
            echo json_encode(['error' => 'No autorizado']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['order']) && is_array($input['order'])) {
            $area = strtolower(trim((string) ($input['area'] ?? 'tiendas')));
            $area = $area === 'crm' ? 'crm' : 'tiendas';

            $currentOrder = $_SESSION['dashboard_order'] ?? null;
            $decodedOrder = json_decode((string) $currentOrder, true);

            if (is_array($decodedOrder) && array_is_list($decodedOrder)) {
                $decodedOrder = ['tiendas' => $decodedOrder];
            }

            if (!is_array($decodedOrder)) {
                $decodedOrder = [];
            }

            $decodedOrder[$area] = array_values($input['order']);
            $jsonOrder = json_encode($decodedOrder);
            
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("UPDATE usuarios SET dashboard_order = ? WHERE id = ?");
            $stmt->execute([$jsonOrder, $_SESSION['user_id']]);
            
            $_SESSION['dashboard_order'] = $jsonOrder;
            
            echo json_encode(['success' => true]);
        }
        exit;
    }

    public function toggleTheme(): void
    {
        if (empty($_SESSION['user_id'])) {
            http_response_code(403);
            echo json_encode(['error' => 'No autorizado']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $newTheme = $input['theme'] ?? 'light';
        if (!in_array($newTheme, ['light', 'dark'])) {
            $newTheme = 'light';
        }

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("UPDATE usuarios SET preferencia_tema = ? WHERE id = ?");
            $stmt->execute([$newTheme, $_SESSION['user_id']]);
            $_SESSION['pref_theme'] = $newTheme;
            echo json_encode(['success' => true, 'theme' => $newTheme]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error de BD']);
        }
        exit;
    }
}
