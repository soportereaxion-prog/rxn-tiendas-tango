<?php

declare(strict_types=1);

namespace App\Modules\Tango;

use App\Core\Context;
use App\Modules\EmpresaConfig\EmpresaConfigService;
use App\Modules\Tango\DTOs\TangoResponseDTO;
use RuntimeException;

class TangoService
{
    private TangoApiClient $apiClient;

    public function __construct()
    {
        // 1. Obtener la empresa activa desde el Contexto Central
        $empresaId = Context::getEmpresaId();
        if (!$empresaId) {
            throw new \App\Infrastructure\Exceptions\ConfigurationException("Contexto multiempresa cerrado. No se puede instanciar el tunel Tango.");
        }

        // 2. Extraer parámetros de conexión específicos para esta empresa.
        $configService = new \App\Modules\EmpresaConfig\EmpresaConfigService();
        $config = $configService->getConfig();

        // CONTRATO OBLIGATORIO DE CONFIGURACIÓN POR EMPRESA
        // Llaves base esperadas en EmpresaConfig derivadas en DB:
        // - tango_api_url (string) Endpoint Base URL
        // - tango_connect_key (string) Client ID Key
        // - tango_connect_token (string) Bearer Access Token
        
        $apiUrl = $config->tango_api_url ?? null; 
        $clientKey = $config->tango_connect_key ?? null;
        $apiToken = $config->tango_connect_token ?? null;

        if (empty($apiUrl) || empty($apiToken) || empty($clientKey)) {
            throw new \App\Infrastructure\Exceptions\ConfigurationException("Módulos API Tango Incompletos: Claves mandatorias (Token, Key o URL) no encontradas para la Empresa $empresaId.");
        }

        // 3. Levantar Cliente Rest inyectando config estricta por empresa
        $this->apiClient = new TangoApiClient($apiUrl, $apiToken, $clientKey);
    }

    /**
     * Orquesta la petición hacia el adaptador y procesa entidades en un Data Transfer Object.
     */
    public function fetchArticulos(int $page = 1): TangoResponseDTO
    {
        $dto = new TangoResponseDTO();
        
        try {
            // 1. Llamar al cliente HTTP
            $response = $this->apiClient->getArticulos($page);

            // 2. Mapear
            $dto->isSuccess = ($response['status'] >= 200 && $response['status'] < 300);
            $dto->payload = $response['data'] ?? [];

        } catch (\Exception $e) {
            $dto->isSuccess = false;
            $dto->errorMessage = $e->getMessage();
        }

        return $dto;
    }
}
