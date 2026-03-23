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
            if (isset($items['Data'])) $items = $items['Data']; 
            if (isset($items['data'])) $items = $items['data'];

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
            // 5. Concluir Trazabilidad en Rojo guardando Error Stack
            $this->logRepo->endLog($logId, $stats, 'ERROR', $e->getMessage());
            throw $e; 
        }
    }
}
