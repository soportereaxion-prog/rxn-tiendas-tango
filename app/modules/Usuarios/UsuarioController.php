<?php

declare(strict_types=1);

namespace App\Modules\Usuarios;

use App\Core\Controller;
use App\Core\View;
use App\Modules\Auth\AuthService;
use App\Modules\Empresas\EmpresaRepository;

class UsuarioController extends Controller
{
    private UsuarioService $service;

    private function requireAdmin(): void
    {
        AuthService::requireLogin();
        $isTenantAdmin = (!empty($_SESSION['es_admin']) && $_SESSION['es_admin'] == 1);
        $isGlobalAdmin = (!empty($_SESSION['es_rxn_admin']) && $_SESSION['es_rxn_admin'] == 1);
        
        if (!$isTenantAdmin && !$isGlobalAdmin) {
            $this->renderDenegado("No tiene permisos de administrador para gestionar usuarios.");
        }
    }

    public function index(): void
    {
        $this->requireAdmin();
        try {
            $this->service = new UsuarioService();
            $usuarios = $this->service->getAllForContext();
            View::render('app/modules/Usuarios/views/index.php', [
                'usuarios' => $usuarios
            ]);
        } catch (\Exception $e) {
            $this->renderDenegado($e->getMessage());
        }
    }

    public function create(): void
    {
        $this->requireAdmin();
        $isGlobalAdmin = (!empty($_SESSION['es_rxn_admin']) && $_SESSION['es_rxn_admin'] == 1);
        $empresas = [];
        if ($isGlobalAdmin) {
            $empresaRepo = new EmpresaRepository();
            $empresas = $empresaRepo->findAll();
        }
        
        View::render('app/modules/Usuarios/views/crear.php', [
            'isGlobalAdmin' => $isGlobalAdmin,
            'empresas' => $empresas
        ]);
    }

    public function store(): void
    {
        $this->requireAdmin();
        try {
            $this->service = new UsuarioService();
            $this->service->create($_POST);
            header('Location: /rxnTiendasIA/public/mi-empresa/usuarios?success=Usuario registrado exitosamente');
            exit;
        } catch (\Exception $e) {
            View::render('app/modules/Usuarios/views/crear.php', [
                'error' => $e->getMessage(),
                'old' => $_POST
            ]);
        }
    }

    public function edit(string $id): void
    {
        $this->requireAdmin();
        try {
            $this->service = new UsuarioService();
            $usuario = $this->service->getByIdForContext((int) $id);
            
            $isGlobalAdmin = (!empty($_SESSION['es_rxn_admin']) && $_SESSION['es_rxn_admin'] == 1);
            $empresas = [];
            if ($isGlobalAdmin) {
                $empresaRepo = new EmpresaRepository();
                $empresas = $empresaRepo->findAll();
            }

            View::render('app/modules/Usuarios/views/editar.php', [
                'usuario' => $usuario,
                'isGlobalAdmin' => $isGlobalAdmin,
                'empresas' => $empresas
            ]);
        } catch (\Exception $e) {
            $this->renderDenegado($e->getMessage());
        }
    }

    public function update(string $id): void
    {
        $this->requireAdmin();
        $this->service = new UsuarioService();
        
        try {
            $this->service->update((int) $id, $_POST);
            header('Location: /rxnTiendasIA/public/mi-empresa/usuarios?success=Datos de usuario actualizados');
            exit;
        } catch (\Exception $e) {
            try {
                $usuario = $this->service->getByIdForContext((int) $id);
                
                $isGlobalAdmin = (!empty($_SESSION['es_rxn_admin']) && $_SESSION['es_rxn_admin'] == 1);
                $empresas = [];
                if ($isGlobalAdmin) {
                    $empresaRepo = new EmpresaRepository();
                    $empresas = $empresaRepo->findAll();
                }

                View::render('app/modules/Usuarios/views/editar.php', [
                    'error' => $e->getMessage(),
                    'usuario' => $usuario,
                    'old' => $_POST,
                    'isGlobalAdmin' => $isGlobalAdmin,
                    'empresas' => $empresas
                ]);
            } catch (\Exception $ex) {
                // Manipulación interceptada por Contexto.
                $this->renderDenegado($ex->getMessage());
            }
        }
    }

    private function renderDenegado(string $motivo): void
    {
        http_response_code(403);
        echo "<div style='font-family:sans-serif; text-align:center; margin-top:50px;'>";
        echo "<h2>⚠️ Operación Interrumpida (Aislamiento de Entorno Activo)</h2>";
        echo "<p>" . htmlspecialchars($motivo) . "</p>";
        echo "<a href='/rxnTiendasIA/public/mi-empresa/usuarios'>Volver Seguro</a>";
        echo "</div>";
        exit;
    }
}
