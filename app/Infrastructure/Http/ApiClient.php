<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use RuntimeException;

class ApiClient
{
    private string $baseUrl;
    private array $defaultHeaders;

    public function __construct(string $baseUrl, array $defaultHeaders = [])
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->defaultHeaders = $defaultHeaders;
    }

    public function get(string $endpoint, array $queryParams = []): array
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }
        return $this->request('GET', $url);
    }

    public function post(string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        return $this->request('POST', $url, $data);
    }

    private function request(string $method, string $url, array $data = []): array
    {
        $ch = curl_init();
        
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $this->defaultHeaders,
            CURLOPT_TIMEOUT => 30, // 30 segundos timeout
            CURLOPT_SSL_VERIFYPEER => false // En prod cambiar a true si el config central lo requiere
        ];

        if ($method === 'POST' && !empty($data)) {
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
            $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        }

        curl_setopt_array($ch, $options);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("Error en petición de Red REST [$method $url]: $error");
        }

        $decodedData = json_decode((string)$response, true);

        if ($httpCode >= 400) {
            $errMessage = is_array($decodedData) && isset($decodedData['message']) 
                          ? $decodedData['message'] 
                          : "HTTP Error $httpCode";
            throw new RuntimeException("Fallo en Integración Externa: $errMessage", $httpCode);
        }

        return [
            'status' => $httpCode,
            'data' => $decodedData
        ];
    }
}
