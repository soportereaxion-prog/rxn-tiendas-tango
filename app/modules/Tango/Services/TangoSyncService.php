<?php
declare(strict_types=1);
namespace App\Modules\Tango\Services;

use App\Core\Context;
use App\Modules\EmpresaConfig\EmpresaConfigService;
use App\Modules\EmpresaConfig\EmpresaConfig;
use App\Modules\Tango\TangoService;
use App\Modules\Tango\Repositories\TangoSyncLogRepository;
use App\Modules\Articulos\ArticuloRepository;
use App\Modules\Tango\Mappers\ArticuloMapper;

class TangoSyncService
{
    private TangoService $tangoService;
    private TangoSyncLogRepository $logRepo;
    private ArticuloRepository $articuloRepo;
    private EmpresaConfigService $configService;
    private string $area;

    public function __construct(string $area = 'tiendas')
    {
        $this->area = $this->normalizeArea($area);
        // Instanciamos el túnel abstracto, que internamente ya valida credenciales por empresa.
        $this->tangoService = new TangoService($this->area);
        $this->logRepo = new TangoSyncLogRepository();
        $this->articuloRepo = $this->area === 'crm' ? ArticuloRepository::forCrm() : new ArticuloRepository();
        $this->configService = EmpresaConfigService::forArea($this->area);
    }

    public static function forCrm(): self
    {
        return new self('crm');
    }

    public function syncClientes(): array
    {
        $empresaId = Context::getEmpresaId();
        if (!$empresaId) {
            throw new \RuntimeException("Sincronización abortada: Sin empresa activa en el contexto temporal.");
        }

        $logId = $this->logRepo->startLog((int)$empresaId, 'CLIENTES_CRM');
        $stats = ['recibidos' => 0, 'insertados' => 0, 'actualizados' => 0, 'omitidos' => 0];

        try {
            $page = 1;
            do {
                $dto = $this->tangoService->fetchClientes($page);

                if (!$dto->isSuccess) {
                    throw new \App\Infrastructure\Exceptions\HttpException("Respuesta fallida/rebotada desde Tango: " . $dto->errorMessage);
                }

                $items = is_array($dto->payload) ? $dto->payload : [];
                if (isset($items['resultData']['list']) && is_array($items['resultData']['list'])) {
                    $items = $items['resultData']['list'];
                } elseif (isset($items['Data']) && is_array($items['Data'])) {
                    $items = $items['Data'];
                } elseif (isset($items['data']) && is_array($items['data'])) {
                    $items = $items['data'];
                }

                $count = count($items);
                $stats['recibidos'] += $count;

                $crmClienteRepo = new \App\Modules\CrmClientes\CrmClienteRepository();

                foreach ($items as $item) {
                    if (!is_array($item)) {
                        $stats['omitidos']++;
                        continue;
                    }

                    $mapped = \App\Modules\Tango\Mappers\CrmClienteMapper::fromConnectJson($item);
                    if (!$mapped) {
                        $stats['omitidos']++;
                        continue;
                    }

                    $res = $crmClienteRepo->upsertFromTango((int)$empresaId, $mapped);

                    if ($res === 'inserted') {
                        $stats['insertados']++;
                    } elseif ($res === 'updated') {
                        $stats['actualizados']++;
                    } elseif ($res === 'skipped') {
                        $stats['omitidos']++;
                    }
                }
                
                // Paginación si vinieron exactamente la cantidad máxima (ej. 50/1000)
                // TangoService -> fetchClientes(page, syncAmount)
                $page++;
            } while ($count > 0 && $page < 100); // Límite de seguridad 100 páginas

            $this->logRepo->endLog($logId, $stats, 'SUCCESS');
            return $stats;

        } catch (\Exception $e) {
            $this->logRepo->endLog($logId, $stats, 'ERROR', $e->getMessage());
            throw $e;
        }
    }

