<?php

declare(strict_types=1);

namespace App\Modules\Tango;

use App\Infrastructure\Http\ApiClient;
use RuntimeException;

class TangoApiClient
{
    private ApiClient $client;
    private string $apiUrl;
    private string $accessToken;
    private ?string $clientKey;
    public $debugLastRawDepositos = null;
    public $debugLastRawListas = null;
    public $debugLastRawEmpresas = null;
    public $debugLastHttpRequest = [];

    public function __construct(string $apiUrl, string $accessToken, string $companyId, ?string $clientKey = null)
    {
        if (empty($apiUrl) || empty($accessToken) || empty($companyId)) {
            throw new \App\Infrastructure\Exceptions\ConfigurationException("Configuración HTTP de integración Tango incompleta para este entorno operativo.");
        }

        $this->apiUrl = rtrim($apiUrl, '/');
        $this->accessToken = $accessToken;
        $this->clientKey = $clientKey;

        $this->client = $this->buildClient($companyId);
    }

    private function buildClient(string $companyId): ApiClient
    {
        if ($companyId === '') {
            throw new \App\Infrastructure\Exceptions\ConfigurationException("El header Company no puede viajar vacio hacia Tango Connect.");
        }

        // Estándar puro demandado por Connect
        $headers = [
            'ApiAuthorization: ' . $this->accessToken,
            'Company: ' . $companyId,
            'Accept: application/json',
            'Content-Type: application/json'
        ];
        
        if ($this->clientKey) {
            $headers[] = 'Client-Id: ' . $this->clientKey;
        }

        return new ApiClient($this->apiUrl, $headers);
    }

    /**
     * Extrae el Listado de Artículos desde Tango Connect /Api/Get?process=87
     * Implementa la paginación nativa basada en pageIndex (0-index).
     */
    public function getArticulos(int $page = 1, int $pageSize = 50): array
    {
        // El endpoint literal reportado operativo por Jefatura
        $endpoint = '/Api/Get';
        
        $params = [
            'process' => 87,
            'pageSize' => $pageSize,
            'pageIndex' => max(0, $page - 1),
            'view' => ''
        ];

        return $this->client->get($endpoint, $params);
    }

    /**
     * Extrae el Listado de Precios desde Tango Connect /Api/Get?process=20091
     */
    public function getPrecios(int $page = 1, int $pageSize = 100): array
    {
        $endpoint = '/Api/Get';
        
        $params = [
            'process' => 20091,
            'pageSize' => $pageSize,
            'pageIndex' => max(0, $page - 1),
            'view' => ''
        ];

        return $this->client->get($endpoint, $params);
    }
    /**
     * Extrae el Listado de Stock desde Tango Connect /Api/GetApiLiveQueryData?process=17668
     */
    public function getStock(int $page = 1, int $pageSize = 100): array
    {
        $endpoint = '/Api/GetApiLiveQueryData';
        
        $params = [
            'process' => 17668,
            'customQuery' => 0,
            'pageSize' => $pageSize,
            'pageIndex' => max(0, $page - 1)
        ];

        return $this->client->get($endpoint, $params);
    }

    /**
     * Valida si las credenciales proporcionan acceso autorizado (Handshake).
     */
    public function testConnection(): bool
    {
        try {
            $res = $this->client->get('Get', ['process' => 87, 'top' => 1]);
            return ($res && isset($res['status']) && $res['status'] === 200);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Agrupa y extrae un Maestro de Depósitos deduciéndolo de Process 2941.
     */
    /**
     * Agrupa y extrae un Maestro de Depósitos deduciéndolo de Process 2941.
     * Incorpora paginación total para garantizar Catálogo Exhaustivo.
     */
    public function getMaestroDepositos(): array
    {
        return $this->fetchCatalog(
            2941,
            ['ID_STA22'],
            ['COD_DESCRIP', 'NOMBRE_SUC'],
            $this->debugLastRawDepositos
        );
    }

    /**
     * Agrupa y extrae identificadores de Listas de Precios de Process 984.
     */
    public function getMaestroListasPrecio(): array
    {
        return $this->fetchCatalog(
            984,
            ['ID_GVA10'],
            ['COD_DESCRIP', 'NOMBRE_LIS'],
            $this->debugLastRawListas
        );
    }

    public function getMaestroEmpresas(): array
    {
        return $this->fetchCatalog(
            1418,
            ['ID_EMPRESA', 'ID', 'EMPRESA'],
            ['NOMBRE_EMPRESA', 'NOMBRE', 'RAZON_SOCIAL', 'DESCRIPCION', 'COD_DESCRIP'],
            $this->debugLastRawEmpresas,
            '-1'
        );
    }

    private function fetchCatalog(
        int $process,
        array $idKeys,
        array $descriptionKeys,
        mixed &$debugRawStore,
        ?string $companyOverride = null
    ): array {
        $items = [];
        $page = 0;
        $pageSize = 100;
        $client = $companyOverride !== null ? $this->buildClient($companyOverride) : $this->client;

        try {
            while (true) {
                $data = $client->get('Get', [
                    'process' => $process,
                    'pageSize' => $pageSize,
                    'pageIndex' => $page,
                    'view' => ''
                ]);

                if ($page === 0) {
                    $debugRawStore = $data;
                    $this->debugLastHttpRequest = $client->debugLastRequest ?? [];
                }

                $list = $data['data']['resultData']['list'] ?? [];
                if (empty($list)) {
                    break;
                }

                foreach ($list as $item) {
                    $id = $this->firstAvailableValue($item, $idKeys);
                    if ($id === null) {
                        continue;
                    }

                    $description = $this->firstAvailableValue($item, $descriptionKeys);
                    $normalizedId = $this->normalizeCatalogValue($id);
                    $normalizedDescription = $description !== null ? trim((string) $description) : $normalizedId;

                    $items[$normalizedId] = $normalizedDescription !== '' ? $normalizedDescription : $normalizedId;
                }

                if (count($list) < $pageSize) {
                    break;
                }

                $page++;
            }
        } catch (\Exception $e) {
        }

        return $items;
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

    private function normalizeCatalogValue(mixed $value): string
    {
        $normalized = trim((string) $value);

        if ($normalized !== '' && is_numeric($normalized)) {
            return (string) ($normalized + 0);
        }

        return $normalized;
    }
}
