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

    public function updateBranding(int $id, array $data): void
    {
        $sql = "UPDATE empresas SET 
                logo_url = :logo,
                favicon_url = :favicon,
                color_primary = :c_prim,
                color_secondary = :c_sec,
                footer_text = :f_text,
                footer_address = :f_addr,
                footer_phone = :f_phone,
                footer_socials = :f_soc
                WHERE id = :id";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':logo' => $data['logo_url'] ?? null,
            ':favicon' => $data['favicon_url'] ?? null,
            ':c_prim' => $data['color_primary'] ?? null,
            ':c_sec' => $data['color_secondary'] ?? null,
            ':f_text' => $data['footer_text'] ?? null,
            ':f_addr' => $data['footer_address'] ?? null,
            ':f_phone' => $data['footer_phone'] ?? null,
            ':f_soc' => $data['footer_socials'] ?? null,
            ':id' => $id,
        ]);
    }
}
