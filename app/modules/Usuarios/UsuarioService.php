<?php

declare(strict_types=1);

namespace App\Modules\Usuarios;

use App\Core\Context;
use App\Modules\Auth\Usuario;
use App\Modules\Auth\UsuarioRepository;
use RuntimeException;

class UsuarioService
{
    private const SEARCH_FIELDS = ['all', 'id', 'nombre', 'email'];
    private const SORT_FIELDS = ['id', 'nombre', 'email', 'es_admin', 'activo'];
    private const SORT_DIRECTIONS = ['asc', 'desc'];
    private const PER_PAGE = 10;
    private const SUGGESTION_LIMIT = 3;
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

    private function canManageAdminPrivileges(): bool
    {
        return (!empty($_SESSION['es_rxn_admin']) && $_SESSION['es_rxn_admin'] == 1)
            || (!empty($_SESSION['es_admin']) && $_SESSION['es_admin'] == 1);
    }

    public function getAllForContext(): array
    {
        return $this->findAllForContext();
    }

    public function findAllForContext(array $filters = []): array
    {
        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        $field = $this->normalizeSearchField($filters['field'] ?? 'all');
        $sort = $this->normalizeSortField($filters['sort'] ?? 'id');
        $dir = $this->normalizeSortDirection($filters['dir'] ?? 'desc');
        $isGlobalAdmin = !empty($_SESSION['es_rxn_admin']) && $_SESSION['es_rxn_admin'] == 1;

        if ($isGlobalAdmin) {
            $total = $this->repository->countAll();
            $filteredTotal = $this->repository->countFiltered($search, $field);
        } else {
            $empresaId = $this->getContextId();
            $total = $this->repository->countAllByEmpresaId($empresaId);
            $filteredTotal = $this->repository->countFilteredByEmpresaId($empresaId, $search, $field);
        }

        $lastPage = max(1, (int) ceil($filteredTotal / self::PER_PAGE));
        $page = $this->normalizePage($filters['page'] ?? 1, $lastPage);
        $offset = ($page - 1) * self::PER_PAGE;

        if ($isGlobalAdmin) {
            $items = $this->repository->findFilteredPaginated($search, $field, $sort, $dir, self::PER_PAGE, $offset);
        } else {
            $items = $this->repository->findFilteredPaginatedByEmpresaId($empresaId, $search, $field, $sort, $dir, self::PER_PAGE, $offset);
        }

        return [
            'items' => $items,
            'filters' => [
                'search' => $search,
                'field' => $field,
                'sort' => $sort,
                'dir' => $dir,
                'page' => $page,
            ],
            'total' => $total,
            'filteredTotal' => $filteredTotal,
            'pagination' => [
                'page' => $page,
                'perPage' => self::PER_PAGE,
                'totalPages' => $lastPage,
                'hasPrevious' => $page > 1,
                'hasNext' => $page < $lastPage,
                'previousPage' => max(1, $page - 1),
                'nextPage' => min($lastPage, $page + 1),
            ],
        ];
    }

    public function findSuggestionsForContext(array $filters = []): array
    {
        $search = isset($filters['q']) ? trim((string) $filters['q']) : '';
        $field = $this->normalizeSearchField($filters['field'] ?? 'all');
        $isGlobalAdmin = !empty($_SESSION['es_rxn_admin']) && $_SESSION['es_rxn_admin'] == 1;

        if (mb_strlen($search) < 2) {
            return [];
        }

        if ($isGlobalAdmin) {
            $rows = $this->repository->findSuggestions($search, $field, self::SUGGESTION_LIMIT);
        } else {
            $rows = $this->repository->findSuggestionsByEmpresaId($this->getContextId(), $search, $field, self::SUGGESTION_LIMIT);
        }

        return array_map(function (array $row) use ($field): array {
            $nombre = trim((string) ($row['nombre'] ?? 'Usuario'));
            $email = trim((string) ($row['email'] ?? ''));
            $value = match ($field) {
                'id' => (string) ((int) ($row['id'] ?? 0)),
                'email' => $email,
                default => $nombre,
            };

            return [
                'id' => (int) ($row['id'] ?? 0),
                'label' => $nombre,
                'value' => $value !== '' ? $value : $nombre,
                'caption' => '#'. (int) ($row['id'] ?? 0) . ' | ' . ($email !== '' ? $email : 'Sin email'),
            ];
        }, $rows);
    }

