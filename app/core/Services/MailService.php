<?php

declare(strict_types=1);

namespace App\Core\Services;

use App\Modules\EmpresaConfig\EmpresaConfigRepository;
use Exception;

class MailService
{
    private EmpresaConfigRepository $configRepo;

    public function __construct()
    {
        $this->configRepo = new EmpresaConfigRepository();
    }

    /**
     * Resuelve qué credenciales SMTP usar.
     * Si la empresa configuró SMTP propio y lo tiene activo, se usa.
     * De lo contrario, se triggerea el fallback global de RXN desde .env.
     */
    private function resolveMailerConfig(int $empresaId): array
    {
        $config = $this->configRepo->findByEmpresaId($empresaId);

        // Fallback global RXN predeterminado
        $smtp = [
            'host' => getenv('MAIL_HOST') ?: '127.0.0.1',
            'port' => getenv('MAIL_PORT') ? (int)getenv('MAIL_PORT') : 587,
            'user' => getenv('MAIL_USER') ?: '',
            'pass' => getenv('MAIL_PASS') ?: '',
            'secure' => getenv('MAIL_SECURE') ?: 'tls',
            'from_email' => getenv('MAIL_FROM_ADDRESS') ?: 'no-reply@rxntiendas.com',
            'from_name' => getenv('MAIL_FROM_NAME') ?: 'RXN Tiendas',
            'source' => 'Global RXN Fallback'
        ];

        // Override si la empresa tiene SMTP propio validado
        if ($config && $config->usa_smtp_propio === 1 && !empty($config->smtp_host) && !empty($config->smtp_user)) {
            $smtp['host'] = $config->smtp_host;
            $smtp['port'] = $config->smtp_port ?: 587;
            $smtp['user'] = $config->smtp_user;
            $smtp['pass'] = $config->smtp_pass;
            $smtp['secure'] = $config->smtp_secure;
            $smtp['from_email'] = $config->smtp_from_email ?: $smtp['from_email'];
            $smtp['from_name'] = $config->smtp_from_name ?: $config->nombre_fantasia;
            $smtp['source'] = 'SMTP Propio Empresa #' . $empresaId;
        }

        return $smtp;
    }

    public function send(string $to, string $subject, string $body, int $empresaId): bool
    {
        $config = $this->resolveMailerConfig($empresaId);
        
        // Evitamos enviar si no hay validaciones básicas completas
        if (empty($config['host'])) {
            error_log("MailService: Falló resolución SMTP para enviar a $to. Default Host missing.");
            return false;
        }

        try {
            return $this->sendViaSocket($to, $subject, $body, $config);
        } catch (Exception $e) {
            error_log("MailService Exception: " . $e->getMessage());
            // Si estuviéramos en Local / Dev, no queremos romper el flujo del cliente, simulamos boolean return
            return false;
        }
    }

