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
            throw new RuntimeException("Contexto multiempresa cerrado. No se puede instanciar el tunel Tango.");
        }

        // 2. Extraer parámetros de conexión específicos para esta empresa.
        // Utilizamos el gestor de config base que inyecta su propio Guard en la base de datos
        $configService = new EmpresaConfigService();
        $config = $configService->getConfig();

        // NOTA DE DEUDA TÉCNICA (DOCUMENTADA): 
        // Para una implementación real se deberán crear campos 'tango_api_url' y 'tango_api_token' 
        // en la tabla 'empresa_config' de MariaDB. Por ahora emularemos unas keys dummy para establecer 
        // el contrato arquitectonico de validacion y arranque limpio.
        $apiUrl = "https://api.axoft.com/demo"; 
        $apiToken = "rxn-tango-dummy-token-$empresaId";

        // 3. Levantar Cliente Rest inyectando config estricta por empresa
        $this->apiClient = new TangoApiClient($apiUrl, $apiToken);
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

        } catch (RuntimeException $e) {
            $dto->isSuccess = false;
            $dto->errorMessage = $e->getMessage();
        }

        return $dto;
    }
}
