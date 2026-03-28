<?php
declare(strict_types=1);

namespace App\Modules\Categorias;

use App\Core\Controller;
use App\Core\Context;
use App\Core\View;
use App\Modules\Auth\AuthService;
use App\Shared\Services\OperationalAreaService;

class CategoriaController extends Controller
{
    private CategoriaService $service;

    public function __construct()
    {
        $this->service = new CategoriaService();
    }

    public function index(): void
    {
        AuthService::requireLogin();
        $ui = $this->buildUiContext();

        try {
            $result = $this->service->findAllForContext($_GET);
            View::render('app/modules/Categorias/views/index.php', array_merge($ui, [
                'categorias' => $result['items'],
                'filters' => $result['filters'],
                'totalCategorias' => $result['total'],
                'filteredCount' => $result['filteredTotal'],
                'pagination' => $result['pagination'],
            ]));
        } catch (\Exception $e) {
            $this->renderDenied($e->getMessage());
        }
    }

    public function suggestions(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');

        try {
            echo json_encode([
                'success' => true,
                'data' => $this->service->findSuggestionsForContext($_GET),
            ]);
        } catch (\Throwable) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'No se pudieron obtener sugerencias.',
                'data' => [],
            ]);
        }

        exit;
    }

    public function create(): void
    {
        AuthService::requireLogin();

        View::render('app/modules/Categorias/views/crear.php', $this->buildUiContext());
    }

    public function store(): void
    {
        AuthService::requireLogin();

        try {
            $this->service->create($_POST, $_FILES);
            $this->clearStoreCache();
            header('Location: ' . $this->withSuccess($this->buildUiContext()['basePath'], 'Categoria creada correctamente'));
            exit;
        } catch (\Exception $e) {
            View::render('app/modules/Categorias/views/crear.php', array_merge($this->buildUiContext(), [
                'error' => $e->getMessage(),
                'old' => $_POST,
            ]));
        }
    }

    public function edit(string $id): void
    {
        AuthService::requireLogin();

        try {
            $categoria = $this->service->getByIdForContext((int) $id);
            View::render('app/modules/Categorias/views/editar.php', array_merge($this->buildUiContext(), [
                'categoria' => $categoria,
            ]));
        } catch (\Exception $e) {
            $this->renderDenied($e->getMessage());
        }
    }

    public function update(string $id): void
    {
        AuthService::requireLogin();

        try {
            $this->service->update((int) $id, $_POST, $_FILES);
            $this->clearStoreCache();
            header('Location: ' . $this->withSuccess($this->buildUiContext()['basePath'], 'Categoria actualizada correctamente'));
            exit;
        } catch (\Exception $e) {
            try {
                $categoria = $this->service->getByIdForContext((int) $id);
                View::render('app/modules/Categorias/views/editar.php', array_merge($this->buildUiContext(), [
                    'error' => $e->getMessage(),
                    'categoria' => $categoria,
                    'old' => $_POST,
                ]));
            } catch (\Exception $inner) {
                $this->renderDenied($inner->getMessage());
            }
        }
    }

    private function renderDenied(string $message): void
    {
        http_response_code(403);
        echo "<div style='font-family:sans-serif; text-align:center; margin-top:50px;'>";
        echo '<h2>Operacion interrumpida</h2>';
        echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
        echo "<a href='/rxnTiendasIA/public/mi-empresa/categorias'>Volver al listado</a>";
        echo '</div>';
        exit;
    }

    private function clearStoreCache(): void
    {
        $empresaId = Context::getEmpresaId();
        if ($empresaId === null) {
            return;
        }

        \App\Core\FileCache::clearPrefix("catalogo_empresa_{$empresaId}");
        \App\Core\FileCache::clearPrefix("categorias_store_empresa_{$empresaId}");
    }

    private function buildUiContext(): array
    {
        return [
            'basePath' => '/rxnTiendasIA/public/mi-empresa/categorias',
            'dashboardPath' => OperationalAreaService::dashboardPath(OperationalAreaService::AREA_TIENDAS),
            'helpPath' => OperationalAreaService::helpPath(OperationalAreaService::AREA_TIENDAS),
        ];
    }

    private function withSuccess(string $path, string $message): string
    {
        $separator = str_contains($path, '?') ? '&' : '?';
        return $path . $separator . 'success=' . urlencode($message);
    }
}
