<?php

declare(strict_types=1);

namespace App\Modules\Usuarios;

use App\Core\Controller;
use App\Core\View;
use App\Modules\Auth\AuthService;
use App\Modules\Empresas\EmpresaRepository;
use App\Shared\Services\OperationalAreaService;

class UsuarioController extends Controller
{
    private UsuarioService $service;

    private function canManageAdminPrivileges(): bool
    {
        return (!empty($_SESSION['es_rxn_admin']) && $_SESSION['es_rxn_admin'] == 1)
            || (!empty($_SESSION['es_admin']) && $_SESSION['es_admin'] == 1);
    }

    private function requireAdmin(): void
    {
        AuthService::requireLogin();
    }

    public function index(): void
    {
        $this->requireAdmin();
        $ui = $this->buildUiContext();
        try {
            $this->service = new UsuarioService();
            $result = $this->service->findAllForContext($_GET);
            View::render('app/modules/Usuarios/views/index.php', array_merge($ui, [
                'usuarios' => $result['items'],
                'filters' => $result['filters'],
                'totalUsuarios' => $result['total'],
                'filteredCount' => $result['filteredTotal'],
                'pagination' => $result['pagination'],
            ]));
        } catch (\Exception $e) {
            $this->renderDenegado($e->getMessage(), $ui['indexPath']);
        }
    }

