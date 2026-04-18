<?php

declare(strict_types=1);

namespace App\Modules\EmpresaConfig;

use App\Core\Context;
use RuntimeException;

class EmpresaConfigService
{
    private EmpresaConfigRepository $repository;
    private string $uploadSegment;
    private bool $clearStoreCache;

    public function __construct(?EmpresaConfigRepository $repository = null, string $uploadSegment = 'config', bool $clearStoreCache = true)
    {
        $this->repository = $repository ?? new EmpresaConfigRepository();
        $this->uploadSegment = $uploadSegment;
        $this->clearStoreCache = $clearStoreCache;
    }

    public static function forCrm(): self
    {
        return new self(EmpresaConfigRepository::forCrm(), 'config-crm', false);
    }

    public static function forArea(string $area): self
    {
        return strtolower(trim($area)) === 'crm'
            ? self::forCrm()
            : new self();
    }

    private function getContextId(): int
    {
        $empresaId = Context::getEmpresaId();
        if ($empresaId === null) {
            throw new RuntimeException('Contexto Multiempresa Fallido: No se ha detectado ninguna empresa activa en sesión.');
        }
        return $empresaId;
    }

    /**
     * Obtiene la configuración para el contexto actual.
     * Si no existe, retorna una instancia limpia de la entidad.
     */
    public function getConfig(): EmpresaConfig
    {
        $empresaId = $this->getContextId();
        $config = $this->repository->findByEmpresaId($empresaId);
        
        if (!$config) {
            $config = new EmpresaConfig();
            $config->empresa_id = $empresaId;
        }

        return $config;
    }

