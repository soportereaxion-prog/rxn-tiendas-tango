<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Core\Database;
use PDO;

class UsuarioRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findByEmail(string $email, ?int $excludeId = null): ?Usuario
    {
        $sql = "SELECT * FROM usuarios WHERE email = :email";
        $params = [':email' => $email];
        
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $user = $stmt->fetchObject(Usuario::class);
        return $user ?: null;
    }

    public function findAllByEmpresaId(int $empresaId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM usuarios WHERE empresa_id = :empresa_id ORDER BY id DESC");
        $stmt->execute([':empresa_id' => $empresaId]);
        return $stmt->fetchAll(PDO::FETCH_CLASS, Usuario::class);
    }

    public function findByIdAndEmpresaId(int $id, int $empresaId): ?Usuario
    {
        $stmt = $this->db->prepare("SELECT * FROM usuarios WHERE id = :id AND empresa_id = :empresa_id");
        $stmt->execute([':id' => $id, ':empresa_id' => $empresaId]);
        $user = $stmt->fetchObject(Usuario::class);
        return $user ?: null;
    }

    public function save(Usuario $usuario): void
    {
        if ($usuario->id) {
            $sql = "UPDATE usuarios SET 
                    nombre = :nombre,
                    email = :email,
                    password_hash = :password_hash,
                    activo = :activo,
                    es_admin = :es_admin
                    WHERE id = :id AND empresa_id = :empresa_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':nombre' => $usuario->nombre,
                ':email' => $usuario->email,
                ':password_hash' => $usuario->password_hash,
                ':activo' => $usuario->activo,
                ':es_admin' => $usuario->es_admin,
                ':id' => $usuario->id,
                ':empresa_id' => $usuario->empresa_id
            ]);
        } else {
            $sql = "INSERT INTO usuarios (empresa_id, nombre, email, password_hash, activo, es_admin, email_verificado, verification_token, verification_expires) 
                    VALUES (:empresa_id, :nombre, :email, :password_hash, :activo, :es_admin, :email_verificado, :verification_token, :verification_expires)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':empresa_id' => $usuario->empresa_id,
                ':nombre' => $usuario->nombre,
                ':email' => $usuario->email,
                ':password_hash' => $usuario->password_hash,
                ':activo' => $usuario->activo,
                ':es_admin' => $usuario->es_admin,
                ':email_verificado' => current([$usuario->email_verificado ?? 0]),
                ':verification_token' => $usuario->verification_token ?? null,
                ':verification_expires' => $usuario->verification_expires ?? null
            ]);
            $usuario->id = (int) $this->db->lastInsertId();
        }
    }
}
