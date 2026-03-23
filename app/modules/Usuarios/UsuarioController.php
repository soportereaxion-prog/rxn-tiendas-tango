<?php

declare(strict_types=1);

namespace App\Modules\Usuarios;

use App\Core\Controller;
use App\Core\View;
use App\Modules\Auth\AuthService;

class UsuarioController extends Controller
{
    private UsuarioService $service;

    public function index(): void
    {
        AuthService::requireLogin();
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
        AuthService::requireLogin();
        View::render('app/modules/Usuarios/views/crear.php', []);
    }

    public function store(): void
    {
        AuthService::requireLogin();
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
        AuthService::requireLogin();
        try {
            $this->service = new UsuarioService();
            $usuario = $this->service->getByIdForContext((int) $id);
            View::render('app/modules/Usuarios/views/editar.php', [
                'usuario' => $usuario
            ]);
        } catch (\Exception $e) {
            $this->renderDenegado($e->getMessage());
        }
    }

    public function update(string $id): void
    {
        AuthService::requireLogin();
        $this->service = new UsuarioService();
        
        try {
            $this->service->update((int) $id, $_POST);
            header('Location: /rxnTiendasIA/public/mi-empresa/usuarios?success=Datos de usuario actualizados');
            exit;
        } catch (\Exception $e) {
            try {
                $usuario = $this->service->getByIdForContext((int) $id);
                View::render('app/modules/Usuarios/views/editar.php', [
                    'error' => $e->getMessage(),
                    'usuario' => $usuario,
                    'old' => $_POST
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
