<?php

declare(strict_types=1);

namespace App\Modules\Tango\Mappers;

class TangoOrderMapper
{
    /**
     * Convierte el modelo local de pedido web a la estructura JSON compatible con Tango Connect 19845.
     * En caso de no tener documentación de Axoft estricta para el process 19845, se asume la estructura
     * estándar de Comprobantes de Ventas (Pedidos) típicos de Tango Rest API.
     */
    public static function map(array $pedidoCabecera, array $renglones, array $clienteWeb): array
    {
        // Regla Comercial Clave: "000000" si no tiene código.
        $codigoClienteTango = !empty($clienteWeb['codigo_tango']) ? $clienteWeb['codigo_tango'] : '000000';
        
        // Payload base descubierto mediante ingeniería inversa en Tango Connect (Process 19845)
        // Requiere estructura plana, ID de talonario y Depósito.
        $payload = [
            "FECHA_PEDIDO" => date('Y-m-d', strtotime($pedidoCabecera['created_at'])),
            "CODIGO_CLIENTE" => $codigoClienteTango,
            "ES_CLIENTE_HABITUAL" => ($codigoClienteTango !== '000000'),
            "NOTA_PEDIDO_WEB" => "RXN_" . $pedidoCabecera['id'],
            "ID_GVA43_TALON_PED" => 6, // Hardcoded para prueba, debería venir de config
            "ID_STA22" => 1, // Depósito
            "OBSERVACIONES" => $pedidoCabecera['observaciones'] ?? "WEB_ID: {$pedidoCabecera['id']} | Cliente Local: {$clienteWeb['nombre']} {$clienteWeb['apellido']} | Doc: {$clienteWeb['documento']}",
            
            // Sub-nodo Heurístico para cliente ocasional (dejado para futura depuración manual desde el admin)
            "CLIENTE_OCASIONAL" => [
                "RAZON_SOCIAL" => trim($clienteWeb['nombre'] . ' ' . $clienteWeb['apellido']),
                "DOMICILIO" => $clienteWeb['direccion'] ?? '',
                "NRO_DOCUMENTO" => $clienteWeb['documento'] ?? ''
            ]
        ];

        // Renglones
        $renglonesMapeados = [];
        foreach ($renglones as $renglon) {
            $renglonesMapeados[] = [
                "ARTICULO_CODIGO" => $renglon['codigo_articulo'] ?? '',
                "CANTIDAD" => (float) $renglon['cantidad'],
                "PRECIO_UNITARIO" => (float) $renglon['precio_unitario']
            ];
        }

        // Se inyecta la clave más estándar
        $payload["RENGLONES"] = $renglonesMapeados;
        $payload["ITEMS"] = $renglonesMapeados;

        return $payload;
    }
}
