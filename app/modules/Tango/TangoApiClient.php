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
    private string $defaultCompanyHeader = '';
    public $debugLastRawDepositos = null;
    public $debugLastRawListas = null;
    public $debugLastRawEmpresas = null;
    public $debugLastHttpRequest = [];

    /**
     * Diagnostico del ultimo fetchCatalog/fetchRichCatalog ejecutado.
     * Siempre se rellena — tanto en exito como en error — para que el caller
     * pueda explicar al usuario por que un catalogo vino vacio sin depender
     * de DevTools. Ver docs/logs/2026-04-16 release 1.12.2.
     *
     * Shape:
     *  - outcome: 'ok' | 'empty' | 'error'
     *  - process: int
     *  - company_header: string (el valor usado en el header Company)
     *  - url: string (baseUrl usado)
     *  - items_count: int
     *  - error_class: ?string
     *  - error_message: ?string
     *  - http_code: ?int
     *  - raw_sample: ?string (primeros 500 chars del body crudo si hubo)
     *  - id_keys: array (las claves buscadas para el ID)
     *  - first_item_keys: array (las keys del primer item recibido, para debugging)
     */
    public array $debugLastDiagnostic = [];

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

        $this->defaultCompanyHeader = $companyId;
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

            return $this->extractGetByIdValue($data);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Obtiene el detalle de un Artículo por ID (process 87)
     */
    public function getArticuloById(string|int $id): ?array
    {
        try {
            $data = $this->client->get('GetById', [
                'process' => 87,
                'id' => (int) $id
            ]);

            return $this->extractGetByIdValue($data);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Obtiene el detalle de un Cliente por ID (process 2117)
     */
    public function getClienteById(string|int $id): ?array
    {
        try {
            $data = $this->client->get('GetById', [
                'process' => 2117,
                'id' => (int) $id
            ]);

            return $this->extractGetByIdValue($data);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Actualiza una entidad completa usando Whitelist
     * Depende del API Connect subyacente (usaremos Update por defecto).
     */
    public function updateEntity(int $process, array $payload): array
    {
        // En un patrón REST de Connect solemos usar `Update` o `Save` 
        // Pasamos un envelope estándar "data" => [...] si fuera POST masivo, 
        // pero la documentación nativa de Update PUT asume el objeto directo.
        return $this->client->put("Update?process=" . $process, $payload);
    }

    private function extractGetByIdValue(array $data): ?array
    {
        // Caso 1: la respuesta ya tiene 'value' en el nivel raíz
        if (isset($data['value']) && is_array($data['value'])) {
            $val = $data['value'];
            return (isset($val[0]) && is_array($val[0])) ? $val[0] : $val;
        }

        // Caso 2: la respuesta tiene 'data.value' (envelope estándar del cliente HTTP)
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

        // Caso 5: data plano que NO sea el envelope
        if (isset($data['data']) && is_array($data['data']) && !isset($data['data'][0])) {
            $inner = $data['data'];
            if (isset($inner['value']) && is_array($inner['value'])) {
                $val = $inner['value'];
                return (isset($val[0]) && is_array($val[0])) ? $val[0] : $val;
            }
            if (!isset($inner['succeeded']) && !isset($inner['message'])) {
                return $inner;
            }
        }

        return null;
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
            $this->debugLastRawDepositos,
            null,
            1000  // Depósitos son maestros pequeños: 1 request cubre todo
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
            $this->debugLastRawListas,
            null,
            1000  // Listas de precio son maestros pequeños: 1 request cubre todo
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
            '-1',
            1000  // Empresas son maestros: 1 request cubre todo
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
        ?string $companyOverride = null,
        int $maxPageSize = 100
    ): array {
        $items = [];
        $page = 0;
        $pageSize = $maxPageSize;
        $client = $companyOverride !== null ? $this->buildClient($companyOverride) : $this->client;
        $seenFirstIds = [];
        $firstItemKeys = [];
        $rawSample = null;

        $this->resetDiagnostic($process, $companyOverride, $idKeys);

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
                    $rawSample = $this->sampleRaw($data);
                }

                $list = $data['resultData']['list'] ?? $data['data']['resultData']['list'] ?? [];
                if (empty($list)) {
                    break;
                }

                if ($page === 0 && is_array($list[0] ?? null)) {
                    $firstItemKeys = array_keys($list[0]);
                }

                $firstId = $this->firstAvailableValue($list[0], $idKeys);
                $hasValidId = ($firstId !== null);
                if ($hasValidId) {
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

                if (!$hasValidId || count($list) < $pageSize || $page >= 10) {
                    break;
                }

                $page++;
            }

            $this->finalizeDiagnostic($items, $firstItemKeys, $rawSample);
        } catch (\Throwable $e) {
            $this->recordDiagnosticError($e, $rawSample);
        }

        return $items;
    }

    private function fetchCatalog(
        int $process,
        array $idKeys,
        array $descriptionKeys,
        mixed &$debugRawStore,
        ?string $companyOverride = null,
        int $maxPageSize = 100
    ): array {
        $items = [];
        $page = 0;
        $pageSize = $maxPageSize;
        $client = $companyOverride !== null ? $this->buildClient($companyOverride) : $this->client;
        $seenFirstIds = [];
        $firstItemKeys = [];
        $rawSample = null;

        $this->resetDiagnostic($process, $companyOverride, $idKeys);

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
                    $rawSample = $this->sampleRaw($data);
                }

                $list = $data['resultData']['list'] ?? $data['data']['resultData']['list'] ?? [];
                if (empty($list)) {
                    break;
                }

                if ($page === 0 && is_array($list[0] ?? null)) {
                    $firstItemKeys = array_keys($list[0]);
                }

                $firstId = $this->firstAvailableValue($list[0], $idKeys);
                $hasValidId = ($firstId !== null);
                if ($hasValidId) {
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

                if (!$hasValidId || count($list) < $pageSize || $page >= 10) {
                    break;
                }

                $page++;
            }

            $this->finalizeDiagnostic($items, $firstItemKeys, $rawSample);
        } catch (\Throwable $e) {
            $this->recordDiagnosticError($e, $rawSample);
        }

        return $items;
    }

    private function resetDiagnostic(int $process, ?string $companyOverride, array $idKeys): void
    {
        $this->debugLastDiagnostic = [
            'outcome' => 'pending',
            'process' => $process,
            'company_header' => $companyOverride !== null ? $companyOverride : $this->currentCompanyHeader(),
            'url' => $this->apiUrl,
            'items_count' => 0,
            'error_class' => null,
            'error_message' => null,
            'http_code' => null,
            'raw_sample' => null,
            'id_keys' => $idKeys,
            'first_item_keys' => [],
        ];
    }

    private function finalizeDiagnostic(array $items, array $firstItemKeys, ?string $rawSample): void
    {
        $this->debugLastDiagnostic['items_count'] = count($items);
        $this->debugLastDiagnostic['first_item_keys'] = $firstItemKeys;
        $this->debugLastDiagnostic['raw_sample'] = $rawSample;
        $this->debugLastDiagnostic['outcome'] = count($items) > 0 ? 'ok' : 'empty';
    }

    private function recordDiagnosticError(\Throwable $e, ?string $rawSample): void
    {
        $this->debugLastDiagnostic['outcome'] = 'error';
        $this->debugLastDiagnostic['error_class'] = (new \ReflectionClass($e))->getShortName();
        $this->debugLastDiagnostic['error_message'] = $e->getMessage();
        $this->debugLastDiagnostic['http_code'] = method_exists($e, 'getCode') ? (int) $e->getCode() : null;
        $this->debugLastDiagnostic['raw_sample'] = $rawSample;
    }

    private function sampleRaw(mixed $data): string
    {
        $encoded = json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
        if ($encoded === false) {
            return '';
        }

        return substr($encoded, 0, 500);
    }

    private function currentCompanyHeader(): string
    {
        return $this->defaultCompanyHeader;
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
