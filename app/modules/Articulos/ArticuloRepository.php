<?php
declare(strict_types=1);
namespace App\Modules\Articulos;

use App\Core\Database;
use PDO;

class ArticuloRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Inserta un artículo o lo actualiza si el código externo ya existe en la misma empresa.
     * @return array conteniendo los affected_rows (1 = insertado, 2 = modificado)
     */
    public function upsert(Articulo $articulo): array
    {
        $sql = "INSERT INTO articulos (empresa_id, codigo_externo, nombre, descripcion, precio, activo, fecha_ultima_sync) 
                VALUES (:empresa_id, :codigo, :nombre, :descripcion, :precio, :activo, NOW())
                ON DUPLICATE KEY UPDATE 
                nombre = VALUES(nombre),
                descripcion = VALUES(descripcion),
                precio = VALUES(precio),
                activo = VALUES(activo),
                fecha_ultima_sync = NOW()";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':empresa_id' => $articulo->empresa_id,
            ':codigo' => $articulo->codigo_externo,
            ':nombre' => $articulo->nombre,
            ':descripcion' => $articulo->descripcion,
            ':precio' => $articulo->precio,
            ':activo' => $articulo->activo
        ]);

        return [
            'affected_rows' => $stmt->rowCount()
        ];
    }

    /**
     * Trae el catalogo general por licenciatario
     */
    public function findAll(int $empresaId): array
    {
        $sql = "SELECT * FROM articulos WHERE empresa_id = :empresa_id ORDER BY nombre ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':empresa_id' => $empresaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
