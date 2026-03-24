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
            $sql .= " AND (codigo_externo LIKE :search1 OR nombre LIKE :search2 OR descripcion LIKE :search3)";
            $params[':search1'] = "%{$search}%";
            $params[':search2'] = "%{$search}%";
            $params[':search3'] = "%{$search}%";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function findAllPaginated(int $empresaId, int $page = 1, int $limit = 50, string $search = '', string $orderBy = 'nombre', string $orderDir = 'ASC'): array
    {
        $offset = max(0, ($page - 1) * $limit);
        $sql = "SELECT a.*, 
               (SELECT ruta FROM articulo_imagenes 
                WHERE articulo_id = a.id AND empresa_id = a.empresa_id AND es_principal = 1 
                ORDER BY orden ASC LIMIT 1) as imagen_principal
                FROM articulos a WHERE a.empresa_id = :empresa_id";
        $params = [':empresa_id' => $empresaId];

        if ($search !== '') {
            $sql .= " AND (codigo_externo LIKE :search1 OR nombre LIKE :search2 OR descripcion LIKE :search3)";
            $params[':search1'] = "%{$search}%";
            $params[':search2'] = "%{$search}%";
            $params[':search3'] = "%{$search}%";
        }

        // Whitelist ordering
        $allowedColumns = ['codigo_externo', 'nombre', 'precio_lista_1', 'precio_lista_2', 'stock_actual', 'activo', 'fecha_ultima_sync'];
        if (!in_array($orderBy, $allowedColumns)) {
            $orderBy = 'nombre';
        }
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';

        $sql .= " ORDER BY {$orderBy} {$orderDir} LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        
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
        $sql = "SELECT a.*, 
               (SELECT ruta FROM articulo_imagenes 
                WHERE articulo_id = a.id AND empresa_id = a.empresa_id AND es_principal = 1 
                ORDER BY orden ASC LIMIT 1) as imagen_principal
                FROM articulos a WHERE a.id = :id AND a.empresa_id = :empresa_id";
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
        $articulo->precio_lista_1 = $row['precio_lista_1'] !== null ? (float)$row['precio_lista_1'] : null;
        $articulo->precio_lista_2 = $row['precio_lista_2'] !== null ? (float)$row['precio_lista_2'] : null;
        $articulo->stock_actual = $row['stock_actual'] !== null ? (float)$row['stock_actual'] : null;
        $articulo->activo = (int)$row['activo'];
        $articulo->fecha_ultima_sync = $row['fecha_ultima_sync'];
        $articulo->imagen_principal = $row['imagen_principal'] ?? null;
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
                stock_actual = :stock_actual,
                activo = :activo 
                WHERE id = :id AND empresa_id = :empresa_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':nombre' => $articulo->nombre,
            ':descripcion' => $articulo->descripcion,
            ':precio' => $articulo->precio,
            ':precio_lista_1' => $articulo->precio_lista_1,
            ':precio_lista_2' => $articulo->precio_lista_2,
            ':stock_actual' => $articulo->stock_actual,
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

        $sql = "UPDATE articulos SET {$columna} = :precio, fecha_ultima_sync = NOW() WHERE codigo_externo = :sku AND empresa_id = :empresa_id";
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
        $sql = "UPDATE articulos SET stock_actual = :saldo, fecha_ultima_sync = NOW() WHERE codigo_externo = :sku AND empresa_id = :empresa_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':saldo' => $saldo,
            ':sku' => $sku,
            ':empresa_id' => $empresaId
        ]);
        
        return $stmt->rowCount();
    }

    public function guardarImagen(int $empresaId, int $articuloId, string $ruta, int $esPrincipal = 1): bool
    {
        $sql = "INSERT INTO articulo_imagenes (empresa_id, articulo_id, ruta, es_principal) 
                VALUES (:empresa_id, :articulo_id, :ruta, :es_principal)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':empresa_id' => $empresaId,
            ':articulo_id' => $articuloId,
            ':ruta' => $ruta,
            ':es_principal' => $esPrincipal
        ]);
    }

    public function obtenerImagenPrincipal(int $empresaId, int $articuloId): ?string
    {
        $sql = "SELECT ruta FROM articulo_imagenes 
                WHERE empresa_id = :empresa_id AND articulo_id = :articulo_id 
                ORDER BY es_principal DESC, orden ASC 
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':empresa_id' => $empresaId, ':articulo_id' => $articuloId]);
        $ruta = $stmt->fetchColumn();
        return $ruta ?: null;
    }
}
