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
            $advancedFilters = $this->handleCrudFilters('config_usuarios');
            $result = $this->service->findAllForContext($_GET, $advancedFilters);
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

    public function fetchTangoProfile(): void
    {
        $this->requireAdmin();
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $profileId = 0;
            if (isset($input['tango_perfil_pedido'])) {
                $parts = explode('|', $input['tango_perfil_pedido']);
                if (count($parts) >= 1 && is_numeric($parts[0])) {
                    $profileId = (int)$parts[0];
                }
            }
            if ($profileId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Perfil inválido (debe seleccionar un perfil del listado).']);
                return;
            }

            $empresaId = \App\Core\Context::getEmpresaId();
            $configRepo = \App\Modules\EmpresaConfig\EmpresaConfigRepository::forCrm();
            $config = $configRepo->findByEmpresaId($empresaId);

            if (!$config || trim((string)($config->tango_connect_token ?? '')) === '') {
                echo json_encode(['success' => false, 'message' => 'La empresa no tiene la conexión de Tango Connect configurada.']);
                return;
            }

            // Sync Profile details
            $snapshotService = new \App\Modules\Tango\Services\TangoProfileSnapshotService();
            $perfilData = $snapshotService->fetch($config, $profileId);

            // Sync Clasificaciones PDS (Process 326)
            $items = [];
            try {
                // To fetch Clasificaciones safely without duplicating ApiClient parsing logic,
                // we'll just instantiate TangoApiClient accurately via helper or replicate it nicely.
                $token = trim((string) $config->tango_connect_token);
                $companyId = trim((string) $config->tango_connect_company_id) !== '' ? trim((string) $config->tango_connect_company_id) : '-1';
                $clientKey = trim((string) $config->tango_connect_key);
                
                $rawUrl = trim((string) $config->tango_api_url);
                $apiUrl = $rawUrl;
                if ($rawUrl !== '') {
                    $normalized = rtrim($rawUrl, '/');
                    if (!preg_match('/\/api$/i', $normalized)) {
                        $normalized .= '/Api';
                    }
                    $apiUrl = $normalized;
                } elseif ($clientKey !== '') {
                    $apiUrl = sprintf('https://%s.connect.axoft.com/Api', str_replace('/', '-', $clientKey));
                }

                if ($token !== '' && $apiUrl !== '') {
                    $client = new \App\Modules\Tango\TangoApiClient($apiUrl, $token, $companyId, $clientKey !== '' ? $clientKey : null);
                    
                    // Process 326 - Obtener Clasificaciones
                    $data = $client->getRawClient()->get('Get', [
                        'process' => 326,
                        'pageSize' => 150,
                        'pageIndex' => 0,
                        'view' => ''
                    ]);

                    $items = $data['resultData']['list'] ?? $data['data']['resultData']['list'] ?? [];
                    if (!empty($items)) {
                        $config->clasificaciones_pds_raw = json_encode($items, JSON_UNESCAPED_UNICODE);
                        $configRepo->save($config);
                    }
                }
            } catch (\Throwable $err) {
                // Ignore failure on clasificaciones to not break profile sync
            }

            echo json_encode([
                'success' => true, 
                'message' => 'Perfil de Tango resuelto y base de Clasificaciones cachéada (' . count($items) . ' obtenidas).',
                'data' => $perfilData
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
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

        $configServiceCrm = \App\Modules\EmpresaConfig\EmpresaConfigService::forArea('crm');
        $configCrm = $configServiceCrm->getConfig();
        $tangoProfilesJson = $configCrm->tango_perfil_snapshot_json ?? '';

        if (empty($tangoProfilesJson)) {
            $configServiceTiendas = \App\Modules\EmpresaConfig\EmpresaConfigService::forArea('tiendas');
            $configTiendas = $configServiceTiendas->getConfig();
            $tangoProfilesJson = $configTiendas->tango_perfil_snapshot_json ?? '';
        }

        $tangoProfiles = !empty($tangoProfilesJson) ? json_decode($tangoProfilesJson, true) : [];
        $empresaTarget = (new EmpresaRepository())->findById((int) (\App\Core\Context::getEmpresaId() ?? 0));

        View::render('app/modules/Usuarios/views/crear.php', array_merge($ui, [
            'isGlobalAdmin' => $isGlobalAdmin,
            'empresas' => $empresas,
            'tangoProfiles' => is_array($tangoProfiles) ? $tangoProfiles : [],
            'canManageAdminPrivileges' => $this->canManageAdminPrivileges(),
            'empresaTarget' => $empresaTarget,
        ]));
    }

    public function store(): void
    {
        $this->requireAdmin();
        $ui = $this->buildUiContext();
        try {
            $this->service = new UsuarioService();
            $this->service->create($_POST);
            
            $url = $ui['indexPath'];
            $sep = str_contains($url, '?') ? '&' : '?';
            header('Location: ' . $url . $sep . 'success=' . urlencode('Usuario registrado exitosamente'));
            exit;
        } catch (\Exception $e) {
            $isGlobalAdmin = (!empty($_SESSION['es_rxn_admin']) && $_SESSION['es_rxn_admin'] == 1);
            $empresas = [];
            if ($isGlobalAdmin) {
                $empresaRepo = new EmpresaRepository();
                $empresas = $empresaRepo->findAll();
            }

            $configServiceCrm = \App\Modules\EmpresaConfig\EmpresaConfigService::forArea('crm');
            $configCrm = $configServiceCrm->getConfig();
            $tangoProfilesJson = $configCrm->tango_perfil_snapshot_json ?? '';

            if (empty($tangoProfilesJson)) {
                $configServiceTiendas = \App\Modules\EmpresaConfig\EmpresaConfigService::forArea('tiendas');
                $configTiendas = $configServiceTiendas->getConfig();
                $tangoProfilesJson = $configTiendas->tango_perfil_snapshot_json ?? '';
            }

            $tangoProfiles = !empty($tangoProfilesJson) ? json_decode($tangoProfilesJson, true) : [];
            $empresaTarget = (new EmpresaRepository())->findById((int) (\App\Core\Context::getEmpresaId() ?? 0));

            View::render('app/modules/Usuarios/views/crear.php', array_merge($ui, [
                'error' => $e->getMessage(),
                'old' => $_POST,
                'isGlobalAdmin' => $isGlobalAdmin,
                'empresas' => $empresas,
                'tangoProfiles' => is_array($tangoProfiles) ? $tangoProfiles : [],
                'canManageAdminPrivileges' => $this->canManageAdminPrivileges(),
                'empresaTarget' => $empresaTarget,
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

            $configServiceCrm = \App\Modules\EmpresaConfig\EmpresaConfigService::forArea('crm');
            $configCrm = $configServiceCrm->getConfig();
            $tangoProfilesJson = $configCrm->tango_perfil_snapshot_json ?? '';

            if (empty($tangoProfilesJson)) {
                $configServiceTiendas = \App\Modules\EmpresaConfig\EmpresaConfigService::forArea('tiendas');
                $configTiendas = $configServiceTiendas->getConfig();
                $tangoProfilesJson = $configTiendas->tango_perfil_snapshot_json ?? '';
            }

            $tangoProfiles = !empty($tangoProfilesJson) ? json_decode($tangoProfilesJson, true) : [];
            $empresaTarget = (new EmpresaRepository())->findById((int) $usuario->empresa_id);

            View::render('app/modules/Usuarios/views/editar.php', array_merge($ui, [
                'usuario' => $usuario,
                'isGlobalAdmin' => $isGlobalAdmin,
                'empresas' => $empresas,
                'tangoProfiles' => is_array($tangoProfiles) ? $tangoProfiles : [],
                'canManageAdminPrivileges' => $this->canManageAdminPrivileges(),
                'empresaTarget' => $empresaTarget,
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
            // Coherente con PDS / Presupuestos (release 1.19.0): Guardar se queda
            // en el form de edición. El operador apreta Volver para salir.
            // Antes redirigía al listado y eso confundía cuando se quería seguir
            // ajustando el mismo usuario (ej: probar password, cambiar perfil
            // Tango, etc).
            $area = $_GET['area'] ?? ($_POST['area'] ?? '');
            $editPath = $ui['basePath'] . '/' . (int) $id . '/editar';
            if ($area !== '') {
                $editPath .= '?area=' . urlencode((string) $area);
            }
            \App\Core\Flash::set('success', 'Datos de usuario actualizados.');
            header('Location: ' . $editPath);
            exit;
        } catch (\Exception $e) {
            $usuario = $this->service->getByIdForContext((int) $id);
            $isGlobalAdmin = (!empty($_SESSION['es_rxn_admin']) && $_SESSION['es_rxn_admin'] == 1);
            $empresas = [];
            if ($isGlobalAdmin) {
                $empresaRepo = new EmpresaRepository();
                $empresas = $empresaRepo->findAll();
            }

            $configServiceCrm = \App\Modules\EmpresaConfig\EmpresaConfigService::forArea('crm');
            $configCrm = $configServiceCrm->getConfig();
            $tangoProfilesJson = $configCrm->tango_perfil_snapshot_json ?? '';

            if (empty($tangoProfilesJson)) {
                $configServiceTiendas = \App\Modules\EmpresaConfig\EmpresaConfigService::forArea('tiendas');
                $configTiendas = $configServiceTiendas->getConfig();
                $tangoProfilesJson = $configTiendas->tango_perfil_snapshot_json ?? '';
            }

            $tangoProfiles = !empty($tangoProfilesJson) ? json_decode($tangoProfilesJson, true) : [];
            $empresaTarget = (new EmpresaRepository())->findById((int) $usuario->empresa_id);

            View::render('app/modules/Usuarios/views/editar.php', array_merge($ui, [
                'error' => $e->getMessage(),
                'old' => $_POST,
                'usuario' => $usuario,
                'isGlobalAdmin' => $isGlobalAdmin,
                'empresas' => $empresas,
                'tangoProfiles' => is_array($tangoProfiles) ? $tangoProfiles : [],
                'canManageAdminPrivileges' => $this->canManageAdminPrivileges(),
                'empresaTarget' => $empresaTarget,
            ]));
        }
    }

    public function copy(string $id): void
    {
        $this->requireAdmin();
        $ui = $this->buildUiContext();
        try {
            $this->service = new UsuarioService();
            $this->service->copy((int) $id);
            $url = $ui['indexPath'];
            $sep = str_contains($url, '?') ? '&' : '?';
            header('Location: ' . $url . $sep . 'success=' . urlencode('Usuario copiado exitosamente'));
            exit;
        } catch (\InvalidArgumentException $e) {
            $url = $ui['indexPath'];
            $sep = str_contains($url, '?') ? '&' : '?';
            header('Location: ' . $url . $sep . 'error=' . urlencode($e->getMessage()));
            exit;
        } catch (\PDOException $e) {
            $url = $ui['indexPath'];
            $sep = str_contains($url, '?') ? '&' : '?';
            header('Location: ' . $url . $sep . 'error=' . urlencode('Error al copiar usuario. Posible colisión de Email genérico.'));
            exit;
        }
    }

    public function eliminarMasivo(): void
    {
        $this->requireAdmin();
        $ui = $this->buildUiContext();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $url = $ui['indexPath'];
            $sep = str_contains($url, '?') ? '&' : '?';
            header('Location: ' . $url . $sep . 'error=' . urlencode('Método no permitido'));
            exit;
        }

        $ids = $_POST['ids'] ?? [];
        if (empty($ids) || !is_array($ids)) {
            $url = $ui['indexPath'];
            $sep = str_contains($url, '?') ? '&' : '?';
            header('Location: ' . $url . $sep . 'error=' . urlencode('No se seleccionaron usuarios'));
            exit;
        }

        try {
            $this->service = new UsuarioService();
            $count = $this->service->bulkDelete($ids);
            $url = $ui['indexPath'];
            $sep = str_contains($url, '?') ? '&' : '?';
            header('Location: ' . $url . $sep . 'success=' . urlencode("Se eliminaron {$count} usuarios correctamente."));
            exit;
        } catch (\Exception $e) {
            $url = $ui['indexPath'];
            $sep = str_contains($url, '?') ? '&' : '?';
            header('Location: ' . $url . $sep . 'error=' . urlencode("Error al eliminar: " . $e->getMessage()));
            exit;
        }
    }

    public function eliminar(string $id): void
    {
        $this->requireAdmin();
        $ui = $this->buildUiContext();
        try {
            $this->service = new UsuarioService();
            $this->service->delete((int) $id);
            $url = $ui['indexPath'];
            $sep = str_contains($url, '?') ? '&' : '?';
            header('Location: ' . $url . $sep . 'success=' . urlencode('Usuario enviado a la papelera'));
            exit;
        } catch (\Exception $e) {
            $url = $ui['indexPath'];
            $sep = str_contains($url, '?') ? '&' : '?';
            header('Location: ' . $url . $sep . 'error=' . urlencode('Error al eliminar: ' . $e->getMessage()));
            exit;
        }
    }

    public function restore(string $id): void
    {
        $this->requireAdmin();
        $ui = $this->buildUiContext();
        try {
            $this->service = new UsuarioService();
            $this->service->restore((int) $id);
            $url = $ui['indexPath'];
            $sep = str_contains($url, '?') ? '&' : '?';
            header('Location: ' . $url . $sep . 'status=papelera&success=' . urlencode('Usuario restaurado exitosamente'));
            exit;
        } catch (\Exception $e) {
            $url = $ui['indexPath'];
            $sep = str_contains($url, '?') ? '&' : '?';
            header('Location: ' . $url . $sep . 'status=papelera&error=' . urlencode('Error al restaurar: ' . $e->getMessage()));
            exit;
        }
    }

    public function restoreMasivo(): void
    {
        $this->requireAdmin();
        $ui = $this->buildUiContext();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $url = $ui['indexPath'];
            $sep = str_contains($url, '?') ? '&' : '?';
            header('Location: ' . $url . $sep . 'status=papelera&error=' . urlencode('Método no permitido'));
            exit;
        }

        $ids = $_POST['ids'] ?? [];
        if (empty($ids) || !is_array($ids)) {
            $url = $ui['indexPath'];
            $sep = str_contains($url, '?') ? '&' : '?';
            header('Location: ' . $url . $sep . 'status=papelera&error=' . urlencode('No se seleccionaron usuarios'));
            exit;
        }

        try {
            $this->service = new UsuarioService();
            $count = $this->service->bulkRestore($ids);
            $url = $ui['indexPath'];
            $sep = str_contains($url, '?') ? '&' : '?';
            header('Location: ' . $url . $sep . 'status=papelera&success=' . urlencode("Se restauraron {$count} usuarios correctamente."));
            exit;
        } catch (\Exception $e) {
            $url = $ui['indexPath'];
            $sep = str_contains($url, '?') ? '&' : '?';
            header('Location: ' . $url . $sep . 'status=papelera&error=' . urlencode("Error al restaurar: " . $e->getMessage()));
            exit;
        }
    }

    public function forceDelete(string $id): void
    {
        $this->requireAdmin();
        $ui = $this->buildUiContext();
        try {
            $this->service = new UsuarioService();
            $this->service->forceDelete((int) $id);
            $url = $ui['indexPath'];
            $sep = str_contains($url, '?') ? '&' : '?';
            header('Location: ' . $url . $sep . 'status=papelera&success=' . urlencode('Usuario eliminado definitivamente'));
            exit;
        } catch (\Exception $e) {
            $url = $ui['indexPath'];
            $sep = str_contains($url, '?') ? '&' : '?';
            header('Location: ' . $url . $sep . 'status=papelera&error=' . urlencode('Error al eliminar: ' . $e->getMessage()));
            exit;
        }
    }

    public function forceDeleteMasivo(): void
    {
        $this->requireAdmin();
        $ui = $this->buildUiContext();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $url = $ui['indexPath'];
            $sep = str_contains($url, '?') ? '&' : '?';
            header('Location: ' . $url . $sep . 'status=papelera&error=' . urlencode('Método no permitido'));
            exit;
        }

        $ids = $_POST['ids'] ?? [];
        if (empty($ids) || !is_array($ids)) {
            $url = $ui['indexPath'];
            $sep = str_contains($url, '?') ? '&' : '?';
            header('Location: ' . $url . $sep . 'status=papelera&error=' . urlencode('No se seleccionaron usuarios'));
            exit;
        }

        try {
            $this->service = new UsuarioService();
            $count = $this->service->bulkForceDelete($ids);
            $url = $ui['indexPath'];
            $sep = str_contains($url, '?') ? '&' : '?';
            header('Location: ' . $url . $sep . 'status=papelera&success=' . urlencode("Se destruyeron {$count} usuarios correctamente."));
            exit;
        } catch (\Exception $e) {
            $url = $ui['indexPath'];
            $sep = str_contains($url, '?') ? '&' : '?';
            header('Location: ' . $url . $sep . 'status=papelera&error=' . urlencode("Error al destruir: " . $e->getMessage()));
            exit;
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
            'basePath' => '/mi-empresa/usuarios',
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
