<?php

declare(strict_types=1);

namespace App\Modules\EmpresaConfig;

use App\Core\Controller;
use App\Core\View;

class EmpresaConfigController extends Controller
{
    private EmpresaConfigService $service;

    public function __construct()
    {
        $this->service = new EmpresaConfigService();
    }

    public function index(): void
    {
        try {
            $config = $this->service->getConfig();
            View::render('app/modules/EmpresaConfig/views/index.php', [
                'config' => $config
            ]);
        } catch (\Exception $e) {
            http_response_code(403);
            echo "<h2>Acceso Denegado</h2><p>" . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }

    public function store(): void
    {
        try {
            $this->service->save($_POST);
            header('Location: /rxnTiendasIA/public/mi-empresa/configuracion?success=guardado');
            exit;
        } catch (\Exception $e) {
            try {
                $config = $this->service->getConfig();
                View::render('app/modules/EmpresaConfig/views/index.php', [
                    'error' => 'Error al guardar: ' . $e->getMessage(),
                    'config' => $config,
                    'old' => $_POST
                ]);
            } catch (\Exception $ex) {
                http_response_code(403);
                echo "<h2>Acceso Denegado</h2><p>" . htmlspecialchars($ex->getMessage()) . "</p>";
            }
        }
    }
}
