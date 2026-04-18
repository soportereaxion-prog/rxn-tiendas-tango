<?php

declare(strict_types=1);

namespace App\Modules\ClientesWeb\Services;

use App\Modules\ClientesWeb\ClienteWebRepository;
use App\Modules\Store\Context\ClienteWebContext;
use App\Core\Database;
use Exception;

class ClienteWebAuthService
{
    private ClienteWebRepository $repo;

    public function __construct()
    {
        $this->repo = new ClienteWebRepository();
    }

    /**
     * Intenta loguearse. Si el correo y pass coinciden con una cuenta validada,
     * setea el scope del cliente. Retorna boolean.
     */
    public function login(int $empresaId, string $email, string $password): bool
    {
        $pdo = Database::getConnection();
        // Buscamos exacto por empresa
        $stmt = $pdo->prepare("SELECT id, password_hash, nombre, apellido, email, activo, email_verificado FROM clientes_web WHERE empresa_id = :emp_id AND email = :email LIMIT 1");
        $stmt->execute(['emp_id' => $empresaId, 'email' => $email]);
        $row = $stmt->fetch();

        if ($row && !empty($row['password_hash']) && (int)$row['activo'] === 1) {
            if (password_verify($password, $row['password_hash'])) {
                if ((int)$row['email_verificado'] !== 1) {
                    throw new Exception("Cuenta pendiente de verificación. Buscá el enlace en tu correo electrónico.");
                }
                ClienteWebContext::login((int)$row['id'], $empresaId, [
                    'nombre' => $row['nombre'],
                    'apellido' => $row['apellido'],
                    'email' => $row['email']
                ]);
                return true;
            }
        }
        return false;
    }

    public function logout(): void
    {
        ClienteWebContext::logout();
    }

    /**
     * Registrar un nuevo cliente desde el Store Público.
     * Si el email ya existía como "Guest" (password nula), lo promueve a "Registrado".
     * Si el email ya existía con password, arroja excepción (Email ya en uso).
     */
    public function register(int $empresaId, array $data, string $password): int
    {
        $email = trim($data['email']);
        $pdo = Database::getConnection();
        
        $stmt = $pdo->prepare("SELECT id, password_hash FROM clientes_web WHERE empresa_id = :emp_id AND email = :email LIMIT 1");
        $stmt->execute(['emp_id' => $empresaId, 'email' => $email]);
        $existente = $stmt->fetch();

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $tokenStr = bin2hex(random_bytes(16));
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

        if ($existente) {
            if (!empty($existente['password_hash'])) {
                throw new Exception("El email introducido ya posee una cuenta registrada.");
            } else {
                // Es un GUEST. Lo actualizamos con la password y los nuevos datos provistos en formulario.
                $this->repo->updateIfChanged((int)$existente['id'], $data);
                
                $stmtUpd = $pdo->prepare("UPDATE clientes_web SET password_hash = :hash, verification_token = :vtok, verification_expires = :vexp, email_verificado = 0 WHERE id = :id");
                $stmtUpd->execute(['hash' => $hash, 'vtok' => $tokenStr, 'vexp' => $expires, 'id' => (int)$existente['id']]);
                
                // Mail Notification
                $mailService = new \App\Core\Services\MailService();
                $mailService->sendVerificationEmail($email, $data['nombre'] ?? 'Cliente', $tokenStr, $empresaId);

                return (int)$existente['id'];
            }
        }

        // Si no existe, creamos de cero.
        $data['empresa_id'] = $empresaId;
        $data['documento'] = $data['documento'] ?? null; // Mantener default
        $clienteId = $this->repo->create($data);

        $stmtUpd = $pdo->prepare("UPDATE clientes_web SET password_hash = :hash, verification_token = :vtok, verification_expires = :vexp, email_verificado = 0 WHERE id = :id");
        $stmtUpd->execute(['hash' => $hash, 'vtok' => $tokenStr, 'vexp' => $expires, 'id' => $clienteId]);

        // Mail Notification
        $mailService = new \App\Core\Services\MailService();
        $mailService->sendVerificationEmail($email, $data['nombre'] ?? 'Cliente', $tokenStr, $empresaId);

        return $clienteId;
    }

    /**
     * Mecanismo base para recuperación de contraseña solicitado por Arquitectura.
     * Genera un PIN/Token temporal, lo persiste y dispara el MailService usando el fallback nativo.
     */
    public function requestPasswordReset(int $empresaId, string $email): bool
    {
        $pdo = Database::getConnection();
        
        $stmt = $pdo->prepare("SELECT id, nombre FROM clientes_web WHERE empresa_id = :emp_id AND email = :email AND activo = 1 LIMIT 1");
        $stmt->execute(['emp_id' => $empresaId, 'email' => $email]);
        $cliente = $stmt->fetch();

        if (!$cliente) {
            // Falla silenciosa por seguridad para no revelar si el mail existe.
            return false;
        }

        // Token de 32 hex en vez del PIN de 6 dígitos (brute-force inviable con rate limit + keyspace grande).
        $tokenStr = bin2hex(random_bytes(16));
        $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        $stmtUpd = $pdo->prepare("UPDATE clientes_web SET reset_token = :token, reset_expires = :expires WHERE id = :id AND empresa_id = :empresa_id");
        $stmtUpd->execute([
            'token' => $tokenStr,
            'expires' => $expires,
            'id' => (int)$cliente['id'],
            'empresa_id' => $empresaId,
        ]);

        $mailService = new \App\Core\Services\MailService();
        $mailService->sendPasswordReset($email, $cliente['nombre'], $tokenStr, $empresaId);

        return true;
    }
}
