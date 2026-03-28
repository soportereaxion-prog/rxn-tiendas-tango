<?php

declare(strict_types=1);

namespace App\Modules\Pedidos;

use App\Core\Database;
use Exception;
use PDO;

class PedidoWebRepository
{
    private PDO $db;
    private const SEARCHABLE_FIELDS = [
        'id' => 'CAST(p.id AS CHAR)',
        'cliente' => 'CONCAT(COALESCE(c.nombre, \'\'), \' \' , COALESCE(c.apellido, \'\'))',
        'email' => 'c.email',
        'estado' => 'p.estado_tango',
    ];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Genera un pedido con sus renglones transaccionalmente.
     */
    public function createPedido(array $cabecera, array $renglones): int
    {
        try {
            $this->db->beginTransaction();

            $sqlCabecera = "INSERT INTO pedidos_web (
                empresa_id, cliente_web_id, codigo_cliente_tango_usado, total, observaciones,
                estado_tango, created_at, updated_at
            ) VALUES (
                :empresa_id, :cliente_web_id, :codigo_cliente_tango_usado, :total, :observaciones,
                'pendiente_envio_tango', NOW(), NOW()
            )";

            $stmtCabecera = $this->db->prepare($sqlCabecera);
            $stmtCabecera->execute([
                'empresa_id' => $cabecera['empresa_id'],
                'cliente_web_id' => $cabecera['cliente_web_id'],
                'codigo_cliente_tango_usado' => $cabecera['codigo_cliente_tango_usado'],
                'total' => $cabecera['total'],
                'observaciones' => $cabecera['observaciones'] ?? null,
            ]);

            $pedidoId = (int)$this->db->lastInsertId();

            $sqlRenglon = "INSERT INTO pedidos_web_renglones (
                pedido_web_id, articulo_id, cantidad, precio_unitario, nombre_articulo
            ) VALUES (
                :pedido_web_id, :articulo_id, :cantidad, :precio_unitario, :nombre_articulo
            )";

            $stmtRenglon = $this->db->prepare($sqlRenglon);

            foreach ($renglones as $renglon) {
                $stmtRenglon->execute([
                    'pedido_web_id' => $pedidoId,
                    'articulo_id' => $renglon['articulo_id'],
                    'cantidad' => $renglon['cantidad'],
                    'precio_unitario' => $renglon['precio_unitario'],
                    'nombre_articulo' => $renglon['nombre_articulo'],
                ]);
            }

