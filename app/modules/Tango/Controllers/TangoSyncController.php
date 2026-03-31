<?php
declare(strict_types=1);
namespace App\Modules\Tango\Controllers;

use App\Core\Controller;
use App\Modules\Auth\AuthService;
use App\Modules\Tango\Services\TangoSyncService;

class TangoSyncController extends Controller
{
    public function syncClientes(): void
    {
        AuthService::requireLogin();
        $syncService = $this->resolveService();
        $redirectPath = '/rxnTiendasIA/public/mi-empresa/crm/clientes';
        
        try {
            $stats = $syncService->syncClientes();
            
            \App\Core\Flash::set('success', 'Sincronización de Clientes finalizada exitosamente.', $stats);
            header('Location: ' . $redirectPath);
            exit;
            
        } catch (\Exception $e) {
            \App\Core\Flash::set('danger', 'Error de Sincronización de Clientes: ' . $e->getMessage());
            header('Location: ' . $redirectPath);
            exit;
        }
    }

    public function syncArticulos(): void
    {
        AuthService::requireLogin();
        $syncService = $this->resolveService();
        $redirectPath = $this->redirectPath();
        
        try {
            $stats = $syncService->syncArticulos();
            
            \App\Core\Flash::set('success', 'Sincronización finalizada exitosamente.', $stats);
            header('Location: ' . $redirectPath);
            exit;
            
        } catch (\Exception $e) {
            \App\Core\Flash::set('danger', 'Error de Sincronización: ' . $e->getMessage());
            header('Location: ' . $redirectPath);
            exit;
        }
    }

    public function syncTodo(): void
    {
        AuthService::requireLogin();
        $syncService = $this->resolveService();
        $redirectPath = $this->redirectPath();

        try {
            $stats = $syncService->syncTodo();
            $etapas = $stats['etapas'] ?? [];

            $mensaje = sprintf(
                'Sincronización total completada. Artículos: %d nuevos / %d actualizados. Precios: %d actualizados. Stock: %d actualizados.',
                (int) ($etapas['articulos']['insertados'] ?? 0),
                (int) ($etapas['articulos']['actualizados'] ?? 0),
                (int) ($etapas['precios']['actualizados'] ?? 0),
                (int) ($etapas['stock']['actualizados'] ?? 0)
            );

            \App\Core\Flash::set('success', $mensaje, $stats);
            header('Location: ' . $redirectPath);
            exit;

        } catch (\Exception $e) {
            \App\Core\Flash::set('danger', 'Error de Sincronización Total: ' . $e->getMessage());
            header('Location: ' . $redirectPath);
            exit;
        }
    }

    public function syncPrecios(): void
    {
        AuthService::requireLogin();
        $syncService = $this->resolveService();
        $redirectPath = $this->redirectPath();
        
        try {
            $stats = $syncService->syncPrecios();
            
            \App\Core\Flash::set('success', 'Sincronización de Precios finalizada exitosamente.', $stats);
            header('Location: ' . $redirectPath);
            exit;
            
        } catch (\Exception $e) {
            \App\Core\Flash::set('danger', 'Error de Sincronización de Precios: ' . $e->getMessage());
            header('Location: ' . $redirectPath);
            exit;
        }
    }

    public function syncStock(): void
    {
        AuthService::requireLogin();
        $syncService = $this->resolveService();
        $redirectPath = $this->redirectPath();
        
        try {
            $stats = $syncService->syncStock();
            
            \App\Core\Flash::set('success', 'Sincronización de Stock finalizada exitosamente.', $stats);
            header('Location: ' . $redirectPath);
            exit;
            
        } catch (\Exception $e) {
            \App\Core\Flash::set('danger', 'Error de Sincronización de Stock: ' . $e->getMessage());
            header('Location: ' . $redirectPath);
            exit;
        }
    }

    private function resolveService(): TangoSyncService
    {
        return $this->resolveArea() === 'crm'
            ? TangoSyncService::forCrm()
            : new TangoSyncService();
    }

    private function resolveArea(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        return str_contains($uri, '/mi-empresa/crm/') ? 'crm' : 'tiendas';
    }

    private function redirectPath(): string
    {
        return $this->resolveArea() === 'crm'
            ? '/rxnTiendasIA/public/mi-empresa/crm/articulos'
            : '/rxnTiendasIA/public/mi-empresa/articulos';
    }
}
