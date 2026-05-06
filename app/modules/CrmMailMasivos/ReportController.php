<?php

declare(strict_types=1);

namespace App\Modules\CrmMailMasivos;

use App\Core\Context;
use App\Core\Database;
use App\Core\Flash;
use App\Core\View;
use App\Modules\Auth\AuthService;
use App\Modules\Auth\UserModuleAccessService;
use App\Modules\Empresas\EmpresaAccessService;
use App\Modules\CrmMailMasivos\Services\BlockRenderer;
use App\Modules\CrmMailMasivos\Services\FilterTokenResolver;
use App\Modules\CrmMailMasivos\Services\ReportMetamodel;
use App\Modules\CrmMailMasivos\Services\ReportQueryBuilder;
use InvalidArgumentException;
use PDO;
use Throwable;

/**
 * CRUD de Reportes del módulo CrmMailMasivos + endpoints auxiliares
 * (metamodelo JSON y preview AJAX).
 *
 * Seguridad:
 * - AuthService::requireLogin() en todos los métodos.
 * - Context::getEmpresaId() obligatorio para todas las queries.
 * - El Query Builder valida contra el metamodelo antes de ejecutar nada.
 */
class ReportController
{
    private ReportRepository $repo;
    private ReportMetamodel $meta;

    public function __construct()
    {
        $this->repo = new ReportRepository();
        $this->meta = new ReportMetamodel();
    }

    // ──────────────────────────────────────────────
    // LISTADO
    // ──────────────────────────────────────────────

    public function index(): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmMailMasivosAccess();
        UserModuleAccessService::requireUserAccess('mail_masivos', 'Mail Masivos');
        $empresaId = (int) Context::getEmpresaId();

        $search = trim((string) ($_GET['search'] ?? ''));
        $reports = $this->repo->findAllByEmpresa($empresaId, $search);

