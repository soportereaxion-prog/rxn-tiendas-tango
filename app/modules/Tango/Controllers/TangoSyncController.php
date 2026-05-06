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
        $this->verifyCsrfOrAbort();
        $syncService = $this->resolveService();
        // Respeta ?return= si viene de RxnSync u otro modulo; si no, vuelve al listado de clientes.
        $return = trim((string) ($_GET['return'] ?? $_POST['return'] ?? ''));
        $redirectPath = ($return !== '' && str_starts_with($return, '/mi-empresa/'))
            ? $return
            : '/mi-empresa/crm/clientes';

        try {
            $stats = $syncService->syncClientes();

            \App\Core\Flash::set('success', 'Sincronización de Clientes finalizada exitosamente.', $stats);
            header('Location: ' . $redirectPath);
            exit;

        } catch (\Throwable $e) {
            error_log('[TangoSyncController::syncClientes] ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            \App\Core\Flash::set('danger', 'Error al sincronizar clientes desde Tango. Revisá los logs del servidor.');
            header('Location: ' . $redirectPath);
            exit;
        }
    }

    public function syncArticulos(): void
    {
        AuthService::requireLogin();
        $this->verifyCsrfOrAbort();
        $syncService = $this->resolveService();
        $redirectPath = $this->redirectPath();

        try {
            $stats = $syncService->syncArticulos();

            \App\Core\Flash::set('success', 'Sincronización finalizada exitosamente.', $stats);
            header('Location: ' . $redirectPath);
            exit;

        } catch (\Throwable $e) {
            error_log('[TangoSyncController::syncArticulos] ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            \App\Core\Flash::set('danger', 'Error al sincronizar artículos desde Tango. Revisá los logs del servidor.');
            header('Location: ' . $redirectPath);
            exit;
        }
    }

    public function syncTodo(): void
    {
        AuthService::requireLogin();
        $this->verifyCsrfOrAbort();
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

        } catch (\Throwable $e) {
            error_log('[TangoSyncController::syncTodo] ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            \App\Core\Flash::set('danger', 'Error en la sincronización total. Revisá los logs del servidor.');
            header('Location: ' . $redirectPath);
            exit;
        }
    }

    public function syncPrecios(): void
    {
        AuthService::requireLogin();
        $this->verifyCsrfOrAbort();
        $syncService = $this->resolveService();
        $redirectPath = $this->redirectPath();

        try {
            $stats = $syncService->syncPrecios();

            $empresaId = (int) \App\Core\Context::getEmpresaId();
            if ($empresaId > 0) {
                (new \App\Modules\RxnPwa\RxnPwaCatalogVersionRepository())->invalidate($empresaId);
            }

            \App\Core\Flash::set('success', 'Sincronización de Precios finalizada exitosamente.', $stats);
            header('Location: ' . $redirectPath);
            exit;

        } catch (\Throwable $e) {
            error_log('[TangoSyncController::syncPrecios] ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            \App\Core\Flash::set('danger', 'Error al sincronizar precios desde Tango. Revisá los logs del servidor.');
            header('Location: ' . $redirectPath);
            exit;
        }
    }

    public function syncStock(): void
    {
        AuthService::requireLogin();
        $this->verifyCsrfOrAbort();
        $syncService = $this->resolveService();
        $redirectPath = $this->redirectPath();

        try {
            $stats = $syncService->syncStock();

            $empresaId = (int) \App\Core\Context::getEmpresaId();
            if ($empresaId > 0) {
                (new \App\Modules\RxnPwa\RxnPwaCatalogVersionRepository())->invalidate($empresaId);
            }

            \App\Core\Flash::set('success', 'Sincronización de Stock finalizada exitosamente.', $stats);
            header('Location: ' . $redirectPath);
            exit;

        } catch (\Throwable $e) {
            error_log('[TangoSyncController::syncStock] ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            \App\Core\Flash::set('danger', 'Error al sincronizar stock desde Tango. Revisá los logs del servidor.');
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
        $return = trim((string) ($_GET['return'] ?? $_POST['return'] ?? ''));
        if ($return !== '' && str_starts_with($return, '/mi-empresa/')) {
            return $return;
        }
        return $this->resolveArea() === 'crm'
            ? '/mi-empresa/crm/articulos'
            : '/mi-empresa/articulos';
    }
}
