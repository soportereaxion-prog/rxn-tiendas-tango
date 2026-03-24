<?php
declare(strict_types=1);

namespace App\Modules\ClientesWeb\Services;

use Exception;

class ClienteTangoLookupService
{
    private string $apiUrl;
    private string $token;
    private string $companyId;

    public function __construct(string $apiUrl, string $token, string $companyId)
    {
        $this->apiUrl = $apiUrl; // Debe venir la url base como "https://{key}.connect.axoft.com/Api"
        $this->token = $token;
        $this->companyId = $companyId;
    }

    /**
     * Valida un código de cliente en Tango usando la API process=2117 y
     * devuelve los identificadores internos necesarios para procesar pedidos.
     */
    public function findByCodigo(string $codigoTango): ?array
    {
        $filtroSql = "WHERE COD_GVA14 = '" . str_replace("'", "''", $codigoTango) . "'";
        $url = $this->apiUrl . "/GetByFilter?process=2117&view=&filtroSql=" . rawurlencode($filtroSql);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "ApiAuthorization: {$this->token}",
            "Company: {$this->companyId}",
            "Content-Type: application/json",
            "Accept: application/json"
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Error consultando API Tango (HTTP $httpCode): " . $response);
        }

        $data = json_decode((string)$response, true);

        if (empty($data['list']) || !isset($data['list'][0])) {
            return null; // Cliente no encontrado
        }

        $tangoClient = $data['list'][0];

        // Mapeo detallado de respuesta para almacenar localmente lo útil
        return [
            'id_gva14_tango' => $tangoClient['ID_GVA14'] ?? null,
            'id_gva01_condicion_venta' => $tangoClient['GVA01_COND_VTA'] ?? null,
            'id_gva10_lista_precios' => $tangoClient['GVA10_NRO_DE_LIS'] ?? null,
            'id_gva23_vendedor' => $tangoClient['GVA23_CODIGO'] ?? null,
            'id_gva24_transporte' => $tangoClient['GVA24_CODIGO'] ?? null,
            'razon_social' => $tangoClient['RAZON_SOCI'] ?? null,
            'cuit' => $tangoClient['CUIT'] ?? null,
            'domicilio' => $tangoClient['DOMICILIO'] ?? null
        ];
    }
}