    public function syncArticulos(): array
    {
        $empresaId = Context::getEmpresaId();
        if (!$empresaId) {
            throw new \RuntimeException("Sincronización abortada: Sin empresa activa en el contexto temporal.");
        }

        // 1. Iniciar registro de trazabilidad
        $logId = $this->logRepo->startLog((int)$empresaId, 'ARTICULOS');
        $stats = ['recibidos' => 0, 'insertados' => 0, 'actualizados' => 0, 'omitidos' => 0];

        try {
            // 2. Extraer Payload mediante el DTO
            $dto = $this->tangoService->fetchArticulos();

            if (!$dto->isSuccess) {
                throw new \App\Infrastructure\Exceptions\HttpException("Respuesta fallida/rebotada desde Tango: " . $dto->errorMessage);
            }

            // Manejo dinámico del Envelope de respuesta según el estándar paginado de Connect
            $items = is_array($dto->payload) ? $dto->payload : [];
            if (isset($items['resultData']['list']) && is_array($items['resultData']['list'])) {
                $items = $items['resultData']['list'];
            } elseif (isset($items['Data']) && is_array($items['Data'])) {
                $items = $items['Data']; 
            } elseif (isset($items['data']) && is_array($items['data'])) {
                $items = $items['data'];
            }

            $stats['recibidos'] = count($items);

            // 3. Iterar, Mapear e Insertar robustamente
            foreach ($items as $item) {
                if (!is_array($item)) {
                    $stats['omitidos']++;
                    continue;
                }

                $articulo = ArticuloMapper::fromConnectJson($item, (int)$empresaId);
                
                if (!$articulo) {
                    $stats['omitidos']++;
                    continue;
                }

                $res = $this->articuloRepo->upsert($articulo);
                
                // PDO rowCount = 1 (Insertado fresco), = 2 (Encontrado via Duplicate Key y modificado). = 0 (Sin cambios reales).
                if ($res['affected_rows'] === 1) {
                    $stats['insertados']++;
                } elseif ($res['affected_rows'] === 2) {
                    $stats['actualizados']++;
                } else {
                    $stats['actualizados']++; // Trackeado silente para feedback visual amigable
                }
            }

            // 4. Concluir Trazabilidad en Verde
            $this->logRepo->endLog($logId, $stats, 'SUCCESS');
            $this->clearAreaCaches((int) $empresaId);
            return $stats;

        } catch (\Exception $e) {
            // 5. Concluir Trazabilidad en Rojo guardando Error Stack y cerrando compuertas a Inserts Falsos/Mockeados.
            $this->logRepo->endLog($logId, $stats, 'ERROR', $e->getMessage());
            throw $e; 
        }
    }

    public function syncPrecios(): array
    {
        $empresaId = Context::getEmpresaId();
        if (!$empresaId) {
            throw new \RuntimeException("Sincronización abortada: Sin empresa activa en sesión.");
        }

        $config = $this->getEmpresaConfig();

        return $this->syncPreciosWithConfig((int) $empresaId, $config);
    }

    public function syncStock(): array
    {
        $empresaId = Context::getEmpresaId();
        if (!$empresaId) {
            throw new \RuntimeException("Sincronización abortada: Sin empresa activa en sesión.");
        }

        $config = $this->getEmpresaConfig();

        return $this->syncStockWithConfig((int) $empresaId, $config);
    }

    public function syncTodo(): array
    {
        $empresaId = Context::getEmpresaId();
        if (!$empresaId) {
            throw new \RuntimeException("Sincronización abortada: Sin empresa activa en sesión.");
        }

        $config = $this->getEmpresaConfig();

        $articulos = $this->syncArticulos();
        $precios = $this->syncPreciosWithConfig((int) $empresaId, $config);
        $stock = $this->syncStockWithConfig((int) $empresaId, $config);

        return [
            'recibidos' => (int) ($articulos['recibidos'] ?? 0) + (int) ($precios['recibidos'] ?? 0) + (int) ($stock['recibidos'] ?? 0),
            'insertados' => (int) ($articulos['insertados'] ?? 0),
            'actualizados' => (int) ($articulos['actualizados'] ?? 0) + (int) ($precios['actualizados'] ?? 0) + (int) ($stock['actualizados'] ?? 0),
            'omitidos' => (int) ($articulos['omitidos'] ?? 0) + (int) ($precios['omitidos'] ?? 0) + (int) ($stock['omitidos'] ?? 0),
            'sin_match' => (int) ($precios['sin_match'] ?? 0) + (int) ($stock['sin_match'] ?? 0),
            'etapas' => [
                'articulos' => $articulos,
                'precios' => $precios,
                'stock' => $stock,
            ],
        ];
    }

