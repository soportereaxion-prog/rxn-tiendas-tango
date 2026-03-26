<?php

declare(strict_types=1);

namespace App\Modules\Tango;

use App\Infrastructure\Http\ApiClient;
use RuntimeException;

class TangoApiClient
{
    private ApiClient $client;
    public $debugLastRawDepositos = null;
    public $debugLastRawListas = null;

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
    /**
     * Agrupa y extrae un Maestro de Depósitos deduciéndolo de Process 2941.
     * Incorpora paginación total para garantizar Catálogo Exhaustivo.
     */
    public function getMaestroDepositos(): array
    {
        $depositos = [];
        $page = 0;
        $pageSize = 100;
        
        try {
            while (true) {
                $data = $this->client->get('/Api/Get', [
                    'process' => 2941,
                    'pageSize' => $pageSize,
                    'pageIndex' => $page,
                    'view' => ''
                ]);
                
                if ($page === 0) {
                    $this->debugLastRawDepositos = $data;
                }

                if (empty($data['data']['list'])) {
                    break; // Fin de resultados
                }
                
                foreach ($data['data']['list'] as $item) {
                    $id = null;
                    $desc = null;
                    
                    // Prioridad 1: Nombres exactos
                    if (isset($item['CODIGO'])) $id = $item['CODIGO'];
                    elseif (isset($item['ID_STA22'])) $id = $item['ID_STA22'];
                    elseif (isset($item['COD_DEPOSITO'])) $id = $item['COD_DEPOSITO'];
                    
                    if (isset($item['DESCRIPCION'])) $desc = $item['DESCRIPCION'];
                    elseif (isset($item['DESCRIPCION_DEPOSITO'])) $desc = $item['DESCRIPCION_DEPOSITO'];
                    elseif (isset($item['NOMBRE'])) $desc = $item['NOMBRE'];
                    
                    // Prioridad 2: Heurística sucia si cambian el esquema
                    if ($id === null || $desc === null) {
                        foreach ($item as $k => $v) {
                            $upKey = strtoupper($k);
                            if ($id === null && (str_contains($upKey, 'COD') || str_contains($upKey, 'ID'))) {
                                $id = $v;
                            } elseif ($desc === null && (str_contains($upKey, 'DESC') || str_contains($upKey, 'NOMB'))) {
                                $desc = $v;
                            }
                        }
                    }
                    
                    if ($id !== null) {
                        $cleanId = trim((string)$id);
                        if (is_numeric($cleanId)) {
                            $cleanId = (string)($cleanId + 0); 
                        }
                        $depositos[$cleanId] = trim((string)($desc ?? ("Depósito " . $cleanId)));
                    }
                }
                
                if (count($data['data']['list']) < $pageSize) {
                    break; // Última página
                }
                $page++;
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
        $page = 0;
        $pageSize = 100;

        try {
            while (true) {
                $data = $this->client->get('/Api/Get', [
                    'process' => 984,
                    'pageSize' => $pageSize,
                    'pageIndex' => $page,
                    'view' => ''
                ]);

                if ($page === 0) {
                    $this->debugLastRawListas = $data;
                }

                if (empty($data['data']['list'])) {
                    break;
                }
                
                foreach ($data['data']['list'] as $item) {
                    $id = null;
                    $desc = null;
                    
                    if (isset($item['CODIGO'])) $id = $item['CODIGO'];
                    elseif (isset($item['NUMERO_DE_LISTA'])) $id = $item['NUMERO_DE_LISTA'];
                    elseif (isset($item['ID_GVA10'])) $id = $item['ID_GVA10'];
                    elseif (isset($item['NRO_DE_LIS'])) $id = $item['NRO_DE_LIS'];
                    
                    if (isset($item['DESCRIPCION'])) $desc = $item['DESCRIPCION'];
                    elseif (isset($item['NOMBRE'])) $desc = $item['NOMBRE'];
                    elseif (isset($item['NOMBRE_DE_LISTA'])) $desc = $item['NOMBRE_DE_LISTA'];
                    
                    if ($id === null || $desc === null) {
                        foreach ($item as $k => $v) {
                            $upKey = strtoupper($k);
                            if ($id === null && (str_contains($upKey, 'COD') || str_contains($upKey, 'ID') || str_contains($upKey, 'NRO'))) {
                                $id = $v;
                            } elseif ($desc === null && (str_contains($upKey, 'DESC') || str_contains($upKey, 'NOMB'))) {
                                $desc = $v;
                            }
                        }
                    }

                    if ($id !== null) {
                        $cleanId = trim((string)$id);
                        if (is_numeric($cleanId)) {
                            $cleanId = (string)($cleanId + 0); 
                        }
                        $listas[$cleanId] = trim((string)($desc ?? ("Lista " . $cleanId)));
                    }
                }

                if (count($data['data']['list']) < $pageSize) {
                    break;
                }
                $page++;
            }
        } catch (\Exception $e) {}
        
        return $listas;
    }
}
