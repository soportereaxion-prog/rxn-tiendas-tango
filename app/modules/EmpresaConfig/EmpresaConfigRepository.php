<?php

declare(strict_types=1);

namespace App\Modules\EmpresaConfig;

use App\Core\Database;
use PDO;

class EmpresaConfigRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findByEmpresaId(int $empresaId): ?EmpresaConfig
    {
        $stmt = $this->db->prepare("SELECT * FROM empresa_config WHERE empresa_id = :empresa_id");
        $stmt->execute([':empresa_id' => $empresaId]);
        $config = $stmt->fetchObject(EmpresaConfig::class);
        return $config ?: null;
    }

    public function save(EmpresaConfig $config): void
    {
        if ($config->id) {
            $sql = "UPDATE empresa_config SET 
                    nombre_fantasia = :nombre, 
                    email_contacto = :email, 
                    telefono = :telefono,
                    tango_api_url = :tango_api_url,
                    tango_connect_key = :tango_connect_key,
                    tango_connect_token = :tango_connect_token,
                    tango_connect_company_id = :tango_connect_company_id
                    WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':nombre' => $config->nombre_fantasia,
                ':email' => $config->email_contacto,
                ':telefono' => $config->telefono,
                ':tango_api_url' => $config->tango_api_url,
                ':tango_connect_key' => $config->tango_connect_key,
                ':tango_connect_token' => $config->tango_connect_token,
                ':tango_connect_company_id' => $config->tango_connect_company_id,
                ':id' => $config->id,
            ]);
        } else {
            $sql = "INSERT INTO empresa_config (empresa_id, nombre_fantasia, email_contacto, telefono, tango_api_url, tango_connect_key, tango_connect_token, tango_connect_company_id) 
                    VALUES (:empresa_id, :nombre, :email, :telefono, :tango_api_url, :tango_connect_key, :tango_connect_token, :tango_connect_company_id)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':empresa_id' => $config->empresa_id,
                ':nombre' => $config->nombre_fantasia,
                ':email' => $config->email_contacto,
                ':telefono' => $config->telefono,
                ':tango_api_url' => $config->tango_api_url,
                ':tango_connect_key' => $config->tango_connect_key,
                ':tango_connect_token' => $config->tango_connect_token,
                ':tango_connect_company_id' => $config->tango_connect_company_id,
            ]);
            $config->id = (int) $this->db->lastInsertId();
        }
    }
}
