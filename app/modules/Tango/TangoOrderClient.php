<?php

declare(strict_types=1);

namespace App\Modules\Tango;

use App\Infrastructure\Http\ApiClient;
use RuntimeException;

class TangoOrderClient
{
    private ApiClient $client;

    public function __construct(string $apiUrl, string $accessToken, string $companyId, ?string $clientKey = null)
    {
        if (empty($apiUrl) || empty($accessToken) || empty($companyId)) {
            throw new \App\Infrastructure\Exceptions\ConfigurationException("Configuración HTTP de integración Tango incompleta para este entorno operativo.");
        }

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
     * Envía el payload del pedido al process 19845
     */
    public function sendOrder(array $payload): array
    {
        $endpoint = '/Api/Create?process=19845';
        
        // POST request a Connect
        return $this->client->post($endpoint, $payload);
    }
}
