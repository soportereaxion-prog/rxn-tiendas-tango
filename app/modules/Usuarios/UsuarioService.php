<?php

declare(strict_types=1);

namespace App\Modules\Usuarios;

use App\Core\Context;
use App\Modules\Auth\Usuario;
use App\Modules\Auth\UsuarioRepository;
use RuntimeException;

class UsuarioService
{
    private UsuarioRepository $repository;

    public function __construct()
    {
        $this->repository = new UsuarioRepository();
    }

    private function getContextId(): int
    {
        $empresaId = Context::getEmpresaId();
        if ($empresaId === null) {
            throw new RuntimeException('Operación Denegada: Contexto de empresa inactivo o inválido.');
        }
        return $empresaId;
    }

    public function getAllForContext(): array
    {
        return $this->repository->findAllByEmpresaId($this->getContextId());
    }

    public function getByIdForContext(int $id): Usuario
    {
        // Requisito 2: "Todo acceso debe quedar encerrado en la empresa activa"
        // Esta linea asegura un query: WHERE id = X AND empresa_id = Y
        $usuario = $this->repository->findByIdAndEmpresaId($id, $this->getContextId());
        
        if (!$usuario) {
            throw new RuntimeException("Rechazado: El usuario solicitado no existe o no pertenece a la titularidad de esta Empresa.");
        }
        return $usuario;
    }

    public function create(array $data): void
    {
        $empresaId = $this->getContextId();
        
        $this->validateEmail($data['email'] ?? '', null);

        if (empty($data['password'])) {
            throw new RuntimeException('La contraseña es obligatoria para un nueva cuenta.');
        }

        $usuario = new Usuario();
        $usuario->empresa_id = $empresaId; // Injectamos el contexto de SESION sin leer el POST jamas.
        $usuario->nombre = trim($data['nombre'] ?? '');
        $usuario->email = trim($data['email'] ?? '');
        $usuario->password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $usuario->activo = isset($data['activo']) && $data['activo'] === 'on' ? 1 : 0;
        $usuario->es_admin = isset($data['es_admin']) && $data['es_admin'] === 'on' ? 1 : 0;

        // Forced Email Lifecycle (No verification = No Login)
        $usuario->email_verificado = 0;
        $usuario->verification_token = bin2hex(random_bytes(16));
        $usuario->verification_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $this->repository->save($usuario);

        $mailService = new \App\Core\Services\MailService();
        $mailService->sendVerificationEmail($usuario->email, $usuario->nombre, $usuario->verification_token, $empresaId);
    }

    public function update(int $id, array $data): void
    {
        // El getByIdForContext frena al controlador antes de hacer daño si se manipula el ID
        $usuario = $this->getByIdForContext($id);
        
        $this->validateEmail($data['email'] ?? '', $id);

        $usuario->nombre = trim($data['nombre'] ?? '');
        $usuario->email = trim($data['email'] ?? '');
        $usuario->activo = isset($data['activo']) && $data['activo'] === 'on' ? 1 : 0;
        $usuario->es_admin = isset($data['es_admin']) && $data['es_admin'] === 'on' ? 1 : 0;

        if (!empty($data['password'])) {
            $usuario->password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $this->repository->save($usuario);
    }

    private function validateEmail(string $email, ?int $excludeId): void
    {
        if (empty($email)) {
            throw new RuntimeException('El correo electrónico es obligatorio.');
        }

        // En RXN Fase 1 definimos email único GLOBAlmente por estar la key UNIQUE integrada en el esquema de base de datos MariaDB base.
        $existente = $this->repository->findByEmail($email, $excludeId);
        if ($existente) {
            throw new RuntimeException('El correo electrónico ya se encuentra registrado (Bloqueo Global).');
        }
    }
}
