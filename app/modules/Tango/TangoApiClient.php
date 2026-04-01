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

        $url = rtrim($apiUrl, '/');
        // Ensure standard /Api suffix
        if (!preg_match('/\/Api$/i', $url)) {
            $url .= '/Api';
        }

        $this->apiUrl = $url;
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
        $endpoint = 'Get';
        
        $params = [
            'process' => 87,
            'pageSize' => $pageSize,
            'pageIndex' => max(0, $page - 1),
            'view' => ''
        ];

        return $this->client->get($endpoint, $params);
    }

    /**
     * Extrae el Listado de Clientes desde Tango Connect /Api/Get?process=2117
     */
    public function getClientes(int $page = 1, int $pageSize = 50): array
    {
        $endpoint = 'Get';
        $params = [
            'process' => 2117,
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
        $endpoint = 'Get';
        
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
        $endpoint = 'GetApiLiveQueryData';
        
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
     * Obtiene el listado de Perfiles de Pedido habilitados. (process 20020)
     */
    public function getPerfilesPedidos(): array
    {
        $items = [];
        try {
            $data = $this->client->get('Get', [
                'process' => 20020,
                'pageSize' => 500,
                'pageIndex' => 0,
                'view' => 'Habilitados'
            ]);

            $list = $data['resultData']['list'] ?? $data['data']['resultData']['list'] ?? [];
            foreach ($list as $item) {
                if (!empty($item['ID_PERFIL'])) {
                    $items[] = [
                        'id' => (int) $item['ID_PERFIL'],
                        'codigo' => trim((string) ($item['COD_PERFIL'] ?? '')),
                        'nombre' => trim((string) ($item['DESC_PERFIL'] ?? '')),
                    ];
                }
            }
        } catch (\Exception $e) {
            // Silencioso en extraccion de catalogo
        }
        return $items;
    }

    /**
     * Obtiene el detalle de metadata interna de un Perfil de Pedido (vendedores, talonarios, etc).
     */
    public function getPerfilPedidoById(string|int $profileId): ?array
    {
        try {
            $data = $this->client->get('GetById', [
                'process' => 20020,
                'id' => (int) $profileId
            ]);

            // Caso 1: la respuesta ya tiene 'value' en el nivel raíz
            if (isset($data['value']) && is_array($data['value'])) {
                $val = $data['value'];
                return (isset($val[0]) && is_array($val[0])) ? $val[0] : $val;
            }

            // Caso 2: la respuesta tiene 'data.value' (envelope estándar del cliente HTTP)
            // Tango Connect GetById devuelve {value: {...perfil...}, message, succeeded}
            if (isset($data['data']['value']) && is_array($data['data']['value'])) {
                $val = $data['data']['value'];
                return (isset($val[0]) && is_array($val[0])) ? $val[0] : $val;
            }

            // Caso 3: resultData directo
            if (isset($data['resultData']) && is_array($data['resultData'])) {
                return $data['resultData'];
            }
            if (isset($data['data']['resultData']) && is_array($data['data']['resultData'])) {
                return $data['data']['resultData'];
            }

            // Caso 4: array indexado en data
            if (isset($data['data'][0]) && is_array($data['data'][0])) {
                return $data['data'][0];
            }

            // Caso 5: data plano que NO sea el envelope (evitar devolver {value, message, succeeded})
            if (isset($data['data']) && is_array($data['data']) && !isset($data['data'][0])) {
                $inner = $data['data'];
                // Si es el envelope, extraer value de adentro
                if (isset($inner['value']) && is_array($inner['value'])) {
                    $val = $inner['value'];
                    return (isset($val[0]) && is_array($val[0])) ? $val[0] : $val;
                }
                // Si tiene las keys típicas de un perfil Tango, devolverlo
                if (!isset($inner['succeeded']) && !isset($inner['message'])) {
                    return $inner;
                }
            }

            return null;
        } catch (\Exception $e) {
            return null;
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

    public $debugLastRawClasificaciones = null;

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
    
    public function getRawClient(): \App\Infrastructure\Http\ApiClient
    {
        return $this->client;
    }

    public function getClasificacionesPds(): array
    {
        return $this->fetchRichCatalog(
            326,
            ['ID_GVA81'],
            ['COD_GVA81', 'DESCRIP'],
            $this->debugLastRawClasificaciones
        );
    }

    private function fetchRichCatalog(
        int $process,
        array $idKeys,
        array $fieldKeys,
        mixed &$debugRawStore,
        ?string $companyOverride = null
    ): array {
        $items = [];
        $page = 0;
        $pageSize = 100;
        $client = $companyOverride !== null ? $this->buildClient($companyOverride) : $this->client;
        $seenFirstIds = [];

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

                $list = $data['resultData']['list'] ?? $data['data']['resultData']['list'] ?? [];
                if (empty($list)) {
                    break;
                }

                $firstId = $this->firstAvailableValue($list[0], $idKeys);
                if ($firstId !== null) {
                    $firstIdStr = (string)$firstId;
                    if (isset($seenFirstIds[$firstIdStr])) {
                        break; 
                    }
                    $seenFirstIds[$firstIdStr] = true;
                }

                foreach ($list as $item) {
                    $id = $this->firstAvailableValue($item, $idKeys);
                    if ($id === null) {
                        continue;
                    }

                    $normalizedId = $this->normalizeCatalogValue($id);
                    $richItem = ['id' => $normalizedId];
                    foreach ($fieldKeys as $fk) {
                        $richItem[$fk] = trim((string) ($item[$fk] ?? ''));
                    }
                    $items[] = $richItem;
                }

                if (count($list) < $pageSize || $page >= 30) {
                    break;
                }

                $page++;
            }
        } catch (\Exception $e) {
        }

        return $items;
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
        $seenFirstIds = [];

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

                $list = $data['resultData']['list'] ?? $data['data']['resultData']['list'] ?? [];
                if (empty($list)) {
                    break;
                }

                $firstId = $this->firstAvailableValue($list[0], $idKeys);
                if ($firstId !== null) {
                    $firstIdStr = (string)$firstId;
                    if (isset($seenFirstIds[$firstIdStr])) {
                        break; 
                    }
                    $seenFirstIds[$firstIdStr] = true;
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

                if (count($list) < $pageSize || $page >= 30) {
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
