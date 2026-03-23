<?php

declare(strict_types=1);

namespace App\Modules\Tango;

use App\Infrastructure\Http\ApiClient;
use RuntimeException;

class TangoApiClient
{
    private ApiClient $client;

    public function __construct(string $apiUrl, string $accessToken)
    {
        if (empty($apiUrl) || empty($accessToken)) {
            throw new \App\Infrastructure\Exceptions\ConfigurationException("Configuración de integración Tango incompleta para este entorno operativo.");
        }

        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json'
        ];

        $this->client = new ApiClient($apiUrl, $headers);
    }

    /**
     * Devuelve el listado crudo de articulos desde la API
     */
    public function getArticulos(int $page = 1): array
    {
        $endpoint = 'api/v1/articulos'; // Placeholder contract
        return $this->client->get($endpoint, ['page' => $page]);
    }
}