    public function suggestions(): void
    {
        $this->requireAdmin();
        header('Content-Type: application/json');

        try {
            $this->service = new UsuarioService();
            echo json_encode([
                'success' => true,
                'data' => $this->service->findSuggestionsForContext($_GET),
            ]);
        } catch (\Throwable $e) {
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
        $this->requireAdmin();
        $ui = $this->buildUiContext();
        $isGlobalAdmin = (!empty($_SESSION['es_rxn_admin']) && $_SESSION['es_rxn_admin'] == 1);
        $empresas = [];
        if ($isGlobalAdmin) {
            $empresaRepo = new EmpresaRepository();
            $empresas = $empresaRepo->findAll();
        }

        $empresaId = \App\Core\Context::getEmpresaId();
        $tangoProfiles = [];
        if ($empresaId) {
            try {
                $configRepo = new \App\Modules\EmpresaConfig\EmpresaConfigRepository();
                $config = $configRepo->findByEmpresaId($empresaId);
                if ($config && trim((string)($config->tango_connect_token ?? '')) !== '') {
                    $apiUrl = rtrim($config->tango_api_url ?? '', '/');
                    if (!preg_match('/\/api$/i', $apiUrl)) {
                        $apiUrl .= '/Api';
                    }
                    $tangoClient = new \App\Modules\Tango\TangoApiClient(
                        $apiUrl,
                        $config->tango_connect_token,
                        $config->tango_connect_company_id ?? '',
                        $config->tango_connect_key ?? ''
                    );
                    $tangoProfiles = $tangoClient->getPerfilesPedidos();
                }
            } catch (\Exception $e) { }
        }
        
        View::render('app/modules/Usuarios/views/crear.php', array_merge($ui, [
            'isGlobalAdmin' => $isGlobalAdmin,
            'empresas' => $empresas,
            'tangoProfiles' => $tangoProfiles,
            'canManageAdminPrivileges' => $this->canManageAdminPrivileges(),
        ]));
    }

    public function store(): void
    {
        $this->requireAdmin();
        $ui = $this->buildUiContext();
        try {
            $this->service = new UsuarioService();
            $this->service->create($_POST);
            header('Location: ' . $this->withSuccess($ui['indexPath'], 'Usuario registrado exitosamente'));
            exit;
        } catch (\Exception $e) {
            $isGlobalAdmin = (!empty($_SESSION['es_rxn_admin']) && $_SESSION['es_rxn_admin'] == 1);
            $empresas = [];
            if ($isGlobalAdmin) {
                $empresaRepo = new EmpresaRepository();
                $empresas = $empresaRepo->findAll();
            }
            $empresaId = \App\Core\Context::getEmpresaId();
            $tangoProfiles = [];
            if ($empresaId) {
                try {
                    $configRepo = new \App\Modules\EmpresaConfig\EmpresaConfigRepository();
                    $config = $configRepo->findByEmpresaId($empresaId);
                    if ($config && trim((string)($config->tango_connect_token ?? '')) !== '') {
                        $apiUrl = rtrim($config->tango_api_url ?? '', '/');
                        if (!preg_match('/\/api$/i', $apiUrl)) {
                            $apiUrl .= '/Api';
                        }
                        $tangoClient = new \App\Modules\Tango\TangoApiClient(
                            $apiUrl,
                            $config->tango_connect_token,
                            $config->tango_connect_company_id ?? '',
                            $config->tango_connect_key ?? ''
                        );
                        $tangoProfiles = $tangoClient->getPerfilesPedidos();
                    }
                } catch (\Exception $ex) { }
            }
            View::render('app/modules/Usuarios/views/crear.php', array_merge($ui, [
                'error' => $e->getMessage(),
                'old' => $_POST,
                'isGlobalAdmin' => $isGlobalAdmin,
                'empresas' => $empresas,
                'tangoProfiles' => $tangoProfiles,
                'canManageAdminPrivileges' => $this->canManageAdminPrivileges(),
            ]));
        }
    }

    public function edit(string $id): void
    {
        $this->requireAdmin();
        $ui = $this->buildUiContext();
        try {
            $this->service = new UsuarioService();
            $usuario = $this->service->getByIdForContext((int) $id);
            
            $isGlobalAdmin = (!empty($_SESSION['es_rxn_admin']) && $_SESSION['es_rxn_admin'] == 1);
            $empresas = [];
            if ($isGlobalAdmin) {
                $empresaRepo = new EmpresaRepository();
                $empresas = $empresaRepo->findAll();
            }

            $tangoProfiles = [];
            try {
                $configRepo = new \App\Modules\EmpresaConfig\EmpresaConfigRepository();
                $config = $configRepo->findByEmpresaId($usuario->empresa_id);
                if ($config && trim((string)($config->tango_connect_token ?? '')) !== '') {
                    $apiUrl = rtrim($config->tango_api_url ?? '', '/');
                    if (!preg_match('/\/api$/i', $apiUrl)) {
                        $apiUrl .= '/Api';
                    }
                    $tangoClient = new \App\Modules\Tango\TangoApiClient(
                        $apiUrl,
                        $config->tango_connect_token,
                        $config->tango_connect_company_id ?? '',
                        $config->tango_connect_key ?? ''
                    );
                    $tangoProfiles = $tangoClient->getPerfilesPedidos();
                }
            } catch (\Exception $e) { }

            View::render('app/modules/Usuarios/views/editar.php', array_merge($ui, [
                'usuario' => $usuario,
                'isGlobalAdmin' => $isGlobalAdmin,
                'empresas' => $empresas,
                'tangoProfiles' => $tangoProfiles,
                'canManageAdminPrivileges' => $this->canManageAdminPrivileges(),
            ]));
        } catch (\Exception $e) {
            $this->renderDenegado($e->getMessage(), $ui['indexPath']);
        }
    }

    public function update(string $id): void
    {
        $this->requireAdmin();
        $this->service = new UsuarioService();
        $ui = $this->buildUiContext();
        
        try {
            $this->service->update((int) $id, $_POST);
            header('Location: ' . $this->withSuccess($ui['indexPath'], 'Datos de usuario actualizados'));
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

                View::render('app/modules/Usuarios/views/editar.php', array_merge($ui, [
                    'error' => $e->getMessage(),
                    'usuario' => $usuario,
                    'old' => $_POST,
                    'isGlobalAdmin' => $isGlobalAdmin,
                    'empresas' => $empresas,
                    'canManageAdminPrivileges' => $this->canManageAdminPrivileges(),
                ]));
            } catch (\Exception $ex) {
                // Manipulación interceptada por Contexto.
                $this->renderDenegado($ex->getMessage(), $ui['indexPath']);
            }
        }
    }

    private function renderDenegado(string $motivo, string $backPath): void
    {
        http_response_code(403);
        echo "<div style='font-family:sans-serif; text-align:center; margin-top:50px;'>";
        echo "<h2>⚠️ Operación Interrumpida (Aislamiento de Entorno Activo)</h2>";
        echo "<p>" . htmlspecialchars($motivo) . "</p>";
        echo "<a href='" . htmlspecialchars($backPath, ENT_QUOTES, 'UTF-8') . "'>Volver Seguro</a>";
        echo "</div>";
        exit;
    }

    private function buildUiContext(): array
    {
        $area = OperationalAreaService::resolveFromRequest();

        return [
            'area' => $area,
            'basePath' => '/rxnTiendasIA/public/mi-empresa/usuarios',
            'indexPath' => OperationalAreaService::usersPath($area),
            'dashboardPath' => OperationalAreaService::dashboardPath($area),
            'helpPath' => OperationalAreaService::helpPath($area),
            'environmentLabel' => OperationalAreaService::environmentLabel($area),
        ];
    }

    private function withSuccess(string $path, string $message): string
    {
        $separator = str_contains($path, '?') ? '&' : '?';
        return $path . $separator . 'success=' . urlencode($message);
    }
}
