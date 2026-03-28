<?php

declare(strict_types=1);

namespace App\Modules\EmpresaConfig;

use App\Core\Database;
use PDO;
use InvalidArgumentException;

class EmpresaConfigRepository
{
    private PDO $db;
    private string $tableName;

    public function __construct(string $tableName = 'empresa_config')
    {
        $this->db = Database::getConnection();
        $this->tableName = $this->normalizeTableName($tableName);
    }

    public static function forCrm(): self
    {
        return new self('empresa_config_crm');
    }

    public static function forArea(string $area): self
    {
        return strtolower(trim($area)) === 'crm'
            ? self::forCrm()
            : new self();
    }

    public function findByEmpresaId(int $empresaId): ?EmpresaConfig
    {
        $stmt = $this->db->prepare('SELECT * FROM ' . $this->quoteTable() . ' WHERE empresa_id = :empresa_id');
        $stmt->execute([':empresa_id' => $empresaId]);
        $config = $stmt->fetchObject(EmpresaConfig::class);
        return $config ?: null;
    }

    public function save(EmpresaConfig $config): void
    {
        if ($config->id) {
            $sql = 'UPDATE ' . $this->quoteTable() . ' SET 
                    nombre_fantasia = :nombre, 
                    email_contacto = :email, 
                    telefono = :telefono,
                    tango_api_url = :tango_api_url,
                    tango_connect_key = :tango_connect_key,
                    tango_connect_token = :tango_connect_token,
                    tango_connect_company_id = :tango_connect_company_id,
                    cantidad_articulos_sync = :cantidad_articulos_sync,
                    lista_precio_1 = :lista_precio_1,
                    lista_precio_2 = :lista_precio_2,
                    deposito_codigo = :deposito_codigo,
                    imagen_default_producto = :imagen_default_producto,
                    usa_smtp_propio = :usa_smtp_propio,
                    smtp_host = :smtp_host,
                    smtp_port = :smtp_port,
                    smtp_user = :smtp_user,
                    smtp_pass = :smtp_pass,
                    smtp_secure = :smtp_secure,
                    smtp_from_email = :smtp_from_email,
                    smtp_from_name = :smtp_from_name
                    WHERE id = :id';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':nombre' => $config->nombre_fantasia,
                ':email' => $config->email_contacto,
                ':telefono' => $config->telefono,
                ':tango_api_url' => $config->tango_api_url,
                ':tango_connect_key' => $config->tango_connect_key,
                ':tango_connect_token' => $config->tango_connect_token,
                ':tango_connect_company_id' => $config->tango_connect_company_id,
                ':cantidad_articulos_sync' => (int) $config->cantidad_articulos_sync,
                ':lista_precio_1' => $config->lista_precio_1,
                ':lista_precio_2' => $config->lista_precio_2,
                ':deposito_codigo' => $config->deposito_codigo ?? null,
                ':imagen_default_producto' => $config->imagen_default_producto,
                ':usa_smtp_propio' => (int) $config->usa_smtp_propio,
                ':smtp_host' => $config->smtp_host,
                ':smtp_port' => $config->smtp_port ? (int) $config->smtp_port : null,
                ':smtp_user' => $config->smtp_user,
                ':smtp_pass' => $config->smtp_pass,
                ':smtp_secure' => $config->smtp_secure,
                ':smtp_from_email' => $config->smtp_from_email,
                ':smtp_from_name' => $config->smtp_from_name,
                ':id' => $config->id,
            ]);
        } else {
            $sql = 'INSERT INTO ' . $this->quoteTable() . ' (empresa_id, nombre_fantasia, email_contacto, telefono, tango_api_url, tango_connect_key, tango_connect_token, tango_connect_company_id, cantidad_articulos_sync, lista_precio_1, lista_precio_2, deposito_codigo, imagen_default_producto, usa_smtp_propio, smtp_host, smtp_port, smtp_user, smtp_pass, smtp_secure, smtp_from_email, smtp_from_name) 
                    VALUES (:empresa_id, :nombre, :email, :telefono, :tango_api_url, :tango_connect_key, :tango_connect_token, :tango_connect_company_id, :cantidad_articulos_sync, :lista_precio_1, :lista_precio_2, :deposito_codigo, :imagen_default_producto, :usa_smtp_propio, :smtp_host, :smtp_port, :smtp_user, :smtp_pass, :smtp_secure, :smtp_from_email, :smtp_from_name)';
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
                ':cantidad_articulos_sync' => (int) $config->cantidad_articulos_sync,
                ':lista_precio_1' => $config->lista_precio_1,
                ':lista_precio_2' => $config->lista_precio_2,
                ':deposito_codigo' => $config->deposito_codigo ?? null,
                ':imagen_default_producto' => $config->imagen_default_producto,
                ':usa_smtp_propio' => (int) $config->usa_smtp_propio,
                ':smtp_host' => $config->smtp_host,
                ':smtp_port' => $config->smtp_port ? (int) $config->smtp_port : null,
                ':smtp_user' => $config->smtp_user,
                ':smtp_pass' => $config->smtp_pass,
                ':smtp_secure' => $config->smtp_secure,
                ':smtp_from_email' => $config->smtp_from_email,
                ':smtp_from_name' => $config->smtp_from_name,
            ]);
            $config->id = (int) $this->db->lastInsertId();
        }
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    private function normalizeTableName(string $tableName): string
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
            throw new InvalidArgumentException('Nombre de tabla invalido para configuracion.');
        }

        return $tableName;
    }

    private function quoteTable(): string
    {
        return '`' . $this->tableName . '`';
    }
}