            $this->db->commit();
            return $pedidoId;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Actualiza el estado del pedido luego de comunicarse con Tango.
     */
    public function markAsSentToTango(int $pedidoId, string $tangoPedidoNumero, string $payload, string $response): void
    {
        $sql = "UPDATE pedidos_web SET 
                estado_tango = 'enviado_tango',
                tango_pedido_numero = :tango_pedido_numero,
                intentos_envio_tango = intentos_envio_tango + 1,
                payload_enviado = :payload_enviado,
                respuesta_tango = :respuesta_tango,
                updated_at = NOW()
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $pedidoId,
            'tango_pedido_numero' => $tangoPedidoNumero,
            'payload_enviado' => $payload,
            'respuesta_tango' => $response
        ]);
    }

    /**
     * Marca el pedido como erróneo al enviarse a Tango, no perdiendo el local.
     */
    public function markAsErrorToTango(int $pedidoId, string $payload, string $errorText, ?string $jsonResponse = null): void
    {
        $sql = "UPDATE pedidos_web SET 
                estado_tango = 'error_envio_tango',
                intentos_envio_tango = intentos_envio_tango + 1,
                payload_enviado = :payload_enviado,
                mensaje_error = :mensaje_error,
                respuesta_tango = :respuesta_tango,
                updated_at = NOW()
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id' => $pedidoId,
            'payload_enviado' => $payload,
            'mensaje_error' => $errorText,
            'respuesta_tango' => $jsonResponse ?: json_encode(['error' => $errorText])
        ]);
    }

    public function countAll(int $empresaId, string $search = '', string $field = 'all', string $estado = ''): int
    {
        $sql = "SELECT COUNT(*) FROM pedidos_web p 
                LEFT JOIN clientes_web c ON p.cliente_web_id = c.id
                WHERE p.empresa_id = :empresa_id";
        $params = [':empresa_id' => $empresaId];

        if ($estado !== '') {
            $sql .= " AND p.estado_tango = :estado";
            $params[':estado'] = $estado;
        }

        $this->applySearch($sql, $params, $search, $field, true);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function findAllPaginated(int $empresaId, int $page = 1, int $limit = 50, string $search = '', string $field = 'all', string $estado = '', string $orderBy = 'p.created_at', string $orderDir = 'DESC'): array
    {
        $offset = max(0, ($page - 1) * $limit);
        $sql = "SELECT p.*, c.nombre as cliente_nombre, c.apellido as cliente_apellido, c.email as cliente_email 
                FROM pedidos_web p 
                LEFT JOIN clientes_web c ON p.cliente_web_id = c.id
                WHERE p.empresa_id = :empresa_id";
        $params = [':empresa_id' => $empresaId];

        if ($estado !== '') {
            $sql .= " AND p.estado_tango = :estado";
            $params[':estado'] = $estado;
        }

        $this->applySearch($sql, $params, $search, $field, true);

        $allowedColumns = ['p.created_at', 'p.id', 'p.total', 'cliente_nombre'];
        if (!in_array($orderBy, $allowedColumns)) {
            $orderBy = 'p.created_at';
        }
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        $sql .= " ORDER BY {$orderBy} {$orderDir} LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findSuggestions(int $empresaId, string $search = '', string $field = 'all', string $estado = '', int $limit = 3): array
    {
        if (trim($search) === '') {
            return [];
        }

        $sql = "SELECT p.id, p.estado_tango, c.nombre as cliente_nombre, c.apellido as cliente_apellido, c.email as cliente_email
                FROM pedidos_web p
                LEFT JOIN clientes_web c ON p.cliente_web_id = c.id
                WHERE p.empresa_id = :empresa_id";
        $params = [':empresa_id' => $empresaId];

        if ($estado !== '') {
            $sql .= " AND p.estado_tango = :estado";
            $params[':estado'] = $estado;
        }

        $this->applySearch($sql, $params, $search, $field, true);
        $sql .= ' ORDER BY p.created_at DESC LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findPendingIds(int $empresaId): array
    {
        $stmt = $this->db->prepare('SELECT id FROM pedidos_web WHERE empresa_id = :empresa_id AND estado_tango = :estado ORDER BY created_at ASC');
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':estado' => 'pendiente_envio_tango',
        ]);

        return array_map(static fn (array $row): int => (int) $row['id'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function findIdsByEmpresaAndList(int $empresaId, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            return [];
        }

        $placeholders = [];
        $params = [':empresa_id' => $empresaId];

        foreach ($ids as $index => $id) {
            $placeholder = ':id_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $id;
        }

        $sql = 'SELECT id FROM pedidos_web WHERE empresa_id = :empresa_id AND id IN (' . implode(',', $placeholders) . ') ORDER BY created_at ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map(static fn (array $row): int => (int) $row['id'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    private function applySearch(string &$sql, array &$params, string $search = '', string $field = 'all', bool $hasWhere = false): void
    {
        $search = trim($search);

        if ($search === '') {
            return;
        }

        $operator = $hasWhere ? ' AND ' : ' WHERE ';

        if ($field !== 'all' && isset(self::SEARCHABLE_FIELDS[$field])) {
            $sql .= $operator . self::SEARCHABLE_FIELDS[$field] . ' LIKE :search';
            $params[':search'] = '%' . $search . '%';
            return;
        }

        $sql .= $operator . ' (CAST(p.id AS CHAR) LIKE :s1 OR c.nombre LIKE :s2 OR c.apellido LIKE :s3 OR c.email LIKE :s4 OR p.estado_tango LIKE :s5)';
        $params[':s1'] = '%' . $search . '%';
        $params[':s2'] = '%' . $search . '%';
        $params[':s3'] = '%' . $search . '%';
        $params[':s4'] = '%' . $search . '%';
        $params[':s5'] = '%' . $search . '%';
    }

    public function findByIdWithDetails(int $id, int $empresaId): ?array
    {
        // 1. Cabecera + Cliente
        $sql = "SELECT p.*, c.*, p.id as pedido_id, p.observaciones as pedido_observaciones, p.created_at as pedido_fecha 
                FROM pedidos_web p
                LEFT JOIN clientes_web c ON p.cliente_web_id = c.id
                WHERE p.id = :id AND p.empresa_id = :empresa_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id, ':empresa_id' => $empresaId]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pedido) return null;

        // 2. Renglones
        $sqlReng = "SELECT * FROM pedidos_web_renglones WHERE pedido_web_id = :pedido_id";
        $stmtReng = $this->db->prepare($sqlReng);
        $stmtReng->execute([':pedido_id' => $id]);
        $pedido['renglones'] = $stmtReng->fetchAll(PDO::FETCH_ASSOC);

        // 3. Obtener el Codigo Tango original del articulo si existe, sumándolo al array renglones
        // No es mandatorio pero aporta para el admin. Para no complicar, lo omito ya que nombre_articulo y id están.

        return $pedido;
    }
}