    public function getByIdForContext(int $id): Usuario
    {
        if (!empty($_SESSION['es_rxn_admin']) && $_SESSION['es_rxn_admin'] == 1) {
            $usuario = $this->repository->findById($id);
        } else {
            $usuario = $this->repository->findByIdAndEmpresaId($id, $this->getContextId());
        }
        
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

        $isGlobalAdmin = (!empty($_SESSION['es_rxn_admin']) && $_SESSION['es_rxn_admin'] == 1);
        if ($isGlobalAdmin && !empty($data['empresa_id'])) {
            $empresaId = (int)$data['empresa_id'];
        }

        $usuario = new Usuario();
        $usuario->empresa_id = $empresaId; // Injectamos el contexto (dinámico si es RXN admin)
        $usuario->nombre = trim($data['nombre'] ?? '');
        $usuario->email = trim($data['email'] ?? '');
        $usuario->password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $usuario->activo = isset($data['activo']) && $data['activo'] === 'on' ? 1 : 0;
        $usuario->es_admin = $this->canManageAdminPrivileges() && isset($data['es_admin']) && $data['es_admin'] === 'on' ? 1 : 0;

        if (!empty($data['tango_perfil_pedido'])) {
            $parts = explode('|', $data['tango_perfil_pedido']);
            if (count($parts) === 3) {
                $usuario->tango_perfil_pedido_id = (int) $parts[0];
                $usuario->tango_perfil_pedido_codigo = $parts[1];
                $usuario->tango_perfil_pedido_nombre = $parts[2];

                try {
                    $configRepo = new \App\Modules\EmpresaConfig\EmpresaConfigRepository();
                    $config = $configRepo->findByEmpresaId($usuario->empresa_id);
                    if ($config && trim((string)($config->tango_connect_token ?? '')) !== '') {
                        $apiUrl = rtrim($config->tango_api_url ?? '', '/');
                        if (!preg_match('/\/api$/i', $apiUrl)) {
                            $apiUrl .= '/Api';
                        }
                        $tangoClient = new \App\Modules\Tango\TangoApiClient(
                            $apiUrl,
                            $config->tango_connect_token,
                            $config->tango_connect_company_id ?? '',
                            $config->tango_connect_key ?? ''
                        );
                        $perfilData = $tangoClient->getPerfilPedidoById($usuario->tango_perfil_pedido_id);
                        if ($perfilData) {
                            $usuario->tango_perfil_snapshot_json = json_encode($perfilData, JSON_UNESCAPED_UNICODE);
                            $usuario->tango_perfil_snapshot_date = date('Y-m-d H:i:s');
                        }
                    }
                } catch (\Throwable $e) {}
            }
        }

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
        $usuario->es_admin = $this->canManageAdminPrivileges() && isset($data['es_admin']) && $data['es_admin'] === 'on' ? 1 : 0;

        $isGlobalAdmin = (!empty($_SESSION['es_rxn_admin']) && $_SESSION['es_rxn_admin'] == 1);
        if ($isGlobalAdmin && !empty($data['empresa_id'])) {
            $usuario->empresa_id = (int)$data['empresa_id'];
        }

        if (isset($data['tango_perfil_pedido'])) {
            if ($data['tango_perfil_pedido'] === '') {
                $usuario->tango_perfil_pedido_id = null;
                $usuario->tango_perfil_pedido_codigo = null;
                $usuario->tango_perfil_pedido_nombre = null;
                $usuario->tango_perfil_snapshot_json = null;
                $usuario->tango_perfil_snapshot_date = null;
            } else {
                $parts = explode('|', $data['tango_perfil_pedido']);
                if (count($parts) === 3) {
                    $usuario->tango_perfil_pedido_id = (int) $parts[0];
                    $usuario->tango_perfil_pedido_codigo = $parts[1];
                    $usuario->tango_perfil_pedido_nombre = $parts[2];

                    try {
                        $configRepo = new \App\Modules\EmpresaConfig\EmpresaConfigRepository();
                        $config = $configRepo->findByEmpresaId($usuario->empresa_id);
                        if ($config && trim((string)($config->tango_connect_token ?? '')) !== '') {
                            $apiUrl = rtrim($config->tango_api_url ?? '', '/');
                            if (!preg_match('/\/api$/i', $apiUrl)) {
                                $apiUrl .= '/Api';
                            }
                            $tangoClient = new \App\Modules\Tango\TangoApiClient(
                                $apiUrl,
                                $config->tango_connect_token,
                                $config->tango_connect_company_id ?? '',
                                $config->tango_connect_key ?? ''
                            );
                            $perfilData = $tangoClient->getPerfilPedidoById($usuario->tango_perfil_pedido_id);
                            if ($perfilData) {
                                $usuario->tango_perfil_snapshot_json = json_encode($perfilData, JSON_UNESCAPED_UNICODE);
                                $usuario->tango_perfil_snapshot_date = date('Y-m-d H:i:s');
                            }
                        }
                    } catch (\Throwable $e) {}
                }
            }
        }

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

    private function normalizeSortField(string $field): string
    {
        return in_array($field, self::SORT_FIELDS, true) ? $field : 'id';
    }

    private function normalizeSearchField(string $field): string
    {
        return in_array($field, self::SEARCH_FIELDS, true) ? $field : 'all';
    }

    private function normalizeSortDirection(string $direction): string
    {
        $direction = strtolower($direction);

        return in_array($direction, self::SORT_DIRECTIONS, true) ? $direction : 'desc';
    }

    private function normalizePage(mixed $page, int $lastPage): int
    {
        $pageNumber = filter_var($page, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($pageNumber === false) {
            return 1;
        }

        return min($pageNumber, $lastPage);
    }
}
