<?php

declare(strict_types=1);

namespace App\Modules\Tango;

use App\Infrastructure\Http\ApiClient;
use RuntimeException;

class TangoApiClient
{
    private ApiClient $client;
    public $debugLastRawDepositos = null;
    public $debugLastRawListas = null;
    public $debugLastHttpRequest = [];

    public function __construct(string $apiUrl, string $accessToken, string $companyId, ?string $clientKey = null)
    {
        if (empty($apiUrl) || empty($accessToken) || empty($companyId)) {
            throw new \App\Infrastructure\Exceptions\ConfigurationException("Configuración HTTP de integración Tango incompleta para este entorno operativo.");
        }

        // Estándar puro demandado por Connect
        $headers = [
            'ApiAuthorization: ' . $accessToken,
            'Company: ' . $companyId,
            'Accept: application/json',
            'Content-Type: application/json'
        ];
        
        if ($clientKey) {
            $headers[] = 'Client-Id: ' . $clientKey;
        }

        $this->client = new ApiClient($apiUrl, $headers);
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
        $depositos = [];
        $page = 0;
        $pageSize = 100;
        
        try {
            while (true) {
                $data = $this->client->get('Get', [
                    'process' => 2941,
                    'pageSize' => $pageSize,
                    'pageIndex' => $page,
                    'view' => ''
                ]);
                
                if ($page === 0) {
                    $this->debugLastRawDepositos = $data;
                    $this->debugLastHttpRequest = $this->client->debugLastRequest ?? [];
                }

                $list = $data['data']['resultData']['list'] ?? [];
                if (empty($list)) {
                    break; // Fin de resultados
                }
                
                foreach ($list as $item) {
                    $id = $item['ID_STA22'] ?? null;
                    $desc = $item['COD_DESCRIP'] ?? $item['NOMBRE_SUC'] ?? null;
                    
                    if ($id !== null) {
                        $depositos[(string)$id] = (string)$desc;
                    }
                }
                
                if (count($list) < $pageSize) {
                    break; // Última página
                }
                $page++;
            }
        } catch (\Exception $e) {}
        
        return $depositos;
    }

    /**
     * Agrupa y extrae identificadores de Listas de Precios de Process 984.
     */
    public function getMaestroListasPrecio(): array
    {
        $listas = [];
        $page = 0;
        $pageSize = 100;

        try {
            while (true) {
                $data = $this->client->get('Get', [
                    'process' => 984,
                    'pageSize' => $pageSize,
                    'pageIndex' => $page,
                    'view' => ''
                ]);

                if ($page === 0) {
                    $this->debugLastRawListas = $data;
                }

                $list = $data['data']['resultData']['list'] ?? [];
                if (empty($list)) {
                    break;
                }
                
                foreach ($list as $item) {
                    $id = $item['ID_GVA10'] ?? null;
                    $desc = $item['COD_DESCRIP'] ?? $item['NOMBRE_LIS'] ?? null;

                    if ($id !== null) {
                        $listas[(string)$id] = (string)$desc;
                    }
                }

                if (count($list) < $pageSize) {
                    break;
                }
                $page++;
            }
        } catch (\Exception $e) {}
        
        return $listas;
    }
}
