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
    public static function map(array $pedidoCabecera, array $renglones, array $clienteWeb, array $resolvedHeaders = []): array
    {
        // Regla Comercial Clave: "000000" si no tiene código.
        $codigoClienteTango = !empty($clienteWeb['codigo_tango']) ? $clienteWeb['codigo_tango'] : '000000';
        
        $fechaPedido = !empty($pedidoCabecera['created_at']) ? date('Y-m-d', strtotime($pedidoCabecera['created_at'])) : date('Y-m-d');

        // Payload base ajustado a las necesidades detectadas de ID Comercial
        $payload = [
            "FECHA_PEDIDO" => $fechaPedido,
            "ID_GVA14" => (int) ($clienteWeb['id_gva14_tango'] ?? 0),
            "ES_CLIENTE_HABITUAL" => true, // Tango API 19845 asume habitual con ID_GVA14 según auditoría
            "NOTA_PEDIDO_WEB" => "RXN_" . $pedidoCabecera['id'],
            "ID_GVA43_TALON_PED" => $resolvedHeaders['ID_GVA43_TALON_PED'] ?? null, 
            "ID_STA22" => $resolvedHeaders['ID_STA22'] ?? null, 
            "ESTADO" => 2, // 2 = Ingresado
            "OBSERVACIONES" => $pedidoCabecera['observaciones'] ?? "WEB_ID: {$pedidoCabecera['id']} | Cliente Local: {$clienteWeb['nombre']} {$clienteWeb['apellido']} | Doc: {$clienteWeb['documento']}"
        ];

        // Inyectar IDs Internos específicos de negocio del cliente y del perfil
        $payload['ID_GVA01'] = $resolvedHeaders['ID_GVA01'] ?? (!empty($clienteWeb['id_gva01_tango']) ? (int) $clienteWeb['id_gva01_tango'] : null);
        $payload['ID_GVA10'] = $resolvedHeaders['ID_GVA10'] ?? (!empty($clienteWeb['id_gva10_tango']) ? (int) $clienteWeb['id_gva10_tango'] : null);
        $payload['ID_GVA23'] = $resolvedHeaders['ID_GVA23'] ?? (!empty($clienteWeb['id_gva23_tango']) ? (int) $clienteWeb['id_gva23_tango'] : null);
        $payload['ID_GVA24'] = $resolvedHeaders['ID_GVA24'] ?? (!empty($clienteWeb['id_gva24_tango']) ? (int) $clienteWeb['id_gva24_tango'] : null);
        
        if (isset($resolvedHeaders['ID_GVA81'])) {
            $payload['ID_GVA81'] = (int)$resolvedHeaders['ID_GVA81'];
        }

        $payload['ID_PERFIL_PEDIDO'] = $resolvedHeaders['ID_PERFIL_PEDIDO'] ?? (!empty($pedidoCabecera['tango_perfil_pedido_id']) ? (int) $pedidoCabecera['tango_perfil_pedido_id'] : 1);
        $payload['ID_MONEDA'] = $resolvedHeaders['ID_MONEDA'] ?? 1;
        
        // Limpiamos nulos
        $payload = array_filter($payload, static fn ($value) => $value !== null);

        // Renglones
        $renglonesMapeados = [];
        foreach ($renglones as $renglon) {
            $renglonesMapeados[] = [
                "ID_STA11" => (int) $renglon['id_sta11_tango'],
                "ARTICULO_CODIGO" => $renglon['codigo_articulo'] ?? '', // opcional si ID_STA11 rige
                "CANTIDAD_PEDIDA" => (float) $renglon['cantidad'],
                "CANTIDAD_A_FACTURAR" => (float) $renglon['cantidad'],
                "CANTIDAD_PENDIENTE_A_FACTURAR" => (float) $renglon['cantidad'],
                "PRECIO" => (float) $renglon['precio_unitario'],
                "PORCENTAJE_BONIFICACION" => 0,
                "DESCRIPCION_ARTICULO" => "",
                "DESCRIPCION_ADICIONAL_ARTICULO" => ""
            ];
        }

        // Se inyecta la clave más estándar
        $payload["RENGLON_DTO"] = $renglonesMapeados;

        return $payload;
    }
}
