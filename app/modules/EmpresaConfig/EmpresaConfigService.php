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
        
        // Solo sobrescribimos token si viene en el post con info nueva para no purgar uno existente por descuido
        if (isset($data['tango_connect_token']) && $data['tango_connect_token'] !== '') {
            $config->tango_connect_token = trim($data['tango_connect_token']);
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

        $this->repository->save($config);
    }
}
