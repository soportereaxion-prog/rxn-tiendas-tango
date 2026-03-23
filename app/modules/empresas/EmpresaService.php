<?php

declare(strict_types=1);

namespace App\Modules\Empresas;

use InvalidArgumentException;

class EmpresaService
{
    private EmpresaRepository $repository;

    public function __construct()
    {
        $this->repository = new EmpresaRepository();
    }

    public function findAll(): array
    {
        return $this->repository->findAll();
    }

    public function create(array $data): void
    {
        if (empty($data['codigo']) || empty($data['nombre'])) {
            throw new InvalidArgumentException('El código y el nombre son obligatorios.');
        }

        $empresa = new Empresa();
        $empresa->codigo = trim($data['codigo']);
        $empresa->nombre = trim($data['nombre']);
        $empresa->razon_social = isset($data['razon_social']) ? trim($data['razon_social']) : null;
        $empresa->cuit = isset($data['cuit']) ? trim($data['cuit']) : null;
        $empresa->activa = 1;

        $this->repository->save($empresa);
    }
}
