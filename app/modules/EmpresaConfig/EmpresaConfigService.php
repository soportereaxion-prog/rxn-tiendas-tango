<?php

declare(strict_types=1);

namespace App\Modules\EmpresaConfig;

use App\Core\Context;
use RuntimeException;

class EmpresaConfigService
{
    private EmpresaConfigRepository $repository;

    public function __construct()
    {
        $this->repository = new EmpresaConfigRepository();
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

        $this->repository->save($config);
    }
}
