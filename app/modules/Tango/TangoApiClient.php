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
     * Agrupa y extrae un Maestro de Depósitos deduciéndolo de los reportes de Stock.
     */
    public function getMaestroDepositos(): array
    {
        $depositos = [];
        try {
            $stockData = $this->getStock(1, 2000); // Muestra amplia
            if (!empty($stockData['data']['list'])) {
                foreach ($stockData['data']['list'] as $item) {
                    if (isset($item['ID_STA22'])) {
                        $id = (int)$item['ID_STA22'];
                        $desc = $item['DESCRIPCION_DEPOSITO'] ?? ("Depósito #" . $id);
                        if (!isset($depositos[$id])) {
                            $depositos[$id] = $desc;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Retorna vacío si falla la extracción
        }
        return $depositos;
    }

    /**
     * Agrupa y extrae identificadores de Listas de Precios.
     */
    public function getMaestroListasPrecio(): array
    {
        $listas = [];
        try {
            $precioData = $this->getPrecios(1, 1000);
            if (!empty($precioData['data']['list'])) {
                foreach ($precioData['data']['list'] as $item) {
                    if (isset($item['NRO_DE_LIS'])) {
                        $id = (int)$item['NRO_DE_LIS'];
                        if (!isset($listas[$id])) {
                            $listas[$id] = "Lista Matriz #" . $id; 
                            // Tango process 20091 carece de nombres comerciales de lista, se deducen numeralmente.
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Fallback silencioso
        }
        return $listas;
    }
}
