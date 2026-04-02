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
        $endpoint = 'Create?process=19845';
        
        // POST request a Connect
        return $this->client->post($endpoint, $payload);
    }

    /**
     * Consulta process=87 para obtener el ID_STA11 de un artículo usando su código/SKU (COD_STA11)
     */
    public function getArticleIdByCode(string $codigoArticulo): ?int
    {
        $filtroSql = "WHERE COD_STA11 = '" . str_replace("'", "''", $codigoArticulo) . "'";
        $endpoint = "GetByFilter?process=87&view=&filtroSql=" . rawurlencode($filtroSql);
        
        try {
            $response = $this->client->get($endpoint);
            $list = $response['resultData']['list'] ?? $response['data']['resultData']['list'] ?? $response['data']['list'] ?? [];
            if (isset($list[0]['ID_STA11'])) {
                return (int) $list[0]['ID_STA11'];
            }
            throw new \RuntimeException('El nodo ID_STA11 no se encontró en la respuesta: ' . json_encode($response, JSON_UNESCAPED_UNICODE));
        } catch (\Exception $e) {
            throw new \RuntimeException("Error resolviendo ID_STA11 para {$codigoArticulo}: " . $e->getMessage());
        }
    }
}