        View::render('app/modules/CrmMailMasivos/views/reportes/index.php', [
            'reports' => $reports,
            'search' => $search,
        ]);
    }

    // ──────────────────────────────────────────────
    // CREAR / EDITAR
    // ──────────────────────────────────────────────

    public function create(): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmMailMasivosAccess();
        UserModuleAccessService::requireUserAccess('mail_masivos', 'Mail Masivos');

        View::render('app/modules/CrmMailMasivos/views/reportes/designer.php', [
            'mode' => 'create',
            'formAction' => '/mi-empresa/crm/mail-masivos/reportes',
            'report' => [
                'id' => null,
                'nombre' => '',
                'descripcion' => '',
                'root_entity' => '',
                'config_json' => json_encode(['root_entity' => '', 'relations' => [], 'fields' => [], 'filters' => []], JSON_PRETTY_PRINT),
            ],
            'entities' => $this->meta->toArrayForFrontend(),
            'errors' => [],
        ]);
    }

    public function edit(string $id): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmMailMasivosAccess();
        UserModuleAccessService::requireUserAccess('mail_masivos', 'Mail Masivos');
        $empresaId = (int) Context::getEmpresaId();
        $id = (int) $id;

        $report = $this->repo->findByIdForEmpresa($id, $empresaId);
        if (!$report) {
            http_response_code(404);
            echo 'Reporte no encontrado.';
            return;
        }

        View::render('app/modules/CrmMailMasivos/views/reportes/designer.php', [
            'mode' => 'edit',
            'formAction' => '/mi-empresa/crm/mail-masivos/reportes/' . $report['id'],
            'report' => $report,
            'entities' => $this->meta->toArrayForFrontend(),
            'errors' => [],
        ]);
    }

    // ──────────────────────────────────────────────
    // PERSISTIR
    // ──────────────────────────────────────────────

    public function store(): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmMailMasivosAccess();
        UserModuleAccessService::requireUserAccess('mail_masivos', 'Mail Masivos');
        $empresaId = (int) Context::getEmpresaId();

        $payload = $this->readFormPayload();
        $errors = $this->validatePayload($payload, $empresaId, null);

        if (!empty($errors)) {
            View::render('app/modules/CrmMailMasivos/views/reportes/designer.php', [
                'mode' => 'create',
                'formAction' => '/mi-empresa/crm/mail-masivos/reportes',
                'report' => $payload,
                'entities' => $this->meta->toArrayForFrontend(),
                'errors' => $errors,
            ]);
            return;
        }

        $id = $this->repo->create([
            'empresa_id' => $empresaId,
            'nombre' => $payload['nombre'],
            'descripcion' => $payload['descripcion'],
            'root_entity' => $payload['root_entity'],
            'config_json' => $payload['config_json'],
            'created_by' => $_SESSION['user_id'] ?? null,
        ]);

        Flash::set('success', 'Reporte creado correctamente.');
        header('Location: /mi-empresa/crm/mail-masivos/reportes/' . $id . '/editar');
        exit;
    }

    public function update(string $id): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmMailMasivosAccess();
        UserModuleAccessService::requireUserAccess('mail_masivos', 'Mail Masivos');
        $empresaId = (int) Context::getEmpresaId();
        $id = (int) $id;

        $existing = $this->repo->findByIdForEmpresa($id, $empresaId);
        if (!$existing) {
            http_response_code(404);
            echo 'Reporte no encontrado.';
            return;
        }

        $payload = $this->readFormPayload();
        $payload['id'] = $id;
        $errors = $this->validatePayload($payload, $empresaId, $id);

        if (!empty($errors)) {
            View::render('app/modules/CrmMailMasivos/views/reportes/designer.php', [
                'mode' => 'edit',
                'formAction' => '/mi-empresa/crm/mail-masivos/reportes/' . $id,
                'report' => array_merge($existing, $payload),
                'entities' => $this->meta->toArrayForFrontend(),
                'errors' => $errors,
            ]);
            return;
        }

        $this->repo->update($id, $empresaId, [
            'nombre' => $payload['nombre'],
            'descripcion' => $payload['descripcion'],
            'root_entity' => $payload['root_entity'],
            'config_json' => $payload['config_json'],
        ]);

        Flash::set('success', 'Reporte actualizado.');
        header('Location: /mi-empresa/crm/mail-masivos/reportes/' . $id . '/editar');
        exit;
    }

    public function delete(string $id): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmMailMasivosAccess();
        UserModuleAccessService::requireUserAccess('mail_masivos', 'Mail Masivos');
        $empresaId = (int) Context::getEmpresaId();
        $id = (int) $id;

        $ok = $this->repo->softDelete($id, $empresaId);
        Flash::set($ok ? 'success' : 'danger', $ok ? 'Reporte eliminado.' : 'No se pudo eliminar el reporte.');

        header('Location: /mi-empresa/crm/mail-masivos/reportes');
        exit;
    }

    // ──────────────────────────────────────────────
    // ENDPOINTS AUXILIARES (AJAX / JSON)
    // ──────────────────────────────────────────────

    /**
     * GET JSON con el metamodelo completo. Lo consume el diseñador visual
     * (Fase 2b) para poblar la UI de entidades/campos/relaciones.
     */
    public function metamodel(): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmMailMasivosAccess();
        UserModuleAccessService::requireUserAccess('mail_masivos', 'Mail Masivos');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'entities' => $this->meta->toArrayForFrontend(),
            'operators_by_type' => ReportMetamodel::operatorsByType(),
            'dynamic_tokens' => FilterTokenResolver::availableTokens(),
        ]);
        exit;
    }

    /**
     * POST JSON con una config de reporte (igual formato que config_json) y
     * devuelve los primeros 10 registros que matchean, junto con el SQL
     * generado (para debug) y la lista de mails destinatarios.
     */
    public function preview(): void
    {
        AuthService::requireLogin();
        EmpresaAccessService::requireCrmMailMasivosAccess();
        UserModuleAccessService::requireUserAccess('mail_masivos', 'Mail Masivos');
        header('Content-Type: application/json; charset=utf-8');

        $empresaId = (int) Context::getEmpresaId();
        if ($empresaId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Empresa no resuelta']);
            exit;
        }

        $raw = file_get_contents('php://input');
        $config = $raw ? json_decode($raw, true) : null;

        if (!is_array($config)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'JSON inválido']);
            exit;
        }

        // Paginación server-side. Sin tope total: el módulo cuida la presión
        // de DB con LIMIT por página, y el preview no se persiste en ningún lado.
        $page = max(1, (int) ($config['_page'] ?? 1));
        $perPage = (int) ($config['_per_page'] ?? 25);
        if ($perPage < 5)   $perPage = 5;
        if ($perPage > 200) $perPage = 200;
        $offset = ($page - 1) * $perPage;

        try {
            // Los reportes de contenido (root = entidad broadcast) NO requieren
            // mail_field: su preview es simplemente la tabla de filas. Los de
            // destinatarios sí — ahí mostramos conteo de mails únicos.
            $rootEntity = (string) ($config['root_entity'] ?? '');
            $isContent = BlockRenderer::isContentEntity($rootEntity);

            $pdo = Database::getConnection();

            // 1. Total de filas (mismo plan, sin LIMIT). Usa el mismo builder
            //    para que joins/where/empresa_scope/soft_delete queden iguales.
            $counter = new ReportQueryBuilder($this->meta);
            $countQuery = $counter->buildCount($config, $empresaId, !$isContent);
            $stmtCount = $pdo->prepare($countQuery['sql']);
            foreach ($countQuery['params'] as $name => $value) {
                $stmtCount->bindValue($name, $value, $this->pdoType($value));
            }
            $stmtCount->execute();
            $total = (int) ($stmtCount->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

            // 2. Página solicitada
            $builder = new ReportQueryBuilder($this->meta);
            $built = $builder->build($config, $empresaId, $perPage, !$isContent, $offset);

            $stmt = $pdo->prepare($built['sql']);
            foreach ($built['params'] as $name => $value) {
                $stmt->bindValue($name, $value, $this->pdoType($value));
            }
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // 3. Total de mails únicos. Construimos COUNT(DISTINCT alias) sobre
            //    el SELECT completo como subquery para reusar joins+where sin
            //    tocar la API pública del builder.
            $mailCount = 0;
            $mailsThisPage = [];
            if ($built['mail_target'] !== null) {
                $mailAlias = $built['mail_target']['alias'];

                $fullBuilder = new ReportQueryBuilder($this->meta);
                $fullBuilt = $fullBuilder->build($config, $empresaId, 0, !$isContent);
                $mcSql = 'SELECT COUNT(DISTINCT `' . $mailAlias . '`) AS total FROM (' . $fullBuilt['sql'] . ') AS sub';
                $stmtMc = $pdo->prepare($mcSql);
                foreach ($fullBuilt['params'] as $name => $value) {
                    $stmtMc->bindValue($name, $value, $this->pdoType($value));
                }
                $stmtMc->execute();
                $mailCount = (int) ($stmtMc->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

                foreach ($rows as $r) {
                    if (!empty($r[$mailAlias]) && filter_var($r[$mailAlias], FILTER_VALIDATE_EMAIL)) {
                        $mailsThisPage[] = $r[$mailAlias];
                    }
                }
            }

            $totalPages = $perPage > 0 ? max(1, (int) ceil($total / $perPage)) : 1;

            echo json_encode([
                'success' => true,
                'rows' => $rows,
                'row_count' => count($rows),
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages,
                'mail_count' => $mailCount,
                'mails' => array_values(array_unique($mailsThisPage)),
                'mail_target' => $built['mail_target'],
                'is_content_report' => $isContent,
                'sql_debug' => $built['sql'],
                'params_debug' => $built['params'],
            ]);
            exit;
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'kind' => 'validation',
            ]);
            exit;
        } catch (Throwable $e) {
            http_response_code(500);
            error_log('ReportController::preview error: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Error interno al generar el preview: ' . $e->getMessage(),
                'kind' => 'server',
            ]);
            exit;
        }
    }

    // ──────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ──────────────────────────────────────────────

    private function pdoType($value): int
    {
        if (is_int($value))   return PDO::PARAM_INT;
        if (is_bool($value))  return PDO::PARAM_BOOL;
        if ($value === null)  return PDO::PARAM_NULL;
        return PDO::PARAM_STR;
    }

    /**
     * @return array<string, mixed>
     */
    private function readFormPayload(): array
    {
        return [
            'nombre' => trim((string) ($_POST['nombre'] ?? '')),
            'descripcion' => trim((string) ($_POST['descripcion'] ?? '')) ?: null,
            'root_entity' => trim((string) ($_POST['root_entity'] ?? '')),
            'config_json' => trim((string) ($_POST['config_json'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    private function validatePayload(array $payload, int $empresaId, ?int $excludeId): array
    {
        $errors = [];

        if (empty($payload['nombre'])) {
            $errors['nombre'] = 'El nombre es obligatorio.';
        } elseif ($this->repo->nameExists($empresaId, (string) $payload['nombre'], $excludeId)) {
            $errors['nombre'] = 'Ya existe un reporte con ese nombre.';
        }

        if (empty($payload['root_entity'])) {
            $errors['root_entity'] = 'Elegí una entidad raíz.';
        } elseif (!$this->meta->hasEntity((string) $payload['root_entity'])) {
            $errors['root_entity'] = 'Entidad raíz desconocida.';
        }

        // Validar config_json parseable y coherente
        $config = null;
        if (!empty($payload['config_json'])) {
            $config = json_decode((string) $payload['config_json'], true);
            if (!is_array($config)) {
                $errors['config_json'] = 'JSON inválido.';
            }
        }

        if (is_array($config) && empty($errors['root_entity'])) {
            // Intentar construir (dry-run) para validar todo el diseño.
            // Los reportes de contenido no requieren mail_field — se lo decimos
            // al builder para que no tire la excepción "destinatario no resuelto".
            $rootEntity = (string) ($payload['root_entity'] ?? '');
            $requireMail = !BlockRenderer::isContentEntity($rootEntity);
            try {
                $builder = new ReportQueryBuilder($this->meta);
                $builder->build($config, $empresaId, 1, $requireMail);
            } catch (InvalidArgumentException $e) {
                $errors['config_json'] = 'Diseño inválido: ' . $e->getMessage();
            } catch (Throwable $e) {
                $errors['config_json'] = 'Error validando el diseño: ' . $e->getMessage();
            }
        }

        return $errors;
    }
}
