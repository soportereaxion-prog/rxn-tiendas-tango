<?php

declare(strict_types=1);

namespace App\Modules\EmpresaConfig;

use App\Core\Controller;
use App\Core\View;
use App\Modules\Auth\AuthService;
use App\Modules\Empresas\EmpresaRepository;
use App\Core\Context;

class EmpresaConfigController extends Controller
{
    private EmpresaConfigService $service;
    private EmpresaRepository $empresaRepo;

    public function __construct()
    {
        $this->service = new EmpresaConfigService();
        $this->empresaRepo = new EmpresaRepository();
    }

    public function index(): void
    {
        AuthService::requireLogin();
        
        try {
            $config = $this->service->getConfig();
            $empresaId = Context::getEmpresaId();
            $empresa = $this->empresaRepo->findById((int)$empresaId);
            
            View::render('app/modules/EmpresaConfig/views/index.php', [
                'config' => $config,
                'empresa' => $empresa
            ]);
        } catch (\Exception $e) {
            http_response_code(403);
            echo "<h2>Acceso Denegado</h2><p>" . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }

    public function store(): void
    {
        AuthService::requireLogin();
        
        try {
            $this->service->save($_POST);
            header('Location: /rxnTiendasIA/public/mi-empresa/configuracion?success=guardado');
            exit;
        } catch (\Exception $e) {
            try {
                $config = $this->service->getConfig();
                $empresaId = Context::getEmpresaId();
                $empresa = $this->empresaRepo->findById((int)$empresaId);

                View::render('app/modules/EmpresaConfig/views/index.php', [
                    'error' => 'Error al guardar: ' . $e->getMessage(),
                    'config' => $config,
                    'empresa' => $empresa,
                    'old' => $_POST
                ]);
            } catch (\Exception $ex) {
                http_response_code(403);
                echo "<h2>Acceso Denegado</h2><p>" . htmlspecialchars($ex->getMessage()) . "</p>";
            }
        }
    }
}
