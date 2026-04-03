<?php

declare(strict_types=1);

namespace App\Modules\CrmLlamadas;

use App\Core\Controller;
use App\Core\View;
use App\Core\Context;
use App\Modules\Auth\AuthService;
use App\Shared\Services\OperationalAreaService;

class CrmLlamadasController extends Controller
{
    private CrmLlamadaRepository $repository;

    public function __construct()
    {
        $this->repository = new CrmLlamadaRepository();
    }

    private function getEmpresaIdOrDie(): int
    {
        $empresaId = Context::getEmpresaId();
        if ($empresaId === null || $empresaId <= 0) {
            $this->renderDenegado("No hay un contexto de empresa válido activo.", "/");
        }
        return (int) $empresaId;
    }

    public function index(): void
    {
        AuthService::requireLogin();
        $empresaId = $this->getEmpresaIdOrDie();
        $ui = $this->buildUiContext();

        $search = $_GET['search'] ?? '';
        $sortRaw = $_GET['sort'] ?? 'fecha_desc';
        $parts = explode('_', $sortRaw);
        $sortDir = strtoupper(array_pop($parts));
        $sortColumn = implode('_', $parts);

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 15;
        $offset = ($page - 1) * $perPage;

        $status = $_GET['status'] ?? 'activos';
        $onlyDeleted = $status === 'papelera';

        $totalItems = $this->repository->countAll($empresaId, $search, $onlyDeleted);
        $items = $this->repository->findAllWithSearch($empresaId, $perPage, $offset, $search, $sortColumn, $sortDir, $onlyDeleted);

        View::render('app/modules/CrmLlamadas/views/index.php', array_merge($ui, [
            'llamadas' => $items,
            'search' => $search,
            'page' => $page,
            'totalPages' => max(1, ceil($totalItems / $perPage)),
            'totalItems' => $totalItems,
            'status' => $status,
            'sortRaw' => $sortRaw
        ]));
    }

    public function eliminar(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = $this->getEmpresaIdOrDie();
        $ui = $this->buildUiContext();

        $this->repository->delete((int) $id, $empresaId);
        header('Location: ' . $this->withSuccess($ui['indexPath'], 'Llamada enviada a la papelera.'));
        exit;
    }

    public function restore(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = $this->getEmpresaIdOrDie();
        $ui = $this->buildUiContext();

        $this->repository->restore((int) $id, $empresaId);
        header('Location: ' . $this->withSuccess($ui['indexPath'] . '?status=papelera', 'Llamada restaurada exitosamente.'));
        exit;
    }

    public function forceDelete(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = $this->getEmpresaIdOrDie();
        $ui = $this->buildUiContext();

        $this->repository->forceDelete((int) $id, $empresaId);
        header('Location: ' . $this->withSuccess($ui['indexPath'] . '?status=papelera', 'Llamada eliminada definitivamente.'));
        exit;
    }

    public function eliminarMasivo(): void
    {
        AuthService::requireLogin();
        $empresaId = $this->getEmpresaIdOrDie();
        $ui = $this->buildUiContext();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $ui['indexPath']);
            exit;
        }

        $ids = $_POST['ids'] ?? [];
        if (empty($ids) || !is_array($ids)) {
            header('Location: ' . $ui['indexPath']);
            exit;
        }

        $count = 0;
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id > 0) {
                try {
                    $this->repository->delete($id, $empresaId);
                    $count++;
                } catch (\Exception $e) {}
            }
        }
        header('Location: ' . $this->withSuccess($ui['indexPath'], "Se enviaron {$count} llamadas a la papelera."));
        exit;
    }

    public function restoreMasivo(): void
    {
        AuthService::requireLogin();
        $empresaId = $this->getEmpresaIdOrDie();
        $ui = $this->buildUiContext();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $ui['indexPath'] . '?status=papelera');
            exit;
        }

        $ids = $_POST['ids'] ?? [];
        if (empty($ids) || !is_array($ids)) {
            header('Location: ' . $ui['indexPath'] . '?status=papelera');
            exit;
        }

        $count = 0;
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id > 0) {
                try {
                    $this->repository->restore($id, $empresaId);
                    $count++;
                } catch (\Exception $e) {}
            }
        }
        header('Location: ' . $this->withSuccess($ui['indexPath'] . '?status=papelera', "Se restauraron {$count} llamadas exitosamente."));
        exit;
    }

    public function forceDeleteMasivo(): void
    {
        AuthService::requireLogin();
        $empresaId = $this->getEmpresaIdOrDie();
        $ui = $this->buildUiContext();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . $ui['indexPath'] . '?status=papelera');
            exit;
        }

        $ids = $_POST['ids'] ?? [];
        if (empty($ids) || !is_array($ids)) {
            header('Location: ' . $ui['indexPath'] . '?status=papelera');
            exit;
        }

        $count = 0;
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id > 0) {
                try {
                    $this->repository->forceDelete($id, $empresaId);
                    $count++;
                } catch (\Exception $e) {}
            }
        }
        header('Location: ' . $this->withSuccess($ui['indexPath'] . '?status=papelera', "Se eliminaron {$count} llamadas definitivamente."));
        exit;
    }

    private function renderDenegado(string $motivo, string $backPath): void
    {
        http_response_code(403);
        echo "<div style='font-family:sans-serif; text-align:center; margin-top:50px;'>";
        echo "<h2>⚠️ Operación Interrumpida</h2>";
        echo "<p>" . htmlspecialchars($motivo) . "</p>";
        echo "<a href='" . htmlspecialchars($backPath, ENT_QUOTES, 'UTF-8') . "'>Volver</a>";
        echo "</div>";
        exit;
    }

    private function buildUiContext(): array
    {
        $area = OperationalAreaService::resolveFromRequest();

        return [
            'area' => $area,
            'basePath' => '/mi-empresa/crm/llamadas',
            'indexPath' => '/mi-empresa/crm/llamadas',
            'dashboardPath' => OperationalAreaService::dashboardPath($area),
            'environmentLabel' => OperationalAreaService::environmentLabel($area),
        ];
    }

    private function withSuccess(string $path, string $message): string
    {
        $separator = str_contains($path, '?') ? '&' : '?';
        return $path . $separator . 'success=' . urlencode($message);
    }
}
