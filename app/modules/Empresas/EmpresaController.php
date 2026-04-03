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

    public function copy(string $id): void
    {
        AuthService::requireBackofficeAdmin();
        try {
            $this->service->copy((int) $id);
            header('Location: /rxnTiendasIA/public/empresas?success=Empresa+copiada');
            exit;
        } catch (InvalidArgumentException $e) {
            header('Location: /rxnTiendasIA/public/empresas?error=' . urlencode($e->getMessage()));
            exit;
        } catch (\PDOException $e) {
            header('Location: /rxnTiendasIA/public/empresas?error=' . urlencode('Error en clonación. Verifique código original.'));
            exit;
        }
    }

    public function ingresar(string $id): void
    {
        AuthService::requireBackofficeAdmin();
        $empresa = $this->service->findById((int) $id);
        if (!$empresa) {
            header('Location: /rxnTiendasIA/public/empresas?error=' . urlencode('Empresa no encontrada.'));
            exit;
        }
        
        $_SESSION['empresa_id'] = $empresa->id;
        header('Location: /rxnTiendasIA/public/');
        exit;
    }

    public function eliminarMasivo(): void
    {
        AuthService::requireBackofficeAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /rxnTiendasIA/public/empresas');
            exit;
        }

        $ids = $_POST['ids'] ?? [];
        if (empty($ids) || !is_array($ids)) {
            header('Location: /rxnTiendasIA/public/empresas');
            exit;
        }

        try {
            $count = $this->service->bulkDelete($ids);
            header('Location: /rxnTiendasIA/public/empresas?success=' . urlencode("Se eliminaron {$count} empresas correctamente."));
            exit;
        } catch (\Exception $e) {
            header('Location: /rxnTiendasIA/public/empresas?error=' . urlencode("Error parcial o total al eliminar: " . $e->getMessage()));
            exit;
        }
    }

    public function eliminar(string $id): void
    {
        AuthService::requireBackofficeAdmin();
        try {
            $this->service->bulkDelete([(int)$id]);
            header('Location: /rxnTiendasIA/public/empresas?success=' . urlencode('Empresa enviada a la papelera'));
            exit;
        } catch (\Exception $e) {
            header('Location: /rxnTiendasIA/public/empresas?error=' . urlencode('Error al eliminar: ' . $e->getMessage()));
            exit;
        }
    }

    public function restore(string $id): void
    {
        AuthService::requireBackofficeAdmin();
        try {
            $this->service->restore((int)$id);
            header('Location: /rxnTiendasIA/public/empresas?status=papelera&success=' . urlencode('Empresa restaurada exitosamente'));
            exit;
        } catch (\Exception $e) {
            header('Location: /rxnTiendasIA/public/empresas?status=papelera&error=' . urlencode('Error al restaurar: ' . $e->getMessage()));
            exit;
        }
    }

    public function restoreMasivo(): void
    {
        AuthService::requireBackofficeAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /rxnTiendasIA/public/empresas?status=papelera');
            exit;
        }

        $ids = $_POST['ids'] ?? [];
        if (empty($ids) || !is_array($ids)) {
            header('Location: /rxnTiendasIA/public/empresas?status=papelera');
            exit;
        }

        try {
            $count = $this->service->bulkRestore($ids);
            header('Location: /rxnTiendasIA/public/empresas?status=papelera&success=' . urlencode("Se restauraron {$count} empresas correctamente."));
            exit;
        } catch (\Exception $e) {
            header('Location: /rxnTiendasIA/public/empresas?status=papelera&error=' . urlencode("Error al restaurar: " . $e->getMessage()));
            exit;
        }
    }

    public function forceDelete(string $id): void
    {
        AuthService::requireBackofficeAdmin();
        try {
            $this->service->forceDelete((int)$id);
            header('Location: /rxnTiendasIA/public/empresas?status=papelera&success=' . urlencode('Empresa eliminada definitivamente'));
            exit;
        } catch (\Exception $e) {
            header('Location: /rxnTiendasIA/public/empresas?status=papelera&error=' . urlencode('Error al eliminar: ' . $e->getMessage()));
            exit;
        }
    }

    public function forceDeleteMasivo(): void
    {
        AuthService::requireBackofficeAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /rxnTiendasIA/public/empresas?status=papelera');
            exit;
        }

        $ids = $_POST['ids'] ?? [];
        if (empty($ids) || !is_array($ids)) {
            header('Location: /rxnTiendasIA/public/empresas?status=papelera');
            exit;
        }

        try {
            $count = $this->service->bulkForceDelete($ids);
            header('Location: /rxnTiendasIA/public/empresas?status=papelera&success=' . urlencode("Se destruyeron {$count} empresas correctamente."));
            exit;
        } catch (\Exception $e) {
            header('Location: /rxnTiendasIA/public/empresas?status=papelera&error=' . urlencode("Error al destruir: " . $e->getMessage()));
            exit;
        }
    }
}
