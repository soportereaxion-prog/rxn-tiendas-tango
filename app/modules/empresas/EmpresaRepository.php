<?php

declare(strict_types=1);

namespace App\Modules\Empresas;

use App\Core\Database;
use PDO;

class EmpresaRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findAll(): array
    {
        $stmt = $this->db->query("SELECT * FROM empresas ORDER BY nombre ASC");
        return $stmt->fetchAll(PDO::FETCH_CLASS, Empresa::class);
    }

    public function save(Empresa $empresa): void
    {
        $sql = "INSERT INTO empresas (codigo, nombre, razon_social, cuit, slug, activa) 
                VALUES (:codigo, :nombre, :razon_social, :cuit, :slug, :activa)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':codigo' => $empresa->codigo,
            ':nombre' => $empresa->nombre,
            ':razon_social' => $empresa->razon_social,
            ':cuit' => $empresa->cuit,
            ':slug' => $empresa->slug,
            ':activa' => $empresa->activa,
        ]);
        
        $empresa->id = (int) $this->db->lastInsertId();
    }

    public function findById(int $id): ?Empresa
    {
        $stmt = $this->db->prepare("SELECT * FROM empresas WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $empresa = $stmt->fetchObject(Empresa::class);
        return $empresa ?: null;
    }

    public function findByCodigo(string $codigo, ?int $excludeId = null): ?Empresa
    {
        $sql = "SELECT * FROM empresas WHERE codigo = :codigo";
        $params = [':codigo' => $codigo];
        
        if ($excludeId !== null) {
            $sql .= " AND id != :excludeId";
            $params[':excludeId'] = $excludeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $empresa = $stmt->fetchObject(Empresa::class);
        return $empresa ?: null;
    }

    public function update(Empresa $empresa): void
    {
        $sql = "UPDATE empresas SET 
                codigo = :codigo, 
                nombre = :nombre, 
                razon_social = :razon_social, 
                cuit = :cuit, 
                slug = :slug,
                activa = :activa 
                WHERE id = :id";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':codigo' => $empresa->codigo,
            ':nombre' => $empresa->nombre,
            ':razon_social' => $empresa->razon_social,
            ':cuit' => $empresa->cuit,
            ':slug' => $empresa->slug,
            ':activa' => $empresa->activa,
            ':id' => $empresa->id,
        ]);
    }
}