    /**
     * Cliente robusto Vanilla PHP para SMTP Transaccional
     */
    private function sendViaSocket(string $to, string $subject, string $body, array $config): bool
    {
        $crlf = "\r\n";
        $host = $config['host'];
        $port = $config['port'];
        
        // Si es SSL implícito
        if ($config['secure'] === 'ssl') {
            $host = 'ssl://' . $host;
        }

        $socket = @fsockopen($host, $port, $errno, $errstr, 10);
        if (!$socket) {
            error_log("SMTP Error: No se pudo conectar a {$config['host']}:{$config['port']} - $errstr");
            return false;
        }

        $this->readSmtpResponse($socket);

        fwrite($socket, "EHLO " . (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost') . $crlf);
        $this->readSmtpResponse($socket);

        if ($config['secure'] === 'tls') {
            fwrite($socket, "STARTTLS" . $crlf);
            $this->readSmtpResponse($socket);
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            
            // Re-EHLO after TLS handshake
            fwrite($socket, "EHLO " . (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost') . $crlf);
            $this->readSmtpResponse($socket);
        }

        if (!empty($config['user'])) {
            fwrite($socket, "AUTH LOGIN" . $crlf);
            $this->readSmtpResponse($socket);
            fwrite($socket, base64_encode($config['user']) . $crlf);
            $this->readSmtpResponse($socket);
            fwrite($socket, base64_encode($config['pass']) . $crlf);
            $res = $this->readSmtpResponse($socket);
            if (strpos($res, '235') === false) {
                error_log("SMTP Auth Error: $res");
                fclose($socket);
                return false;
            }
        }

        fwrite($socket, "MAIL FROM: <{$config['from_email']}>" . $crlf);
        $this->readSmtpResponse($socket);

        fwrite($socket, "RCPT TO: <{$to}>" . $crlf);
        $this->readSmtpResponse($socket);

        fwrite($socket, "DATA" . $crlf);
        $this->readSmtpResponse($socket);

        $headers = "From: =?UTF-8?B?" . base64_encode((string)$config['from_name']) . "?= <{$config['from_email']}>" . $crlf;
        $headers .= "To: <{$to}>" . $crlf;
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=" . $crlf;
        $headers .= "MIME-Version: 1.0" . $crlf;
        $headers .= "Content-Type: text/html; charset=UTF-8" . $crlf;
        
        $message = $headers . $crlf . $body . $crlf . "." . $crlf;
        fwrite($socket, $message);
        $res = $this->readSmtpResponse($socket);

        fwrite($socket, "QUIT" . $crlf);
        fclose($socket);

        return strpos($res, '250') !== false;
    }

    private function readSmtpResponse($socket): string
    {
        $data = '';
        while ($str = fgets($socket, 515)) {
            $data .= $str;
            if (substr($str, 3, 1) === ' ') {
                break;
            }
        }
        return $data;
    }

    // ============================================
    // CASOS DE USO INTERNOS E INTEGRACIONES
    // ============================================

    public function sendWelcomeEmail(string $to, string $nombre, int $empresaId): bool
    {
        $subject = "¡Bienvenido a tu cuenta!";
        $body = "
            <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                <h2 style='color: #2c3e50;'>¡Hola $nombre! 👋</h2>
                <p>Tu cuenta ha sido creada con éxito. Ya podés revisar tu historial de pedidos interactuando en la plataforma.</p>
                <hr style='border: none; border-top: 1px solid #eaeaea; margin: 20px 0;'>
                <p style='font-size: 12px; color: #888;'>Este es un e-mail automático. Por favor, no respondas a esta dirección.</p>
            </div>
        ";
        return $this->send($to, $subject, $body, $empresaId);
    }

    public function sendVerificationEmail(string $to, string $nombre, string $token, int $empresaId): bool
    {
        $subject = "Verificá tu correo electrónico";
        $body = "
            <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                <h2 style='color: #2c3e50;'>Validación de Seguridad</h2>
                <p>Hola $nombre,</p>
                <p>Ingresa este código de 6 dígitos en la aplicación para validar tu cuenta:</p>
                <div style='background: #f8f9fa; padding: 15px; font-size: 24px; font-weight: bold; text-align: center; letter-spacing: 5px; border-radius: 8px;'>$token</div>
            </div>
        ";
        return $this->send($to, $subject, $body, $empresaId);
    }
    
    public function sendPasswordReset(string $to, string $nombre, string $token, int $empresaId): bool
    {
        $subject = "Recuperación de Contraseña solicitada";
        $body = "
            <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                <h2 style='color: #2c3e50;'>Recuperación de Acceso</h2>
                <p>Hola $nombre,</p>
                <p>Solicitaste restablecer tu clave. Tu token de reinicio temporal es:</p>
                <div style='background: #f8f9fa; padding: 15px; font-size: 24px; font-weight: bold; text-align: center; letter-spacing: 5px; color: #dc3545; border-radius: 8px;'>$token</div>
                <p>Si no fuiste vos, ignorá este mensaje.</p>
            </div>
        ";
        return $this->send($to, $subject, $body, $empresaId);
    }
}
