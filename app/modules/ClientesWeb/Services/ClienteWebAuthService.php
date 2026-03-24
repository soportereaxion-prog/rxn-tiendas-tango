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
        $stmt = $pdo->prepare("SELECT id, password_hash, nombre, apellido, email, activo FROM clientes_web WHERE empresa_id = :emp_id AND email = :email LIMIT 1");
        $stmt->execute(['emp_id' => $empresaId, 'email' => $email]);
        $row = $stmt->fetch();

        if ($row && !empty($row['password_hash']) && (int)$row['activo'] === 1) {
            if (password_verify($password, $row['password_hash'])) {
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

        if ($existente) {
            if (!empty($existente['password_hash'])) {
                throw new Exception("El email introducido ya posee una cuenta registrada.");
            } else {
                // Es un GUEST. Lo actualizamos con la password y los nuevos datos provistos en formulario.
                $this->repo->updateIfChanged((int)$existente['id'], $data);
                
                $stmtUpd = $pdo->prepare("UPDATE clientes_web SET password_hash = :hash WHERE id = :id");
                $stmtUpd->execute(['hash' => $hash, 'id' => (int)$existente['id']]);
                
                // Mail Notification
                $mailService = new \App\Core\Services\MailService();
                $mailService->sendWelcomeEmail($email, $data['nombre'] ?? 'Cliente', $empresaId);

                // Force Auth
                ClienteWebContext::login((int)$existente['id'], $empresaId, $data);
                return (int)$existente['id'];
            }
        }

        // Si no existe, creamos de cero.
        $data['empresa_id'] = $empresaId;
        $data['documento'] = $data['documento'] ?? null; // Mantener default
        $clienteId = $this->repo->create($data);

        $stmtUpd = $pdo->prepare("UPDATE clientes_web SET password_hash = :hash WHERE id = :id");
        $stmtUpd->execute(['hash' => $hash, 'id' => $clienteId]);

        // Mail Notification
        $mailService = new \App\Core\Services\MailService();
        $mailService->sendWelcomeEmail($email, $data['nombre'] ?? 'Cliente', $empresaId);

        ClienteWebContext::login($clienteId, $empresaId, $data);
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

        $tokenStr = random_int(100000, 999999);
        $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        $stmtUpd = $pdo->prepare("UPDATE clientes_web SET reset_token = :token, reset_expires = :expires WHERE id = :id");
        $stmtUpd->execute([
            'token' => (string)$tokenStr,
            'expires' => $expires,
            'id' => (int)$cliente['id']
        ]);

        $mailService = new \App\Core\Services\MailService();
        $mailService->sendPasswordReset($email, $cliente['nombre'], (string)$tokenStr, $empresaId);

        return true;
    }
}
