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

        // --- MÓDULO IMAGEN FALLBACK ---
        if (isset($_FILES['imagen_default']) && $_FILES['imagen_default']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['imagen_default']['tmp_name'];
            $name = $_FILES['imagen_default']['name'];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $dirUploads = __DIR__ . '/../../../public/uploads/empresas/' . $empresaId . '/' . $this->uploadSegment;
                if (!is_dir($dirUploads)) {
                    mkdir($dirUploads, 0777, true);
                }
                
                $filename = 'fallback_' . time() . '.' . $ext;
                $rutaAbsoluta = $dirUploads . '/' . $filename;
                
                if (move_uploaded_file($tmpName, $rutaAbsoluta)) {
                    $config->imagen_default_producto = '/uploads/empresas/' . $empresaId . '/' . $this->uploadSegment . '/' . $filename;
                    // Limpieza obligatoria del menú ya que cambiamos el aspet global visual
                    if ($this->clearStoreCache) {
                        \App\Core\FileCache::clearPrefix("catalogo_empresa_{$empresaId}");
                    }
                }
            }
        }

        // --- IMAGENES DE IMPRESIÓN ---
        if (isset($_FILES['impresion_header']) && $_FILES['impresion_header']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['impresion_header']['tmp_name'];
            $name = $_FILES['impresion_header']['name'];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $dirUploads = __DIR__ . '/../../../public/uploads/empresas/' . $empresaId . '/' . $this->uploadSegment;
                if (!is_dir($dirUploads)) {
                    mkdir($dirUploads, 0777, true);
                }
                
                $filename = 'header_' . time() . '.' . $ext;
                $rutaAbsoluta = $dirUploads . '/' . $filename;
                
                if (move_uploaded_file($tmpName, $rutaAbsoluta)) {
                    $config->impresion_header_url = '/uploads/empresas/' . $empresaId . '/' . $this->uploadSegment . '/' . $filename;
                }
            }
        }

        if (isset($_FILES['impresion_footer']) && $_FILES['impresion_footer']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['impresion_footer']['tmp_name'];
            $name = $_FILES['impresion_footer']['name'];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $dirUploads = __DIR__ . '/../../../public/uploads/empresas/' . $empresaId . '/' . $this->uploadSegment;
                if (!is_dir($dirUploads)) {
                    mkdir($dirUploads, 0777, true);
                }
                
                $filename = 'footer_' . time() . '.' . $ext;
                $rutaAbsoluta = $dirUploads . '/' . $filename;
                
                if (move_uploaded_file($tmpName, $rutaAbsoluta)) {
                    $config->impresion_footer_url = '/uploads/empresas/' . $empresaId . '/' . $this->uploadSegment . '/' . $filename;
                }
            }
        }

        $this->repository->save($config);
    }
}
