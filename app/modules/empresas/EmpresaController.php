<?php

declare(strict_types=1);

namespace App\Modules\Empresas;

use App\Core\Controller;
use App\Core\View;
use InvalidArgumentException;

class EmpresaController extends Controller
{
    private EmpresaService $service;

    public function __construct()
    {
        $this->service = new EmpresaService();
    }

    public function index(): void
    {
        $empresas = $this->service->findAll();
        View::render('app/modules/empresas/views/index.php', [
            'empresas' => $empresas
        ]);
    }

    public function create(): void
    {
        View::render('app/modules/empresas/views/crear.php');
    }

    public function store(): void
    {
        try {
            $this->service->create($_POST);
            // Redirigir al listado
            header('Location: /rxnTiendasIA/public/empresas');
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
}
