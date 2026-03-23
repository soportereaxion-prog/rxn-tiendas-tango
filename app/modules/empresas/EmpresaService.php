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

    public function findById(int $id): ?Empresa
    {
        return $this->repository->findById($id);
    }

    public function create(array $data): void
    {
        if (empty($data['codigo']) || empty($data['nombre'])) {
            throw new InvalidArgumentException('El código y el nombre son obligatorios.');
        }

        if ($this->repository->findByCodigo(trim($data['codigo']))) {
            throw new InvalidArgumentException('El código ya está en uso por otra empresa.');
        }

        $empresa = new Empresa();
        $empresa->codigo = trim($data['codigo']);
        $empresa->nombre = trim($data['nombre']);
        $empresa->razon_social = !empty($data['razon_social']) ? trim($data['razon_social']) : null;
        $empresa->cuit = !empty($data['cuit']) ? trim($data['cuit']) : null;
        $empresa->activa = isset($data['activa']) ? 1 : 0;

        $this->repository->save($empresa);
    }

    public function update(int $id, array $data): void
    {
        $empresa = $this->repository->findById($id);
        if (!$empresa) {
            throw new InvalidArgumentException('Empresa no encontrada.');
        }

        if (empty($data['codigo']) || empty($data['nombre'])) {
            throw new InvalidArgumentException('El código y el nombre son obligatorios.');
        }

        if ($this->repository->findByCodigo(trim($data['codigo']), $id)) {
            throw new InvalidArgumentException('El código ya está en uso por otra empresa.');
        }

        $empresa->codigo = trim($data['codigo']);
        $empresa->nombre = trim($data['nombre']);
        $empresa->razon_social = !empty($data['razon_social']) ? trim($data['razon_social']) : null;
        $empresa->cuit = !empty($data['cuit']) ? trim($data['cuit']) : null;
        $empresa->activa = isset($data['activa']) ? 1 : 0;

        $this->repository->update($empresa);
    }
}