    private function syncPreciosWithConfig(int $empresaId, object $config): array
    {
        $lista1 = $config->lista_precio_1 ?? null;
        $lista2 = $config->lista_precio_2 ?? null;

        if (empty($lista1) && empty($lista2)) {
            throw new \RuntimeException("Sincronización abortada: No hay listas de precios configuradas (lista_precio_1 / lista_precio_2) para esta Empresa.");
        }

        // 2. Iniciar registro de trazabilidad
        $logId = $this->logRepo->startLog((int)$empresaId, 'PRECIOS');
        $stats = ['recibidos' => 0, 'actualizados' => 0, 'omitidos' => 0, 'sin_match' => 0];

        try {
            $dto = $this->tangoService->fetchPrecios();

            if (!$dto->isSuccess) {
                throw new \App\Infrastructure\Exceptions\HttpException("Respuesta fallida/rebotada desde Tango Process 20091: " . $dto->errorMessage);
            }

            $items = is_array($dto->payload) ? $dto->payload : [];
            if (isset($items['resultData']['list']) && is_array($items['resultData']['list'])) {
                $items = $items['resultData']['list'];
            } elseif (isset($items['Data']) && is_array($items['Data'])) {
                $items = $items['Data']; 
            } elseif (isset($items['data']) && is_array($items['data'])) {
                $items = $items['data'];
            }

            $stats['recibidos'] = count($items);

            foreach ($items as $item) {
                if (!is_array($item)) {
                    $stats['omitidos']++;
                    continue;
                }

                // Parseo defensivo sin limpiar los espacios originales del SKU
                $sku = (string)($item['COD_STA11'] ?? '');
                $nroLista = (string)($item['NRO_DE_LIS'] ?? '');
                $precioBruto = $item['PRECIO'] ?? null;

                if ($sku === '' || $nroLista === '' || !is_numeric($precioBruto)) {
                    $stats['omitidos']++; // Datos insuficientes (No se inventan)
                    continue;
                }

                $columnaDestino = null;
                if ($lista1 !== null && $lista1 !== '' && $nroLista === (string)$lista1) {
                    $columnaDestino = 'precio_lista_1';
                } elseif ($lista2 !== null && $lista2 !== '' && $nroLista === (string)$lista2) {
                    $columnaDestino = 'precio_lista_2';
                }

                if (!$columnaDestino) {
                    $stats['omitidos']++; 
                    continue; // El precio bajado de Tango pertenece a una lista que no fue mapeada en Config Local
                }

                // Ejecutar macheo silencioso via SQL Update (Afecta solo a productos pre-existentes localmente)
                $affected = $this->articuloRepo->updatePrecioListas($sku, (float)$precioBruto, $columnaDestino, (int)$empresaId);
                
                if ($affected > 0) {
                    $stats['actualizados']++;
                } else {
                    $stats['sin_match']++; 
                }
            }

            // 4. Concluir Trazabilidad en Verde
            $this->logRepo->endLog($logId, $stats, 'SUCCESS');
            $this->clearAreaCaches((int) $empresaId);
            return $stats;

        } catch (\Exception $e) {
            $this->logRepo->endLog($logId, $stats, 'ERROR', $e->getMessage());
            throw $e; 
        }
    }

    private function syncStockWithConfig(int $empresaId, object $config): array
    {
        $deposito = $config->deposito_codigo ?? null;

        if ($deposito === null || $deposito === '') {
            throw new \RuntimeException("Sincronización abortada: No hay Depósito (deposito_codigo) configurado para esta Empresa.");
        }

        $logId = $this->logRepo->startLog((int)$empresaId, 'STOCK');
        $stats = ['recibidos' => 0, 'actualizados' => 0, 'omitidos' => 0, 'sin_match' => 0];

        try {
            $dto = $this->tangoService->fetchStock();

            if (!$dto->isSuccess) {
                throw new \App\Infrastructure\Exceptions\HttpException("Respuesta fallida/rebotada desde Tango Process 17668: " . $dto->errorMessage);
            }

            $items = is_array($dto->payload) ? $dto->payload : [];
            if (isset($items['resultData']['list']) && is_array($items['resultData']['list'])) {
                $items = $items['resultData']['list'];
            } elseif (isset($items['Data']) && is_array($items['Data'])) {
                $items = $items['Data']; 
            } elseif (isset($items['data']) && is_array($items['data'])) {
                $items = $items['data'];
            }

            $stats['recibidos'] = count($items);

            foreach ($items as $item) {
                if (!is_array($item)) {
                    $stats['omitidos']++;
                    continue;
                }

                // Parseo defensivo
                $sku = (string)($item['COD_ARTICULO'] ?? '');
                $depositoId = (string)($item['ID_STA22'] ?? ''); // Payload Connect usa ID_STA22 como referencia
                $saldo = $item['SALDO_CONTROL_STOCK'] ?? null;

                if ($sku === '' || $depositoId === '' || !is_numeric($saldo)) {
                    $stats['omitidos']++; 
                    continue;
                }

                // El configurador de usuario es string de hasta 2 chars: macheamos contra el ID_STA22 string.
                // EJ: si ID_STA22 es "1" y configuraron "1", entra acá.
                if ($depositoId !== (string)$deposito) {
                    $stats['omitidos']++;
                    continue; // Stock es de otro depósito, no me interesa para esta tienda
                }

                // Ejecutar macheo silencioso via SQL Update 
                $affected = $this->articuloRepo->updateStock($sku, (float)$saldo, (int)$empresaId);
                
                if ($affected > 0) {
                    $stats['actualizados']++;
                } else {
                    $stats['sin_match']++; 
                }
            }

            $this->logRepo->endLog($logId, $stats, 'SUCCESS');
            $this->clearAreaCaches((int) $empresaId);
            return $stats;

        } catch (\Exception $e) {
            $this->logRepo->endLog($logId, $stats, 'ERROR', $e->getMessage());
            throw $e; 
        }
    }

    private function getEmpresaConfig(): EmpresaConfig
    {
        return $this->configService->getConfig();
    }

    private function clearAreaCaches(int $empresaId): void
    {
        if ($this->area !== 'tiendas') {
            return;
        }

        \App\Core\FileCache::clearPrefix("catalogo_empresa_{$empresaId}");
        \App\Core\FileCache::clearPrefix("categorias_store_empresa_{$empresaId}");
    }

    private function normalizeArea(string $area): string
    {
        return strtolower(trim($area)) === 'crm' ? 'crm' : 'tiendas';
    }
}
