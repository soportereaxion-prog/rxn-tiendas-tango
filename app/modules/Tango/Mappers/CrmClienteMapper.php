<?php
declare(strict_types=1);

namespace App\Modules\Tango\Mappers;

class CrmClienteMapper
{
    public static function fromConnectJson(array $item): ?array
    {
        // Validación básica priorizando siempre al ERP
        $idGva14 = $item['ID_GVA14'] ?? null;
        $codigoTango = $item['COD_GVA14'] ?? null;

        if (empty($idGva14) && empty($codigoTango)) {
            return null; 
        }

        return [
            'id_gva14_tango' => is_numeric($idGva14) ? (int)$idGva14 : null,
            'codigo_tango' => $codigoTango !== '' ? (string)$codigoTango : null,
            'razon_social' => isset($item['RAZON_SOCI']) ? trim((string)$item['RAZON_SOCI']) : '',
            'documento' => isset($item['CUIT']) ? trim((string)$item['CUIT']) : null,
            'email' => isset($item['E_MAIL']) ? trim((string)$item['E_MAIL']) : (isset($item['EMAIL']) ? trim((string)$item['EMAIL']) : null),
            'telefono' => isset($item['TELEFONO_1']) ? trim((string)$item['TELEFONO_1']) : null,
            'direccion' => isset($item['DOMICILIO']) ? trim((string)$item['DOMICILIO']) : null,
            'activo' => 1,
            
            // Relaciones Nativas: Si vienen en payload 2117 tienen nombres puntuales (como GVA01_COND_VTA)
            'id_gva01_tango' => self::normalizeCode($item['GVA01_COND_VTA'] ?? null),
            'id_gva10_tango' => self::normalizeCode($item['GVA10_NRO_DE_LIS'] ?? null),
            'id_gva23_tango' => self::normalizeCode($item['GVA23_CODIGO'] ?? null),
            'id_gva24_tango' => self::normalizeCode($item['GVA24_CODIGO'] ?? null),
        ];
    }

    private static function normalizeCode($value): ?string
    {
        if ($value === null || trim((string)$value) === '') {
            return null;
        }
        $val = trim((string)$value);
        return is_numeric($val) ? (string)($val + 0) : $val;
    }
}
