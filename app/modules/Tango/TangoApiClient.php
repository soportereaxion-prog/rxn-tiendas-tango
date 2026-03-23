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
}
