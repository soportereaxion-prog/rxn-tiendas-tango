<?php

declare(strict_types=1);

namespace App\Modules\Tango;

use App\Infrastructure\Http\ApiClient;
use RuntimeException;

class TangoApiClient
{
    private ApiClient $client;

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
            // Un ping ligero a Artículos con Top 1
            $this->getArticulos(1, 1);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Agrupa y extrae un Maestro de Depósitos deduciéndolo de Process 2941.
     */
    public function getMaestroDepositos(): array
    {
        $depositos = [];
        try {
            $data = $this->client->get('/Api/Get', ['process' => 2941]);
            if (!empty($data['data']['list'])) {
                foreach ($data['data']['list'] as $item) {
                    // Hunt for Code and Description regardless of exact casing/naming
                    $id = null;
                    $desc = null;
                    
                    foreach ($item as $k => $v) {
                        $upKey = strtoupper($k);
                        if ($id === null && (str_contains($upKey, 'COD') || str_contains($upKey, 'ID'))) {
                            $id = $v;
                        } elseif ($desc === null && (str_contains($upKey, 'DESC') || str_contains($upKey, 'NOMB'))) {
                            $desc = $v;
                        }
                    }
                    
                    if ($id !== null) {
                        // Crucial: agresivo trim() y cast explícito a string porque Tango inyecta paddings (" 1", "00 ")
                        $cleanId = trim((string)$id);
                        // Si el valor numérico coincide, eliminar ceros a la izquierda (01 -> 1) si es el caso, 
                        // pero es más seguro usar ltrim solo si sabemos que es numérico. Lo dejamos como trim limpio.
                        if (is_numeric($cleanId)) {
                            $cleanId = (string)($cleanId + 0); // forza conversion "01" -> "1", "00" -> "0"
                        }
                        $depositos[$cleanId] = trim((string)($desc ?? ("Depósito " . $cleanId)));
                    }
                }
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
        try {
            $data = $this->client->get('/Api/Get', ['process' => 984]);
            if (!empty($data['data']['list'])) {
                foreach ($data['data']['list'] as $item) {
                    $id = null;
                    $desc = null;
                    
                    foreach ($item as $k => $v) {
                        $upKey = strtoupper($k);
                        if ($id === null && (str_contains($upKey, 'COD') || str_contains($upKey, 'ID') || str_contains($upKey, 'NRO'))) {
                            $id = $v;
                        } elseif ($desc === null && (str_contains($upKey, 'DESC') || str_contains($upKey, 'NOMB'))) {
                            $desc = $v;
                        }
                    }

                    if ($id !== null) {
                        $cleanId = trim((string)$id);
                        if (is_numeric($cleanId)) {
                            $cleanId = (string)($cleanId + 0); // "02" -> "2"
                        }
                        $listas[$cleanId] = trim((string)($desc ?? ("Lista " . $cleanId)));
                    }
                }
            }
        } catch (\Exception $e) {}
        return $listas;
    }
}
