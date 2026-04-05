<?php

declare(strict_types=1);

namespace App\Modules\EmpresaConfig;

use App\Core\Controller;
use App\Core\Context;
use App\Core\View;
use App\Modules\Auth\AuthService;
use App\Modules\Empresas\EmpresaRepository;
use App\Shared\Services\OperationalAreaService;

class EmpresaConfigController extends Controller
{
    private EmpresaRepository $empresaRepo;

    public function __construct()
    {
        $this->empresaRepo = new EmpresaRepository();
    }

    public function index(): void
    {
        AuthService::requireLogin();
        $area = $this->resolveArea();
        $service = $this->resolveService($area);
        $viewContext = $this->buildViewContext($area);
        
        try {
            $config = $service->getConfig();
            $empresaId = Context::getEmpresaId();
            $empresa = $this->empresaRepo->findById((int)$empresaId);
            
            $printForms = [];
            if ($area === 'crm') {
                require_once BASE_PATH . '/app/modules/PrintForms/PrintFormRepository.php';
                $repoForms = new \App\Modules\PrintForms\PrintFormRepository();
                $printForms = $repoForms->getDefinitionsByEmpresaId((int)$empresaId);
            }
            
            View::render('app/modules/EmpresaConfig/views/index.php', array_merge($viewContext, [
                'config' => $config,
                'empresa' => $empresa,
                'printForms' => $printForms
            ]));
        } catch (\Exception $e) {
            http_response_code(403);
            echo "<h2>Acceso Denegado</h2><p>" . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }

    public function store(): void
    {
        AuthService::requireLogin();
        $area = $this->resolveArea();
        $service = $this->resolveService($area);
        $viewContext = $this->buildViewContext($area);
        
        try {
            $service->save($_POST);

            if ($area === OperationalAreaService::AREA_TIENDAS) {
                $this->persistStoreBranding();
            } elseif ($area === OperationalAreaService::AREA_CRM) {
                $this->persistCrmFavicon();
            }

            header('Location: ' . $viewContext['basePath'] . '?success=guardado');
            exit;
        } catch (\Exception $e) {
            try {
                $config = $service->getConfig();
                $empresaId = Context::getEmpresaId();
                $empresa = $this->empresaRepo->findById((int)$empresaId);

                View::render('app/modules/EmpresaConfig/views/index.php', array_merge($viewContext, [
                    'error' => 'Error al guardar: ' . $e->getMessage(),
                    'config' => $config,
                    'empresa' => $empresa,
                    'old' => $_POST
                ]));
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
        $service = $this->resolveService($this->resolveArea());

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
            $repoConf = $service->getConfig();
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

        try {
            $service = $this->resolveService($this->resolveArea());

            $apiUrl = trim($_POST['tango_api_url'] ?? '');
            $companyId = trim($_POST['tango_connect_company_id'] ?? '');
            $clientKey = trim($_POST['tango_connect_key'] ?? '');
            $token = trim($_POST['tango_connect_token'] ?? '');
            $repoConf = $service->getConfig();

            if (empty($token)) {
                $token = $repoConf->tango_connect_token ?? '';
            }

            if (empty($clientKey)) {
                $clientKey = trim((string) ($repoConf->tango_connect_key ?? ''));
            }

            if (empty($apiUrl)) {
                $apiUrl = trim((string) ($repoConf->tango_api_url ?? ''));
            }

            if ($companyId === '') {
                $companyId = (string) ($repoConf->tango_connect_company_id ?? '');
            }

            if (empty($token) || (empty($apiUrl) && empty($clientKey))) {
                echo json_encode(['success' => false, 'message' => 'Faltan parámetros mínimos. Debes informar Token y al menos una Llave o URL base.']);
                exit;
            }

            // Prioritize user-provided API URL, fallback to guessing from clientKey if empty
            $finalUrl = '';
            if (!empty($apiUrl) && filter_var($apiUrl, FILTER_VALIDATE_URL)) {
                $finalUrl = rtrim($apiUrl, '/');
            } elseif (!empty($clientKey)) {
                $tangoKeyParsed = str_replace('/', '-', $clientKey);
                $finalUrl = rtrim(sprintf("https://%s.connect.axoft.com/Api", $tangoKeyParsed), '/');
            }

            $client = new \App\Modules\Tango\TangoApiClient($finalUrl, $token, $companyId !== '' ? $companyId : '-1', $clientKey);
            $isValid = $client->testConnection();

            if ($isValid) {
                echo json_encode(['success' => true, 'message' => 'Handshake completado exitosamente con Axoft.'], JSON_INVALID_UTF8_SUBSTITUTE);
            } else {
                echo json_encode(['success' => false, 'message' => 'Credenciales inválidas o servidor inalcanzable.'], JSON_INVALID_UTF8_SUBSTITUTE);
            }
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    /**
     * Proveedor AJAX de Metadata (Empresas, Listas y Depósitos) para los Selects
     */
    public function getConnectTangoMetadata(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');

        try {
            $service = $this->resolveService($this->resolveArea());

            $apiUrl = trim($_POST['tango_api_url'] ?? '');
            $companyId = trim($_POST['tango_connect_company_id'] ?? '');
            $clientKey = trim($_POST['tango_connect_key'] ?? '');
            $token = trim($_POST['tango_connect_token'] ?? '');
            $repoConf = $service->getConfig();

            if (empty($token)) {
                $token = $repoConf->tango_connect_token ?? '';
            }

            if (empty($clientKey)) {
                $clientKey = trim((string) ($repoConf->tango_connect_key ?? ''));
            }

            if (empty($apiUrl)) {
                $apiUrl = trim((string) ($repoConf->tango_api_url ?? ''));
            }

            if ($companyId === '') {
                $companyId = (string) ($repoConf->tango_connect_company_id ?? '');
            }

            if (empty($token) || (empty($apiUrl) && empty($clientKey))) {
                echo json_encode(['success' => false, 'message' => 'Faltan parámetros mínimos. Debes informar Token y al menos una Llave o URL base.']);
                exit;
            }

            // Prioritize user-provided API URL, fallback to guessing from clientKey if empty
            $finalUrl = '';
            if (!empty($apiUrl) && filter_var($apiUrl, FILTER_VALIDATE_URL)) {
                $finalUrl = rtrim($apiUrl, '/');
            } elseif (!empty($clientKey)) {
                $tangoKeyParsed = str_replace('/', '-', $clientKey);
                $finalUrl = rtrim(sprintf("https://%s.connect.axoft.com/Api", $tangoKeyParsed), '/');
            }

            $client = new \App\Modules\Tango\TangoApiClient($finalUrl, $token, $companyId !== '' ? $companyId : '-1', $clientKey);
            
            $empresasObj = $client->getMaestroEmpresas();
            $perfilesObj = $client->getPerfilesPedidos();
            
            $listasPreciosObj = count($empresasObj) > 0 ? $client->getMaestroListasPrecio() : [];
            $depositosObj = count($empresasObj) > 0 ? $client->getMaestroDepositos() : [];

            $debugDump = [
                'FECHA' => date('Y-m-d H:i:s'),
                'HTTP_REQUEST_TRACE' => $client->debugLastHttpRequest ?? 'Inaccesible',
                'EMPRESAS_API_RAW' => $client->debugLastRawEmpresas ?? 'No capturado',
                'EMPRESAS_NORMALIZADAS' => [],
            ];

            foreach ($empresasObj as $id => $desc) {
                $debugDump['EMPRESAS_NORMALIZADAS'][] = [
                    'id' => $id,
                    'descripcion' => $desc
                ];
            }

            try {
                $logDir = BASE_PATH . '/logs';
                if (!is_dir($logDir)) { mkdir($logDir, 0777, true); }
                file_put_contents($logDir . '/debug_selectores_connect.json', json_encode($debugDump, JSON_PRETTY_PRINT));
            } catch (\Exception $ed) { }

            $empresas = [];
            foreach($empresasObj as $id => $desc) {
                $empresas[] = ['id' => $id, 'descripcion' => $desc];
            }
            
            $listasPrecios = [];
            foreach($listasPreciosObj as $id => $desc) {
                $listasPrecios[] = ['id' => $id, 'descripcion' => $desc];
            }

            $depositos = [];
            foreach($depositosObj as $id => $desc) {
                $depositos[] = ['id' => $id, 'descripcion' => $desc];
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'empresas' => $empresas,
                    'perfiles_pedidos' => $perfilesObj,
                    'listas_precios' => $listasPrecios,
                    'depositos' => $depositos
                ]
            ], JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    private function resolveArea(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');

        return str_contains($uri, '/mi-empresa/crm/') ? 'crm' : 'tiendas';
    }

    private function buildViewContext(string $area): array
    {
                        if ($area === 'crm') {
            return [
                'area' => $area,
                'pageTitle' => 'Configuracion CRM',
                'headerTitle' => 'Configuracion de la Empresa',
                'headerSubtitle' => 'Gestion independiente del entorno operativo de CRM.',
                'consoleTitle' => 'Consola de Configuracion CRM',
                'consoleSubtitle' => 'Ajustes propios del modulo CRM para la empresa #[%s].',
                'dashboardPath' => '/mi-empresa/crm/dashboard',
                'helpPath' => OperationalAreaService::helpPath(OperationalAreaService::AREA_CRM),
                'basePath' => '/mi-empresa/crm/configuracion',
                'moduleNotesKey' => 'crm_configuracion',
                'moduleNotesLabel' => 'Configuracion CRM',
                'sharedScopeNotice' => 'CRM ahora guarda su configuracion operativa en un origen propio. El branding publico y la URL de la tienda se siguen administrando desde Tiendas.',
                'showStoreSlug' => false,
                'showStoreBranding' => false,
                'generalSectionTitle' => '1. Datos Generales CRM',
                'fallbackSectionTitle' => '2. Identidad Visual CRM',
                'fallbackSectionHelp' => 'Si un articulo interno de CRM no posee imagen propia, se mostrara este fallback visual.',
                'tangoSectionTitle' => '3. Integracion CRM con Tango Connect',
                'smtpSectionTitle' => '4. Correo Operativo CRM (SMTP)',
                'smtpIntroText' => 'Estas credenciales quedan reservadas para procesos del entorno CRM. No alteran el envio operativo de Tiendas.',
            ];
        }

        return [
            'area' => $area,
            'pageTitle' => 'Configuracion de Empresa',
            'headerTitle' => 'Configuracion de la Empresa',
            'headerSubtitle' => 'Gestion del entorno operativo actual.',
            'consoleTitle' => 'Consola de Configuracion',
            'consoleSubtitle' => 'Personaliza la identidad online y el enlace con el ERP de #[%s].',
            'dashboardPath' => '/mi-empresa/dashboard',
            'helpPath' => OperationalAreaService::helpPath(OperationalAreaService::AREA_TIENDAS),
            'basePath' => '/mi-empresa/configuracion',
            'moduleNotesKey' => 'empresa_configuracion',
            'moduleNotesLabel' => 'Configuracion de Empresa',
            'sharedScopeNotice' => '',
            'showStoreSlug' => true,
            'showStoreBranding' => true,
            'generalSectionTitle' => '1. Datos Generales',
            'fallbackSectionTitle' => '3. Identidad Visual Corporativa',
            'fallbackSectionHelp' => 'Si un articulo de Tango no posee imagenes sincronizadas en el sistema publico, se exhibira automaticamente este placeholder visual.',
            'tangoSectionTitle' => '4. Integracion Tango Connect',
            'smtpSectionTitle' => '5. Transmision de Correo Electronico (SMTP)',
            'smtpIntroText' => 'Fallback Automatico de RXN: Si la llave SMTP esta apagada, el sistema utilizara de forma totalmente transparente nuestro SMTP Global de alta reputacion garantizando que los correos logisticos lleguen a la bandeja de entrada de tus clientes.',
        ];
    }

    private function resolveService(string $area): EmpresaConfigService
    {
        return $area === OperationalAreaService::AREA_CRM
            ? EmpresaConfigService::forCrm()
            : new EmpresaConfigService();
    }

    private function persistStoreBranding(): void
    {
        $empresaId = (int) Context::getEmpresaId();
        $empresa = $this->empresaRepo->findById($empresaId);
        if ($empresa === null) {
            return;
        }

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
    }

    private function persistCrmFavicon(): void
    {
        if (!isset($_FILES['favicon']) || $_FILES['favicon']['error'] !== UPLOAD_ERR_OK) {
            return;
        }

        $empresaId = (int) Context::getEmpresaId();
        $empresa = $this->empresaRepo->findById($empresaId);
        if ($empresa === null) {
            return;
        }

        $dirUploads = __DIR__ . '/../../../public/uploads/empresas/' . $empresaId . '/branding';
        if (!is_dir($dirUploads)) {
            mkdir($dirUploads, 0777, true);
        }

        $ext = strtolower(pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION));
        $filename = 'favicon_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['favicon']['tmp_name'], $dirUploads . '/' . $filename)) {
            $brandingData = [
                'logo_url' => $empresa->logo_url ?? null,
                'favicon_url' => '/uploads/empresas/' . $empresaId . '/branding/' . $filename,
                'color_primary' => $empresa->color_primary ?? null,
                'color_secondary' => $empresa->color_secondary ?? null,
                'footer_text' => $empresa->footer_text ?? null,
                'footer_address' => $empresa->footer_address ?? null,
                'footer_phone' => $empresa->footer_phone ?? null,
                'footer_socials' => $empresa->footer_socials ?? null,
            ];
            $this->empresaRepo->updateBranding($empresaId, $brandingData);
        }
    }
}
