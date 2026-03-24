<?php

declare(strict_types=1);

namespace App\Modules\Pedidos;

use App\Core\Database;
use Exception;
use PDO;

class PedidoWebRepository
{
    private PDO $db;

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
}
