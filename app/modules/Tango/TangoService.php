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
    private int $syncAmount = 50;

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
        $apiUrl = $config->tango_api_url ?? null; 
        $clientKey = $config->tango_connect_key ?? null;
        $apiToken = $config->tango_connect_token ?? null;
        $companyId = $config->tango_connect_company_id ?? null;
        
        $this->syncAmount = $config->cantidad_articulos_sync ?? 50;

        if (empty($apiToken) || empty($companyId)) {
            throw new \App\Infrastructure\Exceptions\ConfigurationException("Módulos API Tango Incompletos: Claves mandatorias (ApiAuthorization Token, Company ID) no encontradas para la Empresa $empresaId.");
        }

        // Armar el Host a base de la convención de Connect si no se forzó una variante local y si la llave existe
        if (empty($apiUrl) && !empty($clientKey)) {
            $keyDash = str_replace('/', '-', $clientKey);
            $apiUrl = "https://{$keyDash}.connect.axoft.com/Api";
        } elseif (empty($apiUrl)) {
            throw new \App\Infrastructure\Exceptions\ConfigurationException("No se estableció una URL Base ni una Llave (Key) para derivar el Host de Connect.");
        }

        // 3. Levantar Cliente Rest inyectando config estricta por empresa
        $this->apiClient = new TangoApiClient($apiUrl, $apiToken, $companyId, $clientKey);
    }

    /**
     * Orquesta la petición hacia el adaptador y procesa entidades en un Data Transfer Object.
     */
    public function fetchArticulos(int $page = 1): TangoResponseDTO
    {
        $dto = new TangoResponseDTO();
        
        try {
            // 1. Llamar al cliente HTTP
            $response = $this->apiClient->getArticulos($page, $this->syncAmount);

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
