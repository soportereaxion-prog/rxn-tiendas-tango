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

    /**
     * Aplica los 11 flags de módulos por usuario desde el POST.
     * Sólo el editor admin puede tocar estos flags — el no-admin que ABRE la
     * vista los ve disabled y, aunque manipulara el HTML, este chequeo de
     * servidor descarta cualquier intento.
     *
     * Para usuarios nuevos creados por no-admin, los flags quedan en el
     * default del modelo (1 = todos habilitados).
     */
    private function applyModuleFlags(\App\Modules\Auth\Usuario $usuario, array $data): void
    {
        if (!$this->canManageAdminPrivileges()) {
            return;
        }

        $modules = [
            'usuario_modulo_notas',
            'usuario_modulo_llamadas',
            'usuario_modulo_monitoreo',
            'usuario_modulo_rxn_live',
            'usuario_modulo_pedidos_servicio',
            'usuario_modulo_agenda',
            'usuario_modulo_mail_masivos',
            'usuario_modulo_horas_turnero',
            'usuario_modulo_geo_tracking',
            'usuario_modulo_presupuestos_pwa',
            'usuario_modulo_horas_pwa',
        ];

        foreach ($modules as $col) {
            $usuario->$col = isset($data[$col]) ? 1 : 0;
        }
    }

    public function getAllForContext(): array
    {
        return $this->findAllForContext();
    }

    public function findAllForContext(array $filters = [], array $advancedFilters = []): array
    {
        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        $field = $this->normalizeSearchField($filters['field'] ?? 'all');
        $sort = $this->normalizeSortField($filters['sort'] ?? 'id');
        $dir = $this->normalizeSortDirection($filters['dir'] ?? 'desc');
        $status = $filters['status'] ?? 'activos';
        $onlyDeleted = $status === 'papelera';
        $isGlobalAdmin = !empty($_SESSION['es_rxn_admin']) && $_SESSION['es_rxn_admin'] == 1;

        if ($isGlobalAdmin) {
            $total = $this->repository->countAll($onlyDeleted);
            $filteredTotal = $this->repository->countFiltered($search, $field, $onlyDeleted, $advancedFilters);
        } else {
            $empresaId = $this->getContextId();
            $total = $this->repository->countAllByEmpresaId($empresaId, $onlyDeleted);
            $filteredTotal = $this->repository->countFilteredByEmpresaId($empresaId, $search, $field, $onlyDeleted, $advancedFilters);
        }

        $lastPage = max(1, (int) ceil($filteredTotal / self::PER_PAGE));
        $page = $this->normalizePage($filters['page'] ?? 1, $lastPage);
        $offset = ($page - 1) * self::PER_PAGE;

        if ($isGlobalAdmin) {
            $items = $this->repository->findFilteredPaginated($search, $field, $sort, $dir, self::PER_PAGE, $offset, $onlyDeleted, $advancedFilters);
        } else {
            $items = $this->repository->findFilteredPaginatedByEmpresaId($empresaId, $search, $field, $sort, $dir, self::PER_PAGE, $offset, $onlyDeleted, $advancedFilters);
        }

        return [
            'items' => $items,
            'filters' => [
                'search' => $search,
                'field' => $field,
                'sort' => $sort,
                'dir' => $dir,
                'page' => $page,
                'status' => $status,
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

    public function getByIdForContext(int $id, bool $includeDeleted = false): Usuario
    {
        // Actually, we use a separate query or bypass it locally to avoid rewriting all methods for includeDeleted.
        // Let's implement a small hack or just run a specific query for checking ownership in restore/forceDelete.
        // Wait, for now let's just use raw query to check ownership inside restore/forceDelete.
        if (!empty($_SESSION['es_rxn_admin']) && $_SESSION['es_rxn_admin'] == 1) {
            $usuario = $this->repository->findById($id);
        } else {
            $usuario = $this->repository->findByIdAndEmpresaId($id, $this->getContextId());
        }
        
        if (!$usuario && !$includeDeleted) {
            throw new RuntimeException("Rechazado: El usuario solicitado no existe o no pertenece a la titularidad de esta Empresa.");
        }
        return $usuario ?: new Usuario();
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
        $usuario->nombre = strip_tags(trim($data['nombre'] ?? ''));
        $usuario->email = filter_var(trim($data['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $usuario->password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $usuario->activo = isset($data['activo']) && $data['activo'] === 'on' ? 1 : 0;
        $usuario->es_admin = $this->canManageAdminPrivileges() && isset($data['es_admin']) && $data['es_admin'] === 'on' ? 1 : 0;
        
        if (\App\Modules\Auth\AuthService::isRxnAdmin()) {
            $usuario->es_rxn_admin = isset($data['es_rxn_admin']) && $data['es_rxn_admin'] === 'on' ? 1 : 0;
        }
        
        $anuraInterno = trim($data['anura_interno'] ?? '');
        if ($anuraInterno !== '') {
            $this->validateAnuraInterno($anuraInterno, $empresaId, null);
        }
        $usuario->anura_interno = $anuraInterno !== '' ? $anuraInterno : null;

        if (!empty($data['tango_perfil_pedido'])) {
            $parts = explode('|', $data['tango_perfil_pedido']);
            if (count($parts) === 3) {
                $usuario->tango_perfil_pedido_id = (int) $parts[0];
                $usuario->tango_perfil_pedido_codigo = $parts[1];
                $usuario->tango_perfil_pedido_nombre = $parts[2];

                    if (!empty($data['tango_perfil_snapshot_json'])) {
                        $usuario->tango_perfil_snapshot_json = $data['tango_perfil_snapshot_json'];
                        $usuario->tango_perfil_snapshot_date = date('Y-m-d H:i:s');
                    } else {
                        try {
                            $configRepo = new \App\Modules\EmpresaConfig\EmpresaConfigRepository();
                            $config = $configRepo->findByEmpresaId($usuario->empresa_id);
                            if ($config && trim((string)($config->tango_connect_token ?? '')) !== '') {
                                $snapshotService = new \App\Modules\Tango\Services\TangoProfileSnapshotService();
                                $perfilData = $snapshotService->fetch($config, $usuario->tango_perfil_pedido_id);
                                if ($perfilData) {
                                    $usuario->tango_perfil_snapshot_json = json_encode($perfilData, JSON_UNESCAPED_UNICODE);
                                    $usuario->tango_perfil_snapshot_date = date('Y-m-d H:i:s');
                                }
                            }
                        } catch (\Throwable $e) {}
                    }
            }
        }

        // Forced Email Lifecycle (No verification = No Login)
        $usuario->email_verificado = 0;
        $usuario->verification_token = bin2hex(random_bytes(16));
        $usuario->verification_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $this->applyModuleFlags($usuario, $data);

        $this->repository->save($usuario);

        $mailService = new \App\Core\Services\MailService();
        $mailService->sendVerificationEmail($usuario->email, $usuario->nombre, $usuario->verification_token, $empresaId);
    }

    public function update(int $id, array $data): void
    {
        // El getByIdForContext frena al controlador antes de hacer daño si se manipula el ID
        $usuario = $this->getByIdForContext($id);
        
        $this->validateEmail($data['email'] ?? '', $id);

        $usuario->nombre = strip_tags(trim($data['nombre'] ?? ''));
        $usuario->email = filter_var(trim($data['email'] ?? ''), FILTER_SANITIZE_EMAIL);

        // Defensa server-side del flag `activo`. Reglas:
        //   1) Nadie puede auto-desactivarse (ni siquiera super admin) — el operador
        //      perdería su propia sesión. Eliminación/baja se hace por papelera.
        //   2) Solo admin (es_admin o es_rxn_admin) puede tocar este flag de otros.
        // Si alguna regla falla, preservamos el valor actual del DB en lugar de leer del POST,
        // así un POST manipulado no rompe el invariante.
        $isSelfEdit = ((int) $id === (int) ($_SESSION['user_id'] ?? 0));
        $canToggleActivo = !$isSelfEdit && $this->canManageAdminPrivileges();
        if ($canToggleActivo) {
            $usuario->activo = isset($data['activo']) && $data['activo'] === 'on' ? 1 : 0;
        }
        $usuario->es_admin = $this->canManageAdminPrivileges() && isset($data['es_admin']) && $data['es_admin'] === 'on' ? 1 : 0;

        if (\App\Modules\Auth\AuthService::isRxnAdmin()) {
            $usuario->es_rxn_admin = isset($data['es_rxn_admin']) && $data['es_rxn_admin'] === 'on' ? 1 : 0;
        }

        $anuraInterno = trim($data['anura_interno'] ?? '');
        if ($anuraInterno !== '') {
            $this->validateAnuraInterno($anuraInterno, $usuario->empresa_id, $id);
        }
        $usuario->anura_interno = $anuraInterno !== '' ? $anuraInterno : null;

        $isGlobalAdmin = (!empty($_SESSION['es_rxn_admin']) && $_SESSION['es_rxn_admin'] == 1);
        if ($isGlobalAdmin && !empty($data['empresa_id'])) {
            $usuario->empresa_id = (int)$data['empresa_id'];
        }

        $this->applyModuleFlags($usuario, $data);

        if (isset($data['tango_perfil_pedido'])) {
            $currentProfileId = $usuario->tango_perfil_pedido_id;

            if ($data['tango_perfil_pedido'] === '') {
                $usuario->tango_perfil_pedido_id = null;
                $usuario->tango_perfil_pedido_codigo = null;
                $usuario->tango_perfil_pedido_nombre = null;
                $usuario->tango_perfil_snapshot_json = null;
                $usuario->tango_perfil_snapshot_date = null;
            } else {
                $parts = explode('|', $data['tango_perfil_pedido']);
                if (count($parts) === 3) {
                    $newProfileId = (int) $parts[0];
                    $profileChanged = ($currentProfileId !== $newProfileId);

                    $usuario->tango_perfil_pedido_id = $newProfileId;
                    $usuario->tango_perfil_pedido_codigo = $parts[1];
                    $usuario->tango_perfil_pedido_nombre = $parts[2];

                    if (!$profileChanged && !empty($data['tango_perfil_snapshot_json'])) {
                        $usuario->tango_perfil_snapshot_json = $data['tango_perfil_snapshot_json'];
                        $usuario->tango_perfil_snapshot_date = date('Y-m-d H:i:s');
                    } else {
                        // Fallback API if not provided or if profile changed (prevent stale json propagation)
                        try {
                            $configRepo = new \App\Modules\EmpresaConfig\EmpresaConfigRepository();
                            $config = $configRepo->findByEmpresaId($usuario->empresa_id);
                            if ($config && trim((string)($config->tango_connect_token ?? '')) !== '') {
                                $snapshotService = new \App\Modules\Tango\Services\TangoProfileSnapshotService();
                                $perfilData = $snapshotService->fetch($config, $usuario->tango_perfil_pedido_id);
                                if ($perfilData) {
                                    $usuario->tango_perfil_snapshot_json = json_encode($perfilData, JSON_UNESCAPED_UNICODE);
                                    $usuario->tango_perfil_snapshot_date = date('Y-m-d H:i:s');
                                }
                            }
                        } catch (\Throwable $e) {}
                    }
                }
            }
        }

        if (!empty($data['password'])) {
            $newPassword = (string) $data['password'];

            // Guard defensivo: si lo que llegó YA es un hash bcrypt
            // ($2y$..., $2a$..., $2b$...), NO rehashear — es casi seguro un
            // autofill del browser/password-manager que pisó el campo con el
            // hash viejo desde el HTML, o con una password ya hasheada.
            // Rehashearlo resulta en un hash de un hash que no matchea ni la
            // vieja ni la nueva → el usuario queda fuera del sistema.
            // El caller debería avisar al operador, pero como mínimo no
            // destruimos la credencial.
            $looksLikeBcrypt = (bool) preg_match('/^\$2[aby]\$\d{2}\$/', $newPassword) && strlen($newPassword) >= 60;

            if (!$looksLikeBcrypt) {
                $usuario->password_hash = password_hash($newPassword, PASSWORD_DEFAULT);
                error_log('[UsuarioService::update] Password actualizado para usuario #' . (int) $id . ' (largo: ' . strlen($newPassword) . ' chars).');

                // Si un superadmin (rxn admin) cambia la password de un usuario,
                // auto-marcamos el email como verificado y limpiamos tokens.
                // Razonamiento: si el rey está cambiando la password manualmente,
                // significa que YA validó al usuario fuera del sistema (le pasó
                // la nueva por otro canal). Pedirle que además entre al mail a
                // verificar es ruido innecesario — Charly lo llamó "trambóliko".
                if (\App\Modules\Auth\AuthService::isRxnAdmin()) {
                    $usuario->email_verificado = 1;
                    $usuario->verification_token = null;
                    $usuario->verification_expires = null;
                    error_log('[UsuarioService::update] Usuario #' . (int) $id . ' marcado como email_verificado=1 (cambio de password por superadmin).');
                }
            } else {
                error_log('[UsuarioService::update] Password descartada para usuario #' . (int) $id . ' — el valor recibido parece un hash bcrypt (probable autofill del browser). password_hash NO modificado.');
            }
        }

        $this->repository->save($usuario);

        // Sincronizar nombre y extensión en la sesión actual si se editó el propio perfil
        if ((int)$id === (int)($_SESSION['user_id'] ?? 0)) {
            $_SESSION['user_name'] = $usuario->nombre;
            $_SESSION['anura_interno'] = $usuario->anura_interno;
        }

        // Espejo a empresa_config: el perfil Tango debe vivir a nivel empresa,
        // no atado a un usuario específico. El resolver lo lee desde config primero.
        if (isset($data['tango_perfil_pedido']) && $usuario->tango_perfil_pedido_id !== null) {
            try {
                $configRepo = new \App\Modules\EmpresaConfig\EmpresaConfigRepository();
                $config = $configRepo->findByEmpresaId($usuario->empresa_id);
                if ($config) {
                    $config->tango_perfil_pedido_id     = $usuario->tango_perfil_pedido_id;
                    $config->tango_perfil_pedido_codigo = $usuario->tango_perfil_pedido_codigo;
                    $config->tango_perfil_pedido_nombre = $usuario->tango_perfil_pedido_nombre;
                    if ($usuario->tango_perfil_snapshot_json !== null) {
                        $config->tango_perfil_snapshot_json = $usuario->tango_perfil_snapshot_json;
                        $config->tango_perfil_snapshot_date = $usuario->tango_perfil_snapshot_date;
                    }
                    $configRepo->save($config);
                }
            } catch (\Throwable $e) {
                // No bloquear el guardado del usuario si falla el mirror de config
            }
        }
    }

    private function validateEmail(string $email, ?int $excludeId): void
    {
        if (empty($email)) {
            throw new RuntimeException('El correo electrónico es obligatorio.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('El formato del correo electrónico no es válido.');
        }

        // En RXN Fase 1 definimos email único GLOBAlmente por estar la key UNIQUE integrada en el esquema de base de datos MariaDB base.
        $existente = $this->repository->findByEmail($email, $excludeId);
        if ($existente) {
            throw new RuntimeException('El correo electrónico ya se encuentra registrado (Bloqueo Global).');
        }
    }

    private function validateAnuraInterno(string $anuraInterno, int $empresaId, ?int $excludeId): void
    {
        $existente = $this->repository->findByAnuraInterno($anuraInterno, $empresaId, $excludeId);
        if ($existente) {
            throw new RuntimeException("El interno Anura '{$anuraInterno}' ya se encuentra mapeado al usuario '{$existente->nombre}' en esta sesión de empresa.");
        }
    }

    public function copy(int $id): void
    {
        $original = $this->getByIdForContext($id);
        
        $usuario = new Usuario();
        $usuario->empresa_id = $original->empresa_id;
        $usuario->nombre = $original->nombre . ' (Copia)';
        
        // Use a generic placeholder email since unique constraint protects DB
        $usuario->email = 'copia_' . uniqid() . '_' . $original->email;
        $usuario->password_hash = $original->password_hash; // Keep same password
        
        $usuario->activo = 0; // Copies are inactive by default
        $usuario->es_admin = 0; // Strip admin privileges
        
        $usuario->tango_perfil_pedido_id = $original->tango_perfil_pedido_id;
        $usuario->tango_perfil_pedido_codigo = $original->tango_perfil_pedido_codigo;
        $usuario->tango_perfil_pedido_nombre = $original->tango_perfil_pedido_nombre;
        $usuario->tango_perfil_snapshot_json = $original->tango_perfil_snapshot_json;
        $usuario->tango_perfil_snapshot_date = $original->tango_perfil_snapshot_date;

        $usuario->email_verificado = 0;
        $usuario->verification_token = bin2hex(random_bytes(16));
        $usuario->verification_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $this->repository->save($usuario);
    }

    public function delete(int $id): void
    {
        $currentUserId = $_SESSION['user_id'] ?? null;
        if ($id === (int)$currentUserId) {
            throw new RuntimeException('No te podes auto-eliminar de la sesión activa.');
        }

        $this->verifyOwnershipIncludingDeleted($id);
        $this->repository->deleteById($id);
    }

    public function bulkDelete(array $ids): int
    {
        $count = 0;
        $currentUserId = $_SESSION['user_id'] ?? null;
        $isGlobalAdmin = (!empty($_SESSION['es_rxn_admin']) && $_SESSION['es_rxn_admin'] == 1);
        $empresaId = $this->getContextId();

        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id <= 0) continue;
            
            // Protect current user from suicide
            if ($id === (int)$currentUserId) continue;

            try {
                if (!$isGlobalAdmin) {
                    $target = $this->repository->findById($id);
                    if (!$target || $target->empresa_id !== $empresaId) continue;
                    
                    // Don't let normal admins delete global admins
                    if ($target->es_admin == 1 && $target->empresa_id == 1) continue; 
                }

                $this->repository->deleteById($id);
                $count++;
            } catch (\Exception $e) {
                // Ignore failure on single item
            }
        }
        return $count;
    }

    public function restore(int $id): void
    {
        $this->verifyOwnershipIncludingDeleted($id);
        $this->repository->restoreById($id);
    }

    public function bulkRestore(array $ids): int
    {
        $count = 0;
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id <= 0) continue;
            try {
                $this->restore($id);
                $count++;
            } catch (\Exception $e) {}
        }
        return $count;
    }

    public function forceDelete(int $id): void
    {
        $currentUserId = $_SESSION['user_id'] ?? null;
        if ($id === (int)$currentUserId) {
            throw new RuntimeException('No te podes eliminar a vos mismo definitivamente.');
        }

        $this->verifyOwnershipIncludingDeleted($id);
        $this->repository->forceDeleteById($id);
    }

    public function bulkForceDelete(array $ids): int
    {
        $count = 0;
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id <= 0) continue;
            try {
                $this->forceDelete($id);
                $count++;
            } catch (\Exception $e) {}
        }
        return $count;
    }

    private function verifyOwnershipIncludingDeleted(int $id): void
    {
        $isGlobalAdmin = (!empty($_SESSION['es_rxn_admin']) && $_SESSION['es_rxn_admin'] == 1);
        if ($isGlobalAdmin) {
            return;
        }

        $empresaId = $this->getContextId();
        // Fallback simple PDO call to check ownership without touching repository standard methods
        $db = \App\Core\Database::getConnection();
        $stmt = $db->prepare("SELECT empresa_id, es_admin FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row || (int)$row['empresa_id'] !== $empresaId) {
            throw new RuntimeException('Rechazado: El usuario no existe o pertenece a otra empresa.');
        }

        if ($row['es_admin'] == 1 && $row['empresa_id'] == 1) {
             throw new RuntimeException('Rechazado: Un operador no puede modificar un Root Global Admin.');
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
