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

            // BRANDING EMPRESARIAL
            $empresaId = (int)Context::getEmpresaId();
            $empresa = $this->empresaRepo->findById($empresaId);
            
            $brandingData = [
                'logo_url' => $empresa->logo_url ?? null,
                'favicon_url' => $empresa->favicon_url ?? null,
                'color_primary' => !empty($_POST['color_primary']) ? $_POST['color_primary'] : null,
                'color_secondary' => !empty($_POST['color_secondary']) ? $_POST['color_secondary'] : null,
                'footer_text' => !empty($_POST['footer_text']) ? $_POST['footer_text'] : null,
                'footer_address' => !empty($_POST['footer_address']) ? $_POST['footer_address'] : null,
                'footer_phone' => !empty($_POST['footer_phone']) ? $_POST['footer_phone'] : null,
                'footer_socials' => !empty($_POST['footer_socials']) ? $_POST['footer_socials'] : null,
            ];

            $dirUploads = __DIR__ . '/../../../public/uploads/empresas/' . $empresaId . '/branding';
            if (!is_dir($dirUploads)) {
                mkdir($dirUploads, 0777, true);
            }

            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                $filename = 'logo_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $dirUploads . '/' . $filename)) {
                    $brandingData['logo_url'] = '/uploads/empresas/' . $empresaId . '/branding/' . $filename;
                }
            }
            if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION));
                $filename = 'favicon_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['favicon']['tmp_name'], $dirUploads . '/' . $filename)) {
                    $brandingData['favicon_url'] = '/uploads/empresas/' . $empresaId . '/branding/' . $filename;
                }
            }

            $this->empresaRepo->updateBranding($empresaId, $brandingData);

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

    /**
     * Validador AJAX de Handshake SMTP Empresa (Tenant) en tiempo de ejecución
     */
    public function testConnection(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');

        $empresaId = Context::getEmpresaId();
        
        $configData = [
            'host' => trim($_POST['smtp_host'] ?? ''),
            'port' => (int)($_POST['smtp_port'] ?? 587),
            'user' => trim($_POST['smtp_user'] ?? ''),
            'pass' => trim($_POST['smtp_pass'] ?? ''),
            'secure' => trim($_POST['smtp_secure'] ?? ''),
        ];

        // Recuperar password oculta preservando el behaviour real del backend si se evalúa guardado previo vs field vacío
        if (empty($configData['pass'])) {
            $repoConf = $this->service->getConfig();
            $configData['pass'] = $repoConf->smtp_pass ?? '';
        }

        $mailService = new \App\Core\Services\MailService();
        $result = $mailService->testConnection($configData);

        echo json_encode($result);
        exit;
    }

    /**
     * Validador AJAX de Credenciales Tango Connect
     */
    public function testConnectTango(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');

        $apiUrl = trim($_POST['tango_api_url'] ?? '');
        $companyId = trim($_POST['tango_connect_company_id'] ?? '');
        $clientKey = trim($_POST['tango_connect_key'] ?? '');
        $token = trim($_POST['tango_connect_token'] ?? '');

        if (empty($token)) {
            $repoConf = $this->service->getConfig();
            $token = $repoConf->tango_connect_token ?? '';
        }

        if (empty($apiUrl) || empty($companyId) || empty($token)) {
            echo json_encode(['success' => false, 'message' => 'Faltan parámetros mínimos (URL, ID Empresa o Token).']);
            exit;
        }

        try {
            $tangoKeyParsed = str_replace('/', '-', $clientKey);
            $finalUrl = rtrim(sprintf("https://%s.connect.axoft.com/Api", $tangoKeyParsed), '/');
            
            // Si no usan el esquema connect.axoft, fallback al literal
            if (empty($clientKey) && filter_var($apiUrl, FILTER_VALIDATE_URL)) {
                $finalUrl = rtrim($apiUrl, '/');
            }

            $client = new \App\Modules\Tango\TangoApiClient($finalUrl, $token, $companyId, $clientKey);
            $isValid = $client->testConnection();

            if ($isValid) {
                echo json_encode(['success' => true, 'message' => 'Handshake completado exitosamente con Axoft.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Credenciales inválidas o servidor inalcanzable.']);
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Proveedor AJAX de Metadata (Listas y Depósitos) para los Selects
     */
    public function getConnectTangoMetadata(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');

        $apiUrl = trim($_POST['tango_api_url'] ?? '');
        $companyId = trim($_POST['tango_connect_company_id'] ?? '');
        $clientKey = trim($_POST['tango_connect_key'] ?? '');
        $token = trim($_POST['tango_connect_token'] ?? '');

        if (empty($token)) {
            $repoConf = $this->service->getConfig();
            $token = $repoConf->tango_connect_token ?? '';
        }

        try {
            $tangoKeyParsed = str_replace('/', '-', $clientKey);
            $finalUrl = rtrim(sprintf("https://%s.connect.axoft.com/Api", $tangoKeyParsed), '/');
            if (empty($clientKey) && filter_var($apiUrl, FILTER_VALIDATE_URL)) {
                $finalUrl = rtrim($apiUrl, '/');
            }

            $client = new \App\Modules\Tango\TangoApiClient($finalUrl, $token, $companyId, $clientKey);
            
            $depositosObj = $client->getMaestroDepositos();
            $listasObj = $client->getMaestroListasPrecio();

            // Transform objects to Arrays for JS mapping
            $depositos = [];
            foreach($depositosObj as $id => $desc) {
                $depositos[] = ['id' => $id, 'descripcion' => $desc];
            }
            $listas = [];
            foreach($listasObj as $id => $desc) {
                $listas[] = ['id' => $id, 'descripcion' => $desc];
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'depositos' => $depositos,
                    'listas_precios' => $listas
                ]
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}
