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

    public function countAll(int $empresaId, string $search = ''): int
    {
        $sql = "SELECT COUNT(*) FROM articulos WHERE empresa_id = :empresa_id";
        $params = [':empresa_id' => $empresaId];

        if ($search !== '') {
            $sql .= " AND (codigo_externo LIKE :search OR nombre LIKE :search OR descripcion LIKE :search)";
            $params[':search'] = "%{$search}%";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function findAllPaginated(int $empresaId, int $page = 1, int $limit = 50, string $search = ''): array
    {
        $offset = max(0, ($page - 1) * $limit);
        $sql = "SELECT * FROM articulos WHERE empresa_id = :empresa_id";
        $params = [':empresa_id' => $empresaId];

        if ($search !== '') {
            $sql .= " AND (codigo_externo LIKE :search OR nombre LIKE :search OR descripcion LIKE :search)";
            $params[':search'] = "%{$search}%";
        }

        $sql .= " ORDER BY nombre ASC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function truncateArticulos(int $empresaId): void
    {
        $stmt = $this->db->prepare("DELETE FROM articulos WHERE empresa_id = :empresa_id");
        $stmt->execute([':empresa_id' => $empresaId]);
    }

    public function deleteByIds(array $ids, int $empresaId): int
    {
        if (empty($ids)) return 0;
        
        // Genera array de bindings (?, ?, ?)
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $sql = "DELETE FROM articulos WHERE empresa_id = ? AND id IN ($placeholders)";
        
        $params = array_merge([$empresaId], $ids);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->rowCount();
    }

    public function findById(int $id, int $empresaId): ?Articulo
    {
        $sql = "SELECT * FROM articulos WHERE id = :id AND empresa_id = :empresa_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id, ':empresa_id' => $empresaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) return null;
        
        $articulo = new Articulo();
        $articulo->id = (int)$row['id'];
        $articulo->empresa_id = (int)$row['empresa_id'];
        $articulo->codigo_externo = $row['codigo_externo'];
        $articulo->nombre = $row['nombre'];
        $articulo->descripcion = $row['descripcion'];
        $articulo->precio = $row['precio'] !== null ? (float)$row['precio'] : null;
        $articulo->activo = (int)$row['activo'];
        $articulo->fecha_ultima_sync = $row['fecha_ultima_sync'];
        return $articulo;
    }

    public function update(Articulo $articulo): bool
    {
        $sql = "UPDATE articulos SET 
                nombre = :nombre, 
                descripcion = :descripcion, 
                precio = :precio, 
                precio_lista_1 = :precio_lista_1,
                precio_lista_2 = :precio_lista_2,
                activo = :activo 
                WHERE id = :id AND empresa_id = :empresa_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':nombre' => $articulo->nombre,
            ':descripcion' => $articulo->descripcion,
            ':precio' => $articulo->precio,
            ':precio_lista_1' => $articulo->precio_lista_1,
            ':precio_lista_2' => $articulo->precio_lista_2,
            ':activo' => $articulo->activo,
            ':id' => $articulo->id,
            ':empresa_id' => $articulo->empresa_id
        ]);
    }

    public function updatePrecioListas(string $sku, float $precio, string $columna, int $empresaId): int
    {
        // $columna has to be safe. It will be 'precio_lista_1' or 'precio_lista_2'.
        if (!in_array($columna, ['precio_lista_1', 'precio_lista_2'])) {
            return 0;
        }

        $sql = "UPDATE articulos SET {$columna} = :precio WHERE codigo_externo = :sku AND empresa_id = :empresa_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':precio' => $precio,
            ':sku' => $sku,
            ':empresa_id' => $empresaId
        ]);
        
        return $stmt->rowCount();
    }

    public function updateStock(string $sku, float $saldo, int $empresaId): int
    {
        $sql = "UPDATE articulos SET stock_actual = :saldo WHERE codigo_externo = :sku AND empresa_id = :empresa_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':saldo' => $saldo,
            ':sku' => $sku,
            ':empresa_id' => $empresaId
        ]);
        
        return $stmt->rowCount();
    }
}
