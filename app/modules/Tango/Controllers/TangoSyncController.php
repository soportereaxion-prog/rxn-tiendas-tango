<?php
declare(strict_types=1);
namespace App\Modules\Tango\Controllers;

use App\Core\Controller;
use App\Modules\Auth\AuthService;
use App\Modules\Tango\Services\TangoSyncService;

class TangoSyncController extends Controller
{
    private TangoSyncService $syncService;

    public function __construct()
    {
        $this->syncService = new TangoSyncService();
    }

    public function syncArticulos(): void
    {
        AuthService::requireLogin();
        
        try {
            $stats = $this->syncService->syncArticulos();
            
            // Renderizamos un array en pantalla temporalmente para la UI operativa de esta validacion
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Sincronización de artículos finalizada.',
                'stats' => $stats
            ]);
            
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
