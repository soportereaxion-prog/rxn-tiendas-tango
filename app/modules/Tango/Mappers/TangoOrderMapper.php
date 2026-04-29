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

        // Cotización (release 1.29.0): la cabecera puede traer 'cotizacion' (default 1).
        // Tango espera el campo COTIZACION exactamente con ese nombre.
        if (isset($pedidoCabecera['cotizacion']) && $pedidoCabecera['cotizacion'] !== '' && $pedidoCabecera['cotizacion'] !== null) {
            $payload['COTIZACION'] = (float) $pedidoCabecera['cotizacion'];
        }

        // Leyendas 1..5 (release 1.29.0): opcionales, máximo 60 caracteres c/u.
        // Tango espera LEYENDA_1, LEYENDA_2, ..., LEYENDA_5 con esos nombres exactos.
        for ($i = 1; $i <= 5; $i++) {
            $key = 'leyenda_' . $i;
            if (!empty($pedidoCabecera[$key])) {
                $payload['LEYENDA_' . $i] = mb_substr((string) $pedidoCabecera[$key], 0, 60);
            }
        }

        // Limpiamos nulos
        $payload = array_filter($payload, static fn ($value) => $value !== null);

        // Renglones
        //
        // Estructura de descripciones en Tango (release 1.29.x — confirmado con GET
        // de pedido real de Charly el 2026-04-29):
        //   - DESCRIPCION_ARTICULO            → texto principal, máx 50 chars.
        //   - DESCRIPCION_ADICIONAL_ARTICULO  → texto corto extra "DESC_ADIC", máx
        //                                       20 chars. NO lo usamos (muy corto
        //                                       para nuestro caso de uso).
        //   - DESCRIPCION_ADICIONAL_DTO[]     → ARRAY de filas adicionales con
        //                                       DESCRIPCION (50) + DESCRIPCION_ADICIONAL
        //                                       (20) cada una. Sirve para multilínea.
        //
        // Lógica: tomamos la descripción del operador (actual si la editó, sino la
        // original del catálogo) y la partimos en chunks de hasta 50 chars,
        // respetando saltos de línea manuales del textarea + word-wrap para no
        // cortar palabras feo. El primer chunk va en DESCRIPCION_ARTICULO; el resto
        // se emite como DESCRIPCION_ADICIONAL_DTO[].
        $renglonesMapeados = [];
        foreach ($renglones as $renglon) {
            $descOriginal = trim((string) ($renglon['descripcion_original'] ?? ''));
            $descActual   = trim((string) ($renglon['descripcion_actual']   ?? ''));

            // Preferimos la actual si está poblada; sino caemos a la original.
            $descParaTango = $descActual !== '' ? $descActual : $descOriginal;

            $chunks = self::chunkDescripcion($descParaTango, 50);
            $primerChunk = array_shift($chunks) ?? '';

            $linea = [
                "ID_STA11" => (int) $renglon['id_sta11_tango'],
                "ARTICULO_CODIGO" => $renglon['codigo_articulo'] ?? '', // opcional si ID_STA11 rige
                "CANTIDAD_PEDIDA" => (float) $renglon['cantidad'],
                "CANTIDAD_A_FACTURAR" => (float) $renglon['cantidad'],
                "CANTIDAD_PENDIENTE_A_FACTURAR" => (float) $renglon['cantidad'],
                "PRECIO" => (float) $renglon['precio_unitario'],
                "PORCENTAJE_BONIFICACION" => (float) ($renglon['bonificacion_porcentaje'] ?? 0),
                "DESCRIPCION_ARTICULO" => $primerChunk,
            ];

            if ($chunks !== []) {
                $linea["DESCRIPCION_ADICIONAL_DTO"] = array_map(static function (string $chunk): array {
                    return [
                        'DESCRIPCION' => $chunk,
                        'DESCRIPCION_ADICIONAL' => null,
                    ];
                }, $chunks);
            }

            $renglonesMapeados[] = $linea;
        }

        // Se inyecta la clave más estándar
        $payload["RENGLON_DTO"] = $renglonesMapeados;

        return $payload;
    }

    /**
     * Parte una descripción larga en chunks aptos para los campos de Tango.
     *
     * Algoritmo (release 1.29.x):
     *   1. Split por saltos de línea manuales del operador (\n, \r\n).
     *   2. Cada línea, si excede $maxLen, se subdivide con wordwrap respetando
     *      palabras (cut=true para palabras solas más largas que el límite).
     *   3. Trim de cada chunk + descarte de vacíos.
     *
     * @param string $texto Texto crudo del operador (puede tener \n).
     * @param int $maxLen   Largo máximo por chunk (default 50, límite de Tango).
     * @return string[] Lista de chunks listos para emitir.
     */
    private static function chunkDescripcion(string $texto, int $maxLen = 50): array
    {
        $texto = trim($texto);
        if ($texto === '') {
            return [];
        }

        $lineas = preg_split('/\r\n|\r|\n/u', $texto) ?: [];
        $chunks = [];
        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if ($linea === '') {
                continue;
            }
            if (mb_strlen($linea) <= $maxLen) {
                $chunks[] = $linea;
                continue;
            }
            // wordwrap respeta palabras; cut=true corta palabras solitarias > $maxLen.
            $partes = explode("\n", wordwrap($linea, $maxLen, "\n", true));
            foreach ($partes as $parte) {
                $parte = trim($parte);
                if ($parte !== '') {
                    $chunks[] = $parte;
                }
            }
        }

        return $chunks;
    }
}
