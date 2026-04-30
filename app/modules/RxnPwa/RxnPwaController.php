<?php

declare(strict_types=1);

namespace App\Modules\RxnPwa;

use App\Core\Context;
use App\Core\View;
use App\Modules\Auth\AuthService;
use App\Modules\Empresas\EmpresaAccessService;

/**
 * Entry de la PWA mobile (Bloque A — Fase 1).
 *
 * Rutas:
 *   - GET /rxnpwa/presupuestos             → shell HTML que registra el SW + chequea catálogo offline.
 *   - GET /api/rxnpwa/catalog/version      → {hash, generated_at, items_count, size_bytes}.
 *   - GET /api/rxnpwa/catalog/full         → catálogo completo + hash.
 *
 * Auth: cookie de sesión + acceso CRM. Multi-tenant estricto via Context::getEmpresaId().
 */
class RxnPwaController extends \App\Core\Controller
{
    private RxnPwaCatalogService $catalog;

    public function __construct()
    {
        $this->catalog = new RxnPwaCatalogService();
    }

    /**
     * Shell HTML de la PWA. Esta vista es lo que termina cacheada por el SW
     * como app shell. Contiene un mínimo: header + zona "preparando offline" +
     * indicador de versión de catálogo. La fase 2 reemplaza/extiende esto con el
     * formulario mobile real.
     */
    public function presupuestosShell(): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmAccess();

        $empresaId = (int) Context::getEmpresaId();

        View::render('app/modules/RxnPwa/views/presupuestos_shell.php', [
            'empresaId' => $empresaId,
            'pageTitle' => 'RXN PWA — Presupuestos',
        ]);
    }

    /**
     * Form mobile para crear un presupuesto nuevo. El draft local se crea
     * client-side al primer save (tmp_uuid se genera en JS).
     */
    public function presupuestoNuevo(): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmAccess();

        $empresaId = (int) Context::getEmpresaId();

        View::render('app/modules/RxnPwa/views/presupuesto_form.php', [
            'empresaId' => $empresaId,
            'tmpUuid' => '',
            'pageTitle' => 'Nuevo presupuesto',
        ]);
    }

    /**
     * Form mobile para editar un draft local existente. La carga real del draft
     * la hace el JS desde IndexedDB usando el tmp_uuid de la URL.
     */
    public function presupuestoEditar(string $tmpUuid): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmAccess();

        $empresaId = (int) Context::getEmpresaId();
        $tmpUuid = $this->sanitizeTmpUuid($tmpUuid);

        View::render('app/modules/RxnPwa/views/presupuesto_form.php', [
            'empresaId' => $empresaId,
            'tmpUuid' => $tmpUuid,
            'pageTitle' => 'Editar presupuesto',
        ]);
    }

    private function sanitizeTmpUuid(string $tmpUuid): string
    {
        $tmpUuid = trim($tmpUuid);
        if (!preg_match('/^TMP-[A-Za-z0-9-]{1,64}$/', $tmpUuid)) {
            return '';
        }
        return $tmpUuid;
    }

    public function catalogVersion(): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmAccess();

        $empresaId = (int) Context::getEmpresaId();
        $version = $this->catalog->ensureVersion($empresaId);

        $this->jsonResponse([
            'ok' => true,
            'hash' => $version['hash'],
            'generated_at' => $version['generated_at'],
            'items_count' => $version['items_count'],
            'size_bytes' => $version['size_bytes'],
        ]);
    }

    public function catalogFull(): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmAccess();

        $empresaId = (int) Context::getEmpresaId();
        $bundle = $this->catalog->getFullCatalog($empresaId);

        // Hash en header así el SW puede compararlo sin parsear el JSON entero.
        header('X-Rxnpwa-Catalog-Hash: ' . $bundle['hash']);

        $this->jsonResponse([
            'ok' => true,
            'hash' => $bundle['hash'],
            'generated_at' => $bundle['generated_at'],
            'items_count' => $bundle['items_count'],
            'size_bytes' => $bundle['size_bytes'],
            'data' => $bundle['data'],
        ]);
    }

    private function jsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        // No cachear en proxies/SW: la frescura la maneja el cliente con el hash.
        header('Cache-Control: no-store, max-age=0');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
