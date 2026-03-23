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
        $sql = "INSERT INTO empresas (codigo, nombre, razon_social, cuit, activa) 
                VALUES (:codigo, :nombre, :razon_social, :cuit, :activa)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':codigo' => $empresa->codigo,
            ':nombre' => $empresa->nombre,
            ':razon_social' => $empresa->razon_social,
            ':cuit' => $empresa->cuit,
            ':activa' => $empresa->activa,
        ]);
        
        $empresa->id = (int) $this->db->lastInsertId();
    }
}
