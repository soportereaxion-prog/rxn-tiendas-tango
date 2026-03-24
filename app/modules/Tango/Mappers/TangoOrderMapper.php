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
        
        $fechaPedido = !empty($pedidoCabecera['created_at']) ? date('Y-m-d', strtotime($pedidoCabecera['created_at'])) : date('Y-m-d');

        // Payload base ajustado a las necesidades detectadas de ID Comercial
        $payload = [
            "FECHA_PEDIDO" => $fechaPedido,
            "ID_GVA14" => (int) ($clienteWeb['id_gva14_tango'] ?? 0),
            "ES_CLIENTE_HABITUAL" => true, // Tango API 19845 asume habitual con ID_GVA14 según auditoría
            "NOTA_PEDIDO_WEB" => "RXN_" . $pedidoCabecera['id'],
            "ID_GVA43_TALON_PED" => 6, // Hardcoded para prueba, debería venir de config
            "ID_STA22" => 1, // Depósito
            "ESTADO" => 2, // 2 = Ingresado
            "OBSERVACIONES" => $pedidoCabecera['observaciones'] ?? "WEB_ID: {$pedidoCabecera['id']} | Cliente Local: {$clienteWeb['nombre']} {$clienteWeb['apellido']} | Doc: {$clienteWeb['documento']}"
        ];

        // Inyectar IDs Internos específicos de negocio del cliente para que no rebote por 'Condición Inexistente' etc
        if (!empty($clienteWeb['id_gva01_tango'])) $payload['ID_GVA01'] = (int) $clienteWeb['id_gva01_tango'];
        if (!empty($clienteWeb['id_gva10_tango'])) $payload['ID_GVA10'] = (int) $clienteWeb['id_gva10_tango'];
        if (!empty($clienteWeb['id_gva23_tango'])) $payload['ID_GVA23'] = (int) $clienteWeb['id_gva23_tango'];
        if (!empty($clienteWeb['id_gva24_tango'])) $payload['ID_GVA24'] = (int) $clienteWeb['id_gva24_tango'];

        $payload['ID_PERFIL_PEDIDO'] = 1; // Valor predeterminado mínimo. Podría venir en config en un futuro.
        $payload['ID_MONEDA'] = 1;

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
