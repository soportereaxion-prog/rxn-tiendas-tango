<?php
declare(strict_types=1);
namespace App\Modules\Tango\Services;

use App\Core\Context;
use App\Modules\Tango\TangoService;
use App\Modules\Tango\Repositories\TangoSyncLogRepository;
use App\Modules\Articulos\ArticuloRepository;
use App\Modules\Tango\Mappers\ArticuloMapper;

class TangoSyncService
{
    private TangoService $tangoService;
    private TangoSyncLogRepository $logRepo;
    private ArticuloRepository $articuloRepo;

    public function __construct()
    {
        // Instanciamos el túnel abstracto, que internamente ya valida credenciales por empresa.
        $this->tangoService = new TangoService();
        $this->logRepo = new TangoSyncLogRepository();
        $this->articuloRepo = new ArticuloRepository();
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

        // 1. Leer configuraciones por empresa
        $configService = new \App\Modules\EmpresaConfig\EmpresaConfigService();
        $config = $configService->getConfig();
        $lista1 = $config->lista_precio_1;
        $lista2 = $config->lista_precio_2;

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
                // Parseo defensivo y limpieza de strings como la API vieja de Tango estila (con espacios char15)
                $sku = trim((string)($item['COD_STA11'] ?? ''));
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
            return $stats;

        } catch (\Exception $e) {
            $this->logRepo->endLog($logId, $stats, 'ERROR', $e->getMessage());
            throw $e; 
        }
    }
}
