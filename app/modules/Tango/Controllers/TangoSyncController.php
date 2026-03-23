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
            
            \App\Core\Flash::set('success', 'Sincronización finalizada exitosamente.', $stats);
            header('Location: /rxnTiendasIA/public/mi-empresa/articulos');
            exit;
            
        } catch (\Exception $e) {
            \App\Core\Flash::set('danger', 'Error de Sincronización: ' . $e->getMessage());
            header('Location: /rxnTiendasIA/public/mi-empresa/articulos');
            exit;
        }
    }
}