    /**
     * Guarda la configuración atada exclusivamente al Contexto actual.
     */
    public function save(array $data): void
    {
        $empresaId = $this->getContextId();
        
        // Evaluamos si ya existe antes en DB
        $config = $this->repository->findByEmpresaId($empresaId);
        if (!$config) {
            $config = new EmpresaConfig();
            $config->empresa_id = $empresaId;
        }

        $config->nombre_fantasia = !empty($data['nombre_fantasia']) ? trim($data['nombre_fantasia']) : null;
        $config->email_contacto  = !empty($data['email_contacto']) ? trim($data['email_contacto']) : null;
        $config->telefono        = !empty($data['telefono']) ? trim($data['telefono']) : null;

        $config->tango_api_url       = !empty($data['tango_api_url']) ? trim($data['tango_api_url']) : null;
        $config->tango_connect_key   = !empty($data['tango_connect_key']) ? trim($data['tango_connect_key']) : null;
        $config->tango_connect_company_id = !empty($data['tango_connect_company_id']) ? trim($data['tango_connect_company_id']) : null;
        
        // Validación Límite de Sincronización
        $limiteStr = trim((string)($data['cantidad_articulos_sync'] ?? ''));
        $limiteInt = is_numeric($limiteStr) ? (int)$limiteStr : 50;
        $config->cantidad_articulos_sync = $limiteInt > 0 ? $limiteInt : 50;
        
        $config->lista_precio_1 = !empty($data['lista_precio_1']) ? trim($data['lista_precio_1']) : null;
        $config->lista_precio_2 = !empty($data['lista_precio_2']) ? trim($data['lista_precio_2']) : null;
        $config->deposito_codigo = !empty($data['deposito_codigo']) ? mb_substr(trim($data['deposito_codigo']), 0, 2) : null;
        
        $config->pds_numero_base = isset($data['pds_numero_base']) && $data['pds_numero_base'] !== '' ? (int)$data['pds_numero_base'] : 0;
        $config->presupuesto_numero_base = isset($data['presupuesto_numero_base']) && $data['presupuesto_numero_base'] !== '' ? (int)$data['presupuesto_numero_base'] : 0;
        $config->pds_email_pdf_canvas_id = isset($data['pds_email_pdf_canvas_id']) && $data['pds_email_pdf_canvas_id'] !== '' ? (int)$data['pds_email_pdf_canvas_id'] : null;
        $config->presupuesto_email_pdf_canvas_id = isset($data['presupuesto_email_pdf_canvas_id']) && $data['presupuesto_email_pdf_canvas_id'] !== '' ? (int)$data['presupuesto_email_pdf_canvas_id'] : null;
        $config->pds_email_body_canvas_id = isset($data['pds_email_body_canvas_id']) && $data['pds_email_body_canvas_id'] !== '' ? (int)$data['pds_email_body_canvas_id'] : null;
        $config->presupuesto_email_body_canvas_id = isset($data['presupuesto_email_body_canvas_id']) && $data['presupuesto_email_body_canvas_id'] !== '' ? (int)$data['presupuesto_email_body_canvas_id'] : null;
        $config->pds_email_asunto = isset($data['pds_email_asunto']) && $data['pds_email_asunto'] !== '' ? trim($data['pds_email_asunto']) : null;
        $config->presupuesto_email_asunto = isset($data['presupuesto_email_asunto']) && $data['presupuesto_email_asunto'] !== '' ? trim($data['presupuesto_email_asunto']) : null;
        $config->tango_pds_talonario_id = isset($data['tango_pds_talonario_id']) && $data['tango_pds_talonario_id'] !== '' ? (int)$data['tango_pds_talonario_id'] : null;
        
        // Solo sobrescribimos token si viene en el post con info nueva para no purgar uno existente por descuido
        if (isset($data['tango_connect_token']) && $data['tango_connect_token'] !== '') {
            $config->tango_connect_token = trim($data['tango_connect_token']);
        }
        
        // Guardado de perfiles en background (para uso de administración de usuarios)
        if (isset($data['tango_perfil_snapshot_json']) && $data['tango_perfil_snapshot_json'] !== '') {
            $config->tango_perfil_snapshot_json = trim($data['tango_perfil_snapshot_json']);
            $config->tango_perfil_snapshot_date = date('Y-m-d H:i:s');
        }

        // --- SMTP SETTINGS ---
        $config->usa_smtp_propio = isset($data['usa_smtp_propio']) && filter_var($data['usa_smtp_propio'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        $config->smtp_host = !empty($data['smtp_host']) ? trim($data['smtp_host']) : null;
        $config->smtp_port = !empty($data['smtp_port']) ? (int)$data['smtp_port'] : null;
        $config->smtp_user = !empty($data['smtp_user']) ? trim($data['smtp_user']) : null;
        $config->smtp_secure = !empty($data['smtp_secure']) ? trim($data['smtp_secure']) : null;
        $config->smtp_from_email = !empty($data['smtp_from_email']) ? trim($data['smtp_from_email']) : null;
        $config->smtp_from_name = !empty($data['smtp_from_name']) ? trim($data['smtp_from_name']) : null;

        if (isset($data['smtp_pass']) && $data['smtp_pass'] !== '') {
                $config->smtp_pass = trim($data['smtp_pass']);
        }

        // --- GOOGLE CALENDAR OAUTH (per-empresa, solo CRM) ---
        $config->google_oauth_client_id = !empty($data['google_oauth_client_id']) ? trim($data['google_oauth_client_id']) : ($config->google_oauth_client_id ?? null);
        $config->google_oauth_redirect_uri = !empty($data['google_oauth_redirect_uri']) ? trim($data['google_oauth_redirect_uri']) : ($config->google_oauth_redirect_uri ?? null);
        $config->agenda_google_auth_mode = !empty($data['agenda_google_auth_mode']) && in_array($data['agenda_google_auth_mode'], ['usuario', 'empresa', 'ambos'], true)
            ? trim($data['agenda_google_auth_mode'])
            : ($config->agenda_google_auth_mode ?? 'usuario');

        // El client_secret se encripta al guardar. Si viene vacio, se preserva el existente.
        if (isset($data['google_oauth_client_secret']) && trim($data['google_oauth_client_secret']) !== '') {
            try {
                $oauthSvc = new \App\Modules\CrmAgenda\GoogleOAuthService();
                $config->google_oauth_client_secret = $oauthSvc->encrypt(trim($data['google_oauth_client_secret']), $empresaId);
            } catch (\Throwable) {
                // Si falla encriptacion (APP_KEY no definida), guardar en texto plano como fallback
                $config->google_oauth_client_secret = trim($data['google_oauth_client_secret']);
            }
        }

        // --- MÓDULO IMAGEN FALLBACK ---
        $fallbackPath = $this->handleImageUpload($_FILES['imagen_default'] ?? null, $empresaId, 'fallback');
        if ($fallbackPath !== null) {
            $config->imagen_default_producto = $fallbackPath;
            // Limpieza obligatoria del menú ya que cambiamos el aspecto global visual
            if ($this->clearStoreCache) {
                \App\Core\FileCache::clearPrefix("catalogo_empresa_{$empresaId}");
            }
        }

        // --- IMAGENES DE IMPRESIÓN ---
        $headerPath = $this->handleImageUpload($_FILES['impresion_header'] ?? null, $empresaId, 'header');
        if ($headerPath !== null) {
            $config->impresion_header_url = $headerPath;
        }

        $footerPath = $this->handleImageUpload($_FILES['impresion_footer'] ?? null, $empresaId, 'footer');
        if ($footerPath !== null) {
            $config->impresion_footer_url = $footerPath;
        }

        $this->repository->save($config);
    }

    /**
     * Recibe una entrada de $_FILES y la procesa con UploadValidator.
     * Retorna la ruta pública relativa o null si no se subió archivo.
     */
    private function handleImageUpload(?array $file, int $empresaId, string $prefix): ?string
    {
        if (!is_array($file)) {
            return null;
        }

        try {
            $validated = \App\Core\UploadValidator::image($file);
        } catch (\RuntimeException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            return null;
        }

        if ($validated === null) {
            return null;
        }

        $dirUploads = __DIR__ . '/../../../public/uploads/empresas/' . $empresaId . '/' . $this->uploadSegment;
        \App\Core\UploadValidator::prepareDir($dirUploads);

        $filename = \App\Core\UploadValidator::generateFilename($prefix, $empresaId, $validated['ext']);
        $rutaAbsoluta = $dirUploads . '/' . $filename;

        if (!move_uploaded_file($validated['tmp_name'], $rutaAbsoluta)) {
            return null;
        }

        return '/uploads/empresas/' . $empresaId . '/' . $this->uploadSegment . '/' . $filename;
    }
}
