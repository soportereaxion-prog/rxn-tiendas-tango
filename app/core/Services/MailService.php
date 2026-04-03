<?php

declare(strict_types=1);

namespace App\Core\Services;

use App\Modules\EmpresaConfig\EmpresaConfigRepository;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Exception;

class MailService
{
    private EmpresaConfigRepository $configRepo;

    public function __construct(?EmpresaConfigRepository $configRepo = null)
    {
        $this->configRepo = $configRepo ?? new EmpresaConfigRepository();
    }

    public static function forCrm(): self
    {
        return new self(EmpresaConfigRepository::forCrm());
    }

    public static function forArea(string $area): self
    {
        return new self(EmpresaConfigRepository::forArea($area));
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
            'host' => getenv('MAIL_HOST') ?: '',
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

    /**
     * Instancia un cliente SMTP puro con PHPMailer
     */
    private function buildMailer(array $config): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = !empty($config['user']);
        
        if ($mail->SMTPAuth) {
            $mail->Username = $config['user'];
            $mail->Password = $config['pass'];
        }

        if (!empty($config['secure'])) {
            $mail->SMTPSecure = ($config['secure'] === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPAutoTLS = false;
        }

        $mail->Port = $config['port'];
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        return $mail;
    }

    public function send(string $to, string $subject, string $body, int $empresaId, array $attachments = []): bool
    {
        $config = $this->resolveMailerConfig($empresaId);
        
        if (empty($config['host'])) {
            error_log("MailService: Falló resolución SMTP para enviar a $to. Default Host missing.");
            return false;
        }

        try {
            $mail = $this->buildMailer($config);
            $mail->setFrom($config['from_email'], $config['from_name']);
            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags($body);

            foreach ($attachments as $att) {
                if (is_string($att) && file_exists($att)) {
                    $mail->addAttachment($att);
                } elseif (is_array($att) && isset($att['path'])) {
                    $name = $att['filename'] ?? $att['name'] ?? '';
                    $mail->addAttachment($att['path'], $name);
                } elseif (is_array($att) && isset($att['content'], $att['name'])) {
                    $mail->addStringAttachment(
                        $att['content'],
                        $att['name'],
                        $att['encoding'] ?? \PHPMailer\PHPMailer\PHPMailer::ENCODING_BASE64,
                        $att['type'] ?? '',
                        $att['disposition'] ?? 'attachment'
                    );
                }
            }

            $mail->send();
            return true;
        } catch (PHPMailerException $e) {
            error_log("PHPMailer Exception al enviar a {$to}: " . $e->getMessage() . " | Error Info: " . $mail->ErrorInfo);
            return false;
        } catch (Exception $e) {
            error_log("MailService Exception: " . $e->getMessage());
            return false;
        }
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
        // Enlaza a un endpoint universal sin depender del slug del tenant.
        $link = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http') . '://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost') . "/auth/verify?token=" . urlencode($token);

        $subject = "Verificá tu correo electrónico";
        $body = "
            <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                <h2 style='color: #2c3e50;'>Validación de Seguridad</h2>
                <p>Hola $nombre,</p>
                <p>Tu cuenta ha sido creada. Para poder iniciar sesión y utilizar la plataforma, es obligatorio verificar tu dirección de correo electrónico haciendo click en el siguiente botón:</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$link' style='background: #0d6efd; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px; display: inline-block;'>✅ Verificar Mi Cuenta</a>
                </div>
                <p style='font-size: 13px; color: #666;'>Si el botón no funciona, copiá y pegá este enlace en tu navegador:<br><a href='$link'>$link</a></p>
                <hr style='border: none; border-top: 1px solid #eaeaea; margin: 20px 0;'>
                <p style='font-size: 12px; color: #888;'>Este enlace expirará en 24 horas.</p>
            </div>
        ";
        return $this->send($to, $subject, $body, $empresaId);
    }
    
    public function sendPasswordReset(string $to, string $nombre, string $token, int $empresaId): bool
    {
        $link = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http') . '://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost') . "/auth/reset?token=" . urlencode($token);

        $subject = "Recuperación de Contraseña solicitada";
        $body = "
            <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                <h2 style='color: #2c3e50;'>Recuperación de Acceso</h2>
                <p>Hola $nombre,</p>
                <p>Solicitaste restablecer tu clave. Hacé click en el botón a continuación para ingresar una nueva contraseña segura:</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$link' style='background: #dc3545; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px; display: inline-block;'>🔐 Restablecer mi Contraseña</a>
                </div>
                <p style='font-size: 13px; color: #666;'>Si el botón no funciona, copiá este enlace:<br><a href='$link'>$link</a></p>
                <p>Si no fuiste vos, ignorá este mensaje.</p>
                <hr style='border: none; border-top: 1px solid #eaeaea; margin: 20px 0;'>
                <p style='font-size: 12px; color: #888;'>Este enlace expirará en 30 minutos por razones de seguridad.</p>
            </div>
        ";
        return $this->send($to, $subject, $body, $empresaId);
    }
    
    /**
     * Validador en vivo para testing AJAX de configuración SMTP usando PHPMailer.
     */
    public function testConnection(array $config): array
    {
        if (empty($config['host'])) {
            return ['success' => false, 'message' => 'El servidor / host está vacío.'];
        }

        try {
            $mail = clone $this->buildMailer($config);
            
            // Interceptar la salida del Sockets Handshake Debug de PHPMailer
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $debugOutput = '';
            $mail->Debugoutput = function($str, $level) use (&$debugOutput) {
                $debugOutput .= $str . "\n";
            };
            
            // Requerimos Timeout menor visual para UX
            $mail->Timeout = 5;

            if ($mail->smtpConnect()) {
                $mail->smtpClose();
                return ['success' => true, 'message' => '¡Conexión SMTP validadas exitosamente por PHPMailer!'];
            } else {
                return [
                    'success' => false, 
                    'message' => "La conexión fue rechazada o falló el handshake.\n\nDebug Info:\n" . trim($debugOutput)
                ];
            }
        } catch (PHPMailerException $e) {
            return [
                'success' => false, 
                'message' => "Excepción PHPMailer durante el Handshake SMTP:\n" . $e->getMessage() . "\n\nDebug Info:\n" . trim($debugOutput ?? '')
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => "Excepción de Socket General: " . $e->getMessage()];
        }
    }
}
