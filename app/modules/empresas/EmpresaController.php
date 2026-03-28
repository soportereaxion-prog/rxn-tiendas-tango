<?php

declare(strict_types=1);

namespace App\Modules\Empresas;

use App\Core\Controller;
use App\Core\View;
use InvalidArgumentException;
use App\Modules\Auth\AuthService;

class EmpresaController extends Controller
{
    private EmpresaService $service;

    public function __construct()
    {
        $this->service = new EmpresaService();
    }

    public function index(): void
    {
        AuthService::requireBackofficeAdmin();
        $result = $this->service->findAll($_GET);
        View::render('app/modules/empresas/views/index.php', [
            'empresas' => $result['items'],
            'filters' => $result['filters'],
            'totalEmpresas' => $result['total'],
            'filteredCount' => $result['filteredTotal'],
            'pagination' => $result['pagination'],
        ]);
    }

    public function create(): void
    {
        AuthService::requireBackofficeAdmin();
        View::render('app/modules/empresas/views/crear.php');
    }

    public function suggestions(): void
    {
        AuthService::requireBackofficeAdmin();
        header('Content-Type: application/json');

        try {
            echo json_encode([
                'success' => true,
                'data' => $this->service->findSuggestions($_GET),
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'No se pudieron obtener sugerencias.',
                'data' => [],
            ]);
        }
    }

    public function store(): void
    {
        AuthService::requireBackofficeAdmin();
        try {
            $this->service->create($_POST);
            // Redirigir al listado con feedback
            header('Location: /rxnTiendasIA/public/empresas?success=creada');
            exit;
        } catch (InvalidArgumentException $e) {
            View::render('app/modules/empresas/views/crear.php', [
                'error' => $e->getMessage(),
                'old' => $_POST
            ]);
        } catch (\PDOException $e) {
            $error = 'Error de base de datos. Es posible que el código ya exista.';
            View::render('app/modules/empresas/views/crear.php', [
                'error' => $error,
                'old' => $_POST
            ]);
        }
    }

    public function edit(string $id): void
    {
        AuthService::requireBackofficeAdmin();
        $empresa = $this->service->findById((int) $id);
        if (!$empresa) {
            header('Location: /rxnTiendasIA/public/empresas?error=No+encontrada');
            exit;
        }

        View::render('app/modules/empresas/views/editar.php', [
            'empresa' => $empresa
        ]);
    }

    public function update(string $id): void
    {
        AuthService::requireBackofficeAdmin();
        try {
            $this->service->update((int) $id, $_POST);
            header('Location: /rxnTiendasIA/public/empresas?success=actualizada');
            exit;
        } catch (InvalidArgumentException $e) {
            $empresa = $this->service->findById((int) $id);
            View::render('app/modules/empresas/views/editar.php', [
                'error' => $e->getMessage(),
                'empresa' => $empresa,
                'old' => $_POST
            ]);
        } catch (\PDOException $e) {
            $empresa = $this->service->findById((int) $id);
            View::render('app/modules/empresas/views/editar.php', [
                'error' => 'Error de base de datos.',
                'empresa' => $empresa,
                'old' => $_POST
            ]);
        }
    }
}
