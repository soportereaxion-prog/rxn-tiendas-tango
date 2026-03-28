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
        $data = $this->requestJson("/GetByFilter?process=2117&view=&filtroSql=" . rawurlencode($filtroSql));
        $list = $this->extractList($data);

        if (empty($list) || !isset($list[0])) {
            return null; // Cliente no encontrado
        }

        $tangoClient = $list[0];

        return $this->hydrateClientData($tangoClient);
    }

    public function findById(string $idGva14): ?array
    {
        $idGva14 = trim($idGva14);
        if ($idGva14 === '') {
            return null;
        }

        $data = $this->requestJson("/GetById?process=2117&id=" . rawurlencode($idGva14));
        $client = $data['value'] ?? null;

        if (!is_array($client) || empty($client)) {
            return null;
        }

        return $this->hydrateClientData($client);
    }

    public function search(string $term, int $limit = 10): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $safeTerm = str_replace("'", "''", $term);
        $filtroSql = sprintf(
            "WHERE COD_GVA14 LIKE '%%%s%%' OR RAZON_SOCI LIKE '%%%s%%'",
            $safeTerm,
            $safeTerm
        );

        $data = $this->requestJson(
            "/GetByFilter?process=2117&view=&pageSize=" . max(1, min(20, $limit)) . "&pageIndex=0&filtroSql=" . rawurlencode($filtroSql)
        );

        $results = [];
        foreach ($this->extractList($data) as $item) {
            $id = isset($item['ID_GVA14']) ? trim((string) $item['ID_GVA14']) : '';
            $codigo = isset($item['COD_GVA14']) ? trim((string) $item['COD_GVA14']) : '';
            $razonSocial = isset($item['RAZON_SOCI']) ? trim((string) $item['RAZON_SOCI']) : '';

            if ($id === '' || $codigo === '') {
                continue;
            }

            $results[] = [
                'id_gva14' => $id,
                'codigo' => $codigo,
                'razon_social' => $razonSocial,
                'label' => trim($codigo . ' - ' . $razonSocial, ' -'),
                'defaults' => [
                    'gva01' => $this->normalizeComparableCode($item['GVA01_COND_VTA'] ?? null),
                    'gva10' => $this->normalizeComparableCode($item['GVA10_NRO_DE_LIS'] ?? null),
                    'gva23' => $this->normalizeComparableCode($item['GVA23_CODIGO'] ?? null),
                    'gva24' => $this->normalizeComparableCode($item['GVA24_CODIGO'] ?? null),
                ],
            ];
        }

        return $results;
    }

    public function getRelacionCatalogs(): array
    {
        return [
            'condiciones_venta' => $this->fetchCatalogOptions(
                2497,
                ['COND_VTA'],
                ['ID_GVA01'],
                ['COD_DESCRIP', 'DESC_COND']
            ),
            'listas_precios' => $this->fetchCatalogOptions(
                984,
                ['NRO_DE_LIS'],
                ['ID_GVA10'],
                ['COD_DESCRIP', 'NOMBRE_LIS']
            ),
            'vendedores' => $this->fetchCatalogOptions(
                952,
                ['COD_GVA23'],
                ['ID_GVA23'],
                ['COD_DESCRIP', 'NOMBRE_VEN']
            ),
            'transportes' => $this->fetchCatalogOptions(
                960,
                ['COD_GVA24'],
                ['ID_GVA24'],
                ['COD_DESCRIP', 'NOMBRE_TRA']
            ),
        ];
    }

    private function hydrateClientData(array $tangoClient): array
    {
        $tangoInternal = [];

        // --- NUEVO: Traer IDs Internos Mapeados necesarios para el process 19845 ---
        if (!empty($tangoClient['ID_GVA14'])) {
            $idBase = $tangoClient['ID_GVA14'];
            $dataId = $this->requestJson("/GetById?process=2117&id=" . rawurlencode((string) $idBase));
            $tangoInternal = $dataId['value'] ?? [];
        }

        // Mapeo detallado de respuesta para almacenar localmente lo útil
        return [
            'id_gva14_tango' => $tangoClient['ID_GVA14'] ?? null,
            'codigo_tango' => $tangoClient['COD_GVA14'] ?? null,
            'id_gva01_condicion_venta' => $this->normalizeComparableCode($tangoClient['GVA01_COND_VTA'] ?? null),
            'id_gva10_lista_precios' => $this->normalizeComparableCode($tangoClient['GVA10_NRO_DE_LIS'] ?? null),
            'id_gva23_vendedor' => $this->normalizeComparableCode($tangoClient['GVA23_CODIGO'] ?? null),
            'id_gva24_transporte' => $this->normalizeComparableCode($tangoClient['GVA24_CODIGO'] ?? null),
            
            // Identificadores internos estrictamente nativos para proceso Pedidos (19845)
            'id_gva01_tango' => $tangoInternal['ID_GVA01'] ?? null,
            'id_gva10_tango' => $tangoInternal['ID_GVA10'] ?? null,
            'id_gva23_tango' => $tangoInternal['ID_GVA23'] ?? null,
            'id_gva24_tango' => $tangoInternal['ID_GVA24'] ?? null,

            'razon_social' => $tangoClient['RAZON_SOCI'] ?? null,
            'cuit' => $tangoClient['CUIT'] ?? null,
            'domicilio' => $tangoClient['DOMICILIO'] ?? null
        ];
    }

    private function requestJson(string $path): array
    {
        $url = $this->apiUrl . $path;
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

        $data = json_decode((string) $response, true);

        return is_array($data) ? $data : [];
    }

    private function extractList(array $data): array
    {
        $list = $data['list'] ?? $data['value'] ?? $data['resultData']['list'] ?? [];

        return is_array($list) ? array_values($list) : [];
    }

    private function fetchCatalogOptions(int $process, array $codeKeys, array $internalIdKeys, array $labelKeys): array
    {
        $options = [];
        $page = 0;
        $pageSize = 100;

        do {
            $data = $this->requestJson(
                "/Get?process={$process}&pageSize={$pageSize}&pageIndex={$page}&view="
            );

            $list = $this->extractList($data);
            foreach ($list as $item) {
                $codigoOriginal = $this->firstAvailableValue($item, $codeKeys);
                $internalId = $this->firstAvailableValue($item, $internalIdKeys);

                if ($codigoOriginal === null || $internalId === null) {
                    continue;
                }

                $codigoNormalizado = $this->normalizeComparableCode($codigoOriginal);
                if ($codigoNormalizado === '') {
                    continue;
                }

                $label = $this->firstAvailableValue($item, $labelKeys);
                $descripcion = trim((string) ($label ?? ''));

                $options[$codigoNormalizado] = [
                    'codigo' => $codigoNormalizado,
                    'codigo_original' => trim((string) $codigoOriginal),
                    'id_interno' => (int) $internalId,
                    'descripcion' => $descripcion,
                    'label' => $descripcion !== '' ? $descripcion : trim((string) $codigoOriginal),
                ];
            }

            $page++;
        } while (count($list) === $pageSize);

        return array_values($options);
    }

    private function firstAvailableValue(array $item, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $item) && $item[$key] !== null && $item[$key] !== '') {
                return $item[$key];
            }
        }

        return null;
    }

    private function normalizeComparableCode(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        if (is_numeric($normalized)) {
            return (string) ($normalized + 0);
        }

        return $normalized;
    }
}
