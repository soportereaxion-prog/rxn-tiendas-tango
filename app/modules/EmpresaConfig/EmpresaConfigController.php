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
            file_put_contents(BASE_PATH . '/logs/test_tango_error.log', "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
            echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    /**
     * Helper privado: construye el TangoApiClient a partir del POST o config guardada.
     * Todos los endpoints atómicos lo reutilizan.
     * @return \App\Modules\Tango\TangoApiClient
     * @throws \RuntimeException si faltan credenciales mínimas
     */
    private function buildTangoClientFromPost(): \App\Modules\Tango\TangoApiClient
    {
        $service  = $this->resolveService($this->resolveArea());
        $repoConf = $service->getConfig();

        $apiUrl    = trim($_POST['tango_api_url'] ?? '')          ?: trim((string)($repoConf->tango_api_url ?? ''));
        $clientKey = trim($_POST['tango_connect_key'] ?? '')       ?: trim((string)($repoConf->tango_connect_key ?? ''));
        $token     = trim($_POST['tango_connect_token'] ?? '')     ?: trim((string)($repoConf->tango_connect_token ?? ''));
        $companyId = trim($_POST['tango_connect_company_id'] ?? '') ?: (string)($repoConf->tango_connect_company_id ?? '');

        if (empty($token) || (empty($apiUrl) && empty($clientKey))) {
            throw new \RuntimeException('Faltan parámetros mínimos. Informá Token y al menos una Llave o URL base.');
        }

        $finalUrl = '';
        if (!empty($apiUrl) && filter_var($apiUrl, FILTER_VALIDATE_URL)) {
            $finalUrl = rtrim($apiUrl, '/');
        } elseif (!empty($clientKey)) {
            $finalUrl = rtrim(sprintf('https://%s.connect.axoft.com/Api', str_replace('/', '-', $clientKey)), '/');
        }

        return new \App\Modules\Tango\TangoApiClient($finalUrl, $token, $companyId !== '' ? $companyId : '-1', $clientKey);
    }

    /**
     * Determina si el POST/config tiene una empresa Connect seleccionada.
     * Listas, depósitos y perfiles dependen de Company; sin empresa elegida
     * Connect responde HTTP 200 con items=0 que ensucia el banner de diagnóstico.
     */
    private function hasResolvedCompanyId(): bool
    {
        $service  = $this->resolveService($this->resolveArea());
        $repoConf = $service->getConfig();
        $companyId = trim($_POST['tango_connect_company_id'] ?? '') ?: (string)($repoConf->tango_connect_company_id ?? '');
        return $companyId !== '' && $companyId !== '-1';
    }

    /**
     * Diagnostic estructural cuando un endpoint dependiente de Company se invoca
     * sin empresa elegida. El frontend lo trata como info, NO como anomalía.
     */
    private function pendingCompanyDiagnostic(int $process, string $label): array
    {
        return [
            'outcome' => 'pending_company',
            'label' => $label,
            'process' => $process,
            'company_header' => '-1',
            'url' => trim((string)($_POST['tango_api_url'] ?? '')),
            'items_count' => 0,
            'error_class' => null,
            'error_message' => 'Seleccioná primero una empresa Connect — este catálogo depende del Company resuelto.',
            'http_code' => null,
            'raw_sample' => null,
            'id_keys' => [],
            'first_item_keys' => [],
        ];
    }

    /**
     * AJAX atómico: devuelve solo Empresas.
     * Separado del endpoint monolítico para no superar el Apache TimeOut (60s).
     */
    public function getTangoEmpresas(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');
        set_time_limit(30);
        try {
            $client     = $this->buildTangoClientFromPost();
            $raw        = $client->getMaestroEmpresas();
            $empresas   = [];
            foreach ($raw as $id => $desc) {
                $empresas[] = ['id' => $id, 'descripcion' => $desc];
            }
            echo json_encode($this->envelopeCatalogResponse($empresas, $client, 'Empresas (process 1418)'), JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'diagnostic' => $this->fallbackDiagnosticFromException($e, 'Empresas (process 1418)'),
            ], JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    /**
     * AJAX atómico: devuelve solo Listas de Precio.
     */
    public function getTangoListas(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');
        set_time_limit(30);
        if (!$this->hasResolvedCompanyId()) {
            echo json_encode([
                'success' => true,
                'data' => [],
                'diagnostic' => $this->pendingCompanyDiagnostic(984, 'Listas de precio (process 984)'),
            ], JSON_INVALID_UTF8_SUBSTITUTE);
            exit;
        }
        try {
            $client = $this->buildTangoClientFromPost();
            $raw    = $client->getMaestroListasPrecio();
            $listas = [];
            foreach ($raw as $id => $desc) {
                $listas[] = ['id' => $id, 'descripcion' => $desc];
            }
            echo json_encode($this->envelopeCatalogResponse($listas, $client, 'Listas de precio (process 984)'), JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'diagnostic' => $this->fallbackDiagnosticFromException($e, 'Listas de precio (process 984)'),
            ], JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    /**
     * AJAX atómico: devuelve solo Depósitos.
     */
    public function getTangoDepositos(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');
        set_time_limit(30);
        if (!$this->hasResolvedCompanyId()) {
            echo json_encode([
                'success' => true,
                'data' => [],
                'diagnostic' => $this->pendingCompanyDiagnostic(2941, 'Depositos (process 2941)'),
            ], JSON_INVALID_UTF8_SUBSTITUTE);
            exit;
        }
        try {
            $client    = $this->buildTangoClientFromPost();
            $raw       = $client->getMaestroDepositos();
            $depositos = [];
            foreach ($raw as $id => $desc) {
                $depositos[] = ['id' => $id, 'descripcion' => $desc];
            }
            echo json_encode($this->envelopeCatalogResponse($depositos, $client, 'Depositos (process 2941)'), JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'diagnostic' => $this->fallbackDiagnosticFromException($e, 'Depositos (process 2941)'),
            ], JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    /**
     * AJAX atómico: devuelve solo Clasificaciones PDS (process 326).
     * Se devuelve en el shape "items" que el frontend traduce a lineas planas
     * (CODIGO descripcion) en el textarea clasificaciones_pds_raw — que es lo
     * que espera ClasificacionCatalogService::parseRaw().
     */
    public function getTangoClasificaciones(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');
        set_time_limit(30);
        try {
            $client = $this->buildTangoClientFromPost();
            $raw    = $client->getClasificacionesPds();
            // getClasificacionesPds devuelve ['id' => ID_GVA81, 'COD_GVA81' => ..., 'DESCRIP' => ...]
            $items = [];
            foreach ($raw as $row) {
                $code = trim((string) ($row['COD_GVA81'] ?? ''));
                $desc = trim((string) ($row['DESCRIP'] ?? ''));
                if ($code === '') {
                    continue;
                }
                $items[] = ['codigo' => $code, 'descripcion' => $desc];
            }
            echo json_encode($this->envelopeCatalogResponse($items, $client, 'Clasificaciones PDS (process 326)'), JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'diagnostic' => $this->fallbackDiagnosticFromException($e, 'Clasificaciones PDS (process 326)'),
            ], JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    /**
     * AJAX atómico: devuelve solo Perfiles de Pedidos.
     */
    public function getTangoPerfiles(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');
        set_time_limit(30);
        if (!$this->hasResolvedCompanyId()) {
            echo json_encode([
                'success' => true,
                'data' => [],
                'diagnostic' => $this->pendingCompanyDiagnostic(20020, 'Perfiles de pedido (process 20020)'),
            ], JSON_INVALID_UTF8_SUBSTITUTE);
            exit;
        }
        try {
            $client   = $this->buildTangoClientFromPost();
            $perfiles = $client->getPerfilesPedidos();
            echo json_encode($this->envelopeCatalogResponse($perfiles, $client, 'Perfiles de pedido (process 20020)'), JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'diagnostic' => $this->fallbackDiagnosticFromException($e, 'Perfiles de pedido (process 20020)'),
            ], JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    /**
     * Endpoint de diagnostico crudo. Ejecuta la misma llamada que getTangoEmpresas
     * (process 1418 con Company: -1) y devuelve el payload textual tal como vino
     * desde Axoft — sin parseo, sin filtros. Pensado para cuando el selector de
     * empresas se ve vacio y hay que saber QUE dijo Connect exactamente.
     *
     * Ver docs/logs/2026-04-16 release 1.12.2.
     */
    public function diagnoseTangoConnect(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');
        set_time_limit(30);

        try {
            $client = $this->buildTangoClientFromPost();
            // Forzamos el flujo real de getMaestroEmpresas (process 1418 con Company: -1)
            // para que el diagnostico refleje EXACTAMENTE lo que ocurre al validar.
            $empresas = $client->getMaestroEmpresas();
            $rawEmpresas = $client->debugLastRawEmpresas;
            $rawJson = json_encode($rawEmpresas, JSON_INVALID_UTF8_SUBSTITUTE);

            echo json_encode([
                'success' => true,
                'data' => [
                    'endpoint' => 'Get?process=1418 (Company: -1)',
                    'empresas_parsed_count' => count($empresas),
                    'diagnostic' => $client->debugLastDiagnostic,
                    'raw_response_sample' => $rawJson !== false ? substr($rawJson, 0, 2000) : '',
                    'resultData_list_count' => is_array($rawEmpresas['resultData']['list'] ?? null)
                        ? count($rawEmpresas['resultData']['list'])
                        : (is_array($rawEmpresas['data']['resultData']['list'] ?? null)
                            ? count($rawEmpresas['data']['resultData']['list'])
                            : null),
                    'top_level_keys' => is_array($rawEmpresas ?? null) ? array_keys($rawEmpresas) : [],
                    'first_item_keys' => is_array($rawEmpresas['resultData']['list'][0] ?? null)
                        ? array_keys($rawEmpresas['resultData']['list'][0])
                        : (is_array($rawEmpresas['data']['resultData']['list'][0] ?? null)
                            ? array_keys($rawEmpresas['data']['resultData']['list'][0])
                            : []),
                    'request_info' => $this->scrubSensitiveHeaders($client->debugLastHttpRequest),
                ],
            ], JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'diagnostic' => $this->fallbackDiagnosticFromException($e, 'Diagnostico empresas (process 1418)'),
            ], JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    /**
     * Construye el envelope de respuesta de los 4 endpoints atomicos de catalogos.
     * SIEMPRE incluye el diagnostic del TangoApiClient — aun cuando success=true —
     * para que el frontend pueda explicar por que un catalogo vino vacio.
     */
    private function envelopeCatalogResponse(array $data, \App\Modules\Tango\TangoApiClient $client, string $label): array
    {
        $diagnostic = $client->debugLastDiagnostic;
        $diagnostic['label'] = $label;

        return [
            'success' => true,
            'data' => $data,
            'diagnostic' => $diagnostic,
        ];
    }

    /**
     * Oculta el token en el debugLastRequest antes de emitirlo por JSON.
     * El array trae headers como 'ApiAuthorization: <token>'.
     */
    private function scrubSensitiveHeaders(array $requestInfo): array
    {
        if (!empty($requestInfo['headers']) && is_array($requestInfo['headers'])) {
            $requestInfo['headers'] = array_map(static function ($h) {
                if (!is_string($h)) {
                    return $h;
                }
                if (stripos($h, 'ApiAuthorization:') === 0) {
                    return 'ApiAuthorization: ***redacted***';
                }
                return $h;
            }, $requestInfo['headers']);
        }
        return $requestInfo;
    }

    /**
     * Cuando la excepcion salta ANTES de que fetchCatalog pueda llenar el diagnostic
     * (ej: credenciales incompletas), construimos uno minimo para que el frontend no
     * pierda visibilidad.
     */
    private function fallbackDiagnosticFromException(\Throwable $e, string $label): array
    {
        return [
            'outcome' => 'error',
            'label' => $label,
            'process' => null,
            'company_header' => trim($_POST['tango_connect_company_id'] ?? '') ?: '-1',
            'url' => trim($_POST['tango_api_url'] ?? ''),
            'items_count' => 0,
            'error_class' => (new \ReflectionClass($e))->getShortName(),
            'error_message' => $e->getMessage(),
            'http_code' => (int) $e->getCode(),
            'raw_sample' => null,
            'id_keys' => [],
            'first_item_keys' => [],
        ];
    }

    /**
     * @deprecated Mantenido para compatibilidad. Usar los 4 endpoints atómicos.
     * Proveedor AJAX de Metadata (Empresas, Listas y Depósitos) para los Selects.
     */
    public function getConnectTangoMetadata(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json');
        set_time_limit(120);
        try {
            $client          = $this->buildTangoClientFromPost();
            $empresasObj     = $client->getMaestroEmpresas();
            $perfilesObj     = $client->getPerfilesPedidos();
            $listasPreciosObj = count($empresasObj) > 0 ? $client->getMaestroListasPrecio() : [];
            $depositosObj    = count($empresasObj) > 0 ? $client->getMaestroDepositos() : [];

            $empresas = [];
            foreach ($empresasObj as $id => $desc) { $empresas[] = ['id' => $id, 'descripcion' => $desc]; }
            $listasPrecios = [];
            foreach ($listasPreciosObj as $id => $desc) { $listasPrecios[] = ['id' => $id, 'descripcion' => $desc]; }
            $depositos = [];
            foreach ($depositosObj as $id => $desc) { $depositos[] = ['id' => $id, 'descripcion' => $desc]; }

            echo json_encode([
                'success' => true,
                'data'    => [
                    'empresas'        => $empresas,
                    'perfiles_pedidos' => $perfilesObj,
                    'listas_precios'  => $listasPrecios,
                    'depositos'       => $depositos,
                ],
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
        \App\Core\UploadValidator::prepareDir($dirUploads);

        $logoPath = $this->storeBrandingAsset($_FILES['logo'] ?? null, $empresaId, 'logo', $dirUploads, false);
        if ($logoPath !== null) {
            $brandingData['logo_url'] = $logoPath;
        }

        $faviconPath = $this->storeBrandingAsset($_FILES['favicon'] ?? null, $empresaId, 'favicon', $dirUploads, true);
        if ($faviconPath !== null) {
            $brandingData['favicon_url'] = $faviconPath;
        }

        $this->empresaRepo->updateBranding($empresaId, $brandingData);
    }

    private function persistCrmFavicon(): void
    {
        if (!isset($_FILES['favicon'])) {
            return;
        }

        $empresaId = (int) Context::getEmpresaId();
        $empresa = $this->empresaRepo->findById($empresaId);
        if ($empresa === null) {
            return;
        }

        $dirUploads = __DIR__ . '/../../../public/uploads/empresas/' . $empresaId . '/branding';
        \App\Core\UploadValidator::prepareDir($dirUploads);

        $faviconPath = $this->storeBrandingAsset($_FILES['favicon'], $empresaId, 'favicon', $dirUploads, true);
        if ($faviconPath === null) {
            return;
        }

        $brandingData = [
            'logo_url' => $empresa->logo_url ?? null,
            'favicon_url' => $faviconPath,
            'color_primary' => $empresa->color_primary ?? null,
            'color_secondary' => $empresa->color_secondary ?? null,
            'footer_text' => $empresa->footer_text ?? null,
            'footer_address' => $empresa->footer_address ?? null,
            'footer_phone' => $empresa->footer_phone ?? null,
            'footer_socials' => $empresa->footer_socials ?? null,
        ];
        $this->empresaRepo->updateBranding($empresaId, $brandingData);
    }

    /**
     * Recibe una entrada de $_FILES y la procesa con UploadValidator.
     * Retorna la ruta pública relativa o null si no se subió archivo.
     */
    private function storeBrandingAsset(?array $file, int $empresaId, string $prefix, string $dirUploads, bool $isFavicon): ?string
    {
        if (!is_array($file)) {
            return null;
        }

        try {
            $validated = $isFavicon
                ? \App\Core\UploadValidator::favicon($file)
                : \App\Core\UploadValidator::image($file);
        } catch (\RuntimeException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            return null;
        }

        if ($validated === null) {
            return null;
        }

        $filename = \App\Core\UploadValidator::generateFilename($prefix, $empresaId, $validated['ext']);
        if (!move_uploaded_file($validated['tmp_name'], $dirUploads . '/' . $filename)) {
            return null;
        }

        return '/uploads/empresas/' . $empresaId . '/branding/' . $filename;
    }
}
