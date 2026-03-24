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
        
        // Formato GVA21 -> Datos de cabecera de pedido mínimo vital
        $payload = [
            "CABECERA" => [
                "CODIGO_CLIENTE" => $codigoClienteTango,
                "NOTA_PEDIDO_WEB" => "RXN_" . $pedidoCabecera['id'],
                "FECHA_PEDIDO" => date('Y-m-d'), // La fecha en formato ISO típica
                "OBSERVACIONES" => $pedidoCabecera['observaciones'] ?? "WEB_ID: {$pedidoCabecera['id']} | Cliente Local: {$clienteWeb['nombre']} {$clienteWeb['apellido']} | Doc: {$clienteWeb['documento']}"
            ],
            "RENGLONES" => []
        ];

        // Renglones
        foreach ($renglones as $renglon) {
            // El backend debe pasar articulo_codigo idealmente o hacer join, pero acá tenemos articulo_id que en la DB es el ID auto_inc.
            // Necesitamos que $renglon traiga 'codigo_tango' del artículo. Lo asumiremos provisto desde el service.
            
            $payload["RENGLONES"][] = [
                "ARTICULO_CODIGO" => $renglon['codigo_articulo_tango'] ?? '',
                "CANTIDAD" => (float) $renglon['cantidad'],
                "PRECIO_UNITARIO" => (float) $renglon['precio_unitario']
            ];
        }

        return $payload;
    }
}
