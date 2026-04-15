<?php

declare(strict_types=1);

namespace App\Modules\CrmMailMasivos;

use App\Core\Context;
use App\Core\Database;
use App\Core\Flash;
use App\Core\View;
use App\Modules\Auth\AuthService;
use App\Modules\CrmMailMasivos\Services\ReportMetamodel;
use App\Modules\CrmMailMasivos\Services\ReportQueryBuilder;
use InvalidArgumentException;
use PDO;
use Throwable;

/**
 * CRUD de Plantillas HTML del módulo CrmMailMasivos (Fase 3).
 *
 * El usuario arma el body HTML con placeholders `{{Entity.field}}` que se
 * reemplazan al momento del envío real (Fase 4, en n8n) con los datos de
 * cada destinatario. En el editor el preview se hace con el primer registro
 * que devuelve el reporte asociado, reutilizando el mismo motor de query
 * validado contra el metamodelo.
 *
 * Seguridad:
 * - AuthService::requireLogin() en todos los métodos.
 * - Context::getEmpresaId() obligatorio para todas las queries.
 * - El reporte asociado a la plantilla debe pertenecer a la misma empresa.
 * - El preview del iframe usa sandbox="" (sin permisos) en el frontend.
 */
class TemplateController
{
    private TemplateRepository $repo;
    private ReportMetamodel $meta;

    public function __construct()
    {
        $this->repo = new TemplateRepository();
        $this->meta = new ReportMetamodel();
    }

    // ──────────────────────────────────────────────
    // LISTADO
    // ──────────────────────────────────────────────

    public function index(): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();

        $search = trim((string) ($_GET['search'] ?? ''));
        $templates = $this->repo->findAllByEmpresa($empresaId, $search);

        View::render('app/modules/CrmMailMasivos/views/plantillas/index.php', [
            'templates' => $templates,
            'search' => $search,
        ]);
    }

    // ──────────────────────────────────────────────
    // CREAR / EDITAR
    // ──────────────────────────────────────────────

    public function create(): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();

        View::render('app/modules/CrmMailMasivos/views/plantillas/editor.php', [
            'mode' => 'create',
            'formAction' => '/mi-empresa/crm/mail-masivos/plantillas',
            'template' => [
                'id' => null,
                'nombre' => '',
                'descripcion' => '',
                'report_id' => null,
                'asunto' => '',
                'body_html' => $this->defaultBodyHtml(),
                'available_vars_json' => null,
            ],
            'reports' => $this->repo->listAvailableReports($empresaId),
            'errors' => [],
        ]);
    }

    public function edit(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $id = (int) $id;

        $template = $this->repo->findByIdForEmpresa($id, $empresaId);
        if (!$template) {
            http_response_code(404);
            echo 'Plantilla no encontrada.';
            return;
        }

        View::render('app/modules/CrmMailMasivos/views/plantillas/editor.php', [
            'mode' => 'edit',
            'formAction' => '/mi-empresa/crm/mail-masivos/plantillas/' . $template['id'],
            'template' => $template,
            'reports' => $this->repo->listAvailableReports($empresaId),
            'errors' => [],
        ]);
    }

    // ──────────────────────────────────────────────
    // PERSISTIR
    // ──────────────────────────────────────────────

    public function store(): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();

        $payload = $this->readFormPayload();
        $errors = $this->validatePayload($payload, $empresaId, null);

        if (!empty($errors)) {
            View::render('app/modules/CrmMailMasivos/views/plantillas/editor.php', [
                'mode' => 'create',
                'formAction' => '/mi-empresa/crm/mail-masivos/plantillas',
                'template' => $payload,
                'reports' => $this->repo->listAvailableReports($empresaId),
                'errors' => $errors,
            ]);
            return;
        }

        $id = $this->repo->create([
            'empresa_id' => $empresaId,
            'nombre' => $payload['nombre'],
            'descripcion' => $payload['descripcion'],
            'report_id' => $payload['report_id'],
            'asunto' => $payload['asunto'],
            'body_html' => $payload['body_html'],
            'available_vars_json' => $payload['available_vars_json'],
            'created_by' => $_SESSION['user_id'] ?? null,
        ]);

        Flash::set('success', 'Plantilla creada correctamente.');
        header('Location: /mi-empresa/crm/mail-masivos/plantillas/' . $id . '/editar');
        exit;
    }

    public function update(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $id = (int) $id;

        $existing = $this->repo->findByIdForEmpresa($id, $empresaId);
        if (!$existing) {
            http_response_code(404);
            echo 'Plantilla no encontrada.';
            return;
        }

        $payload = $this->readFormPayload();
        $payload['id'] = $id;
        $errors = $this->validatePayload($payload, $empresaId, $id);

        if (!empty($errors)) {
            View::render('app/modules/CrmMailMasivos/views/plantillas/editor.php', [
                'mode' => 'edit',
                'formAction' => '/mi-empresa/crm/mail-masivos/plantillas/' . $id,
                'template' => array_merge($existing, $payload),
                'reports' => $this->repo->listAvailableReports($empresaId),
                'errors' => $errors,
            ]);
            return;
        }

        $this->repo->update($id, $empresaId, [
            'nombre' => $payload['nombre'],
            'descripcion' => $payload['descripcion'],
            'report_id' => $payload['report_id'],
            'asunto' => $payload['asunto'],
            'body_html' => $payload['body_html'],
            'available_vars_json' => $payload['available_vars_json'],
        ]);

        Flash::set('success', 'Plantilla actualizada.');
        header('Location: /mi-empresa/crm/mail-masivos/plantillas/' . $id . '/editar');
        exit;
    }

    public function delete(string $id): void
    {
        AuthService::requireLogin();
        $empresaId = (int) Context::getEmpresaId();
        $id = (int) $id;

        $ok = $this->repo->softDelete($id, $empresaId);
        Flash::set($ok ? 'success' : 'danger', $ok ? 'Plantilla eliminada.' : 'No se pudo eliminar la plantilla.');

        header('Location: /mi-empresa/crm/mail-masivos/plantillas');
        exit;
    }

    // ──────────────────────────────────────────────
    // ENDPOINTS AUXILIARES (AJAX / JSON)
    // ──────────────────────────────────────────────

    /**
     * GET JSON — devuelve las variables disponibles de un reporte específico,
     * extraídas del `fields` de su `config_json`. Formato:
     *
     * {
     *   "success": true,
     *   "report_id": 17,
     *   "report_nombre": "Clientes activos CABA",
     *   "variables": [
     *     {
     *       "token": "CrmClientes.razon_social",
     *       "column_alias": "CrmClientes_razon_social",
     *       "entity_label": "Clientes",
     *       "field_label": "Razón Social",
     *       "type": "string"
     *     },
     *     ...
     *   ]
     * }
     */
    public function availableVars(string $reportId): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json; charset=utf-8');

        $empresaId = (int) Context::getEmpresaId();
        $reportId = (int) $reportId;

        $report = $this->repo->findReportByIdForEmpresa($reportId, $empresaId);
        if (!$report) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Reporte no encontrado']);
            exit;
        }

        $config = json_decode((string) $report['config_json'], true);
        $fields = is_array($config['fields'] ?? null) ? $config['fields'] : [];

        $variables = [];
        foreach ($fields as $f) {
            if (!is_array($f)) continue;
            $entity = (string) ($f['entity'] ?? '');
            $field = (string) ($f['field'] ?? '');
            if ($entity === '' || $field === '') continue;
            if (!$this->meta->hasField($entity, $field)) continue;

            $entityDef = $this->meta->getEntity($entity);
            $fieldDef = $this->meta->getField($entity, $field);

            $variables[] = [
                'token' => $entity . '.' . $field,
                'column_alias' => $entity . '_' . $field,
                'entity_label' => (string) ($entityDef['label'] ?? $entity),
                'field_label' => (string) ($fieldDef['label'] ?? $field),
                'type' => (string) ($fieldDef['type'] ?? 'string'),
            ];
        }

        echo json_encode([
            'success' => true,
            'report_id' => (int) $report['id'],
            'report_nombre' => (string) $report['nombre'],
            'variables' => $variables,
        ]);
        exit;
    }

    /**
     * POST JSON — renderiza la plantilla con el PRIMER row del reporte
     * asociado para preview. Input:
     *
     * {
     *   "report_id": 17,
     *   "asunto": "Hola {{CrmClientes.razon_social}}",
     *   "body_html": "<p>Gracias {{CrmClientes.nombre}}...</p>"
     * }
     *
     * Output:
     *
     * {
     *   "success": true,
     *   "asunto_rendered": "Hola ACME S.A.",
     *   "body_html_rendered": "<p>Gracias Juan...</p>",
     *   "sample_row": { "CrmClientes_razon_social": "ACME S.A.", ... },
     *   "missing_tokens": ["CrmClientes.telefono"]  // si hay placeholders sin valor
     * }
     */
    public function previewRender(): void
    {
        AuthService::requireLogin();
        header('Content-Type: application/json; charset=utf-8');

        $empresaId = (int) Context::getEmpresaId();
        if ($empresaId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Empresa no resuelta']);
            exit;
        }

        $raw = file_get_contents('php://input');
        $input = $raw ? json_decode($raw, true) : null;

        if (!is_array($input)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'JSON inválido']);
            exit;
        }

        $reportId = (int) ($input['report_id'] ?? 0);
        $asunto = (string) ($input['asunto'] ?? '');
        $bodyHtml = (string) ($input['body_html'] ?? '');

        // Sin reporte asociado → preview sin reemplazo (los placeholders quedan visibles)
        if ($reportId <= 0) {
            echo json_encode([
                'success' => true,
                'asunto_rendered' => $asunto,
                'body_html_rendered' => $bodyHtml,
                'sample_row' => null,
                'missing_tokens' => $this->extractTokens($asunto . ' ' . $bodyHtml),
                'note' => 'Sin reporte asociado — los placeholders no se reemplazan en el preview.',
            ]);
            exit;
        }

        $report = $this->repo->findReportByIdForEmpresa($reportId, $empresaId);
        if (!$report) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Reporte no encontrado']);
            exit;
        }

        $config = json_decode((string) $report['config_json'], true);
        if (!is_array($config)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Config del reporte ilegible']);
            exit;
        }

        try {
            $builder = new ReportQueryBuilder($this->meta);
            $built = $builder->build($config, $empresaId, 1);

            $pdo = Database::getConnection();
            $stmt = $pdo->prepare($built['sql']);
            foreach ($built['params'] as $name => $value) {
                $paramType = PDO::PARAM_STR;
                if (is_int($value)) {
                    $paramType = PDO::PARAM_INT;
                } elseif (is_bool($value)) {
                    $paramType = PDO::PARAM_BOOL;
                } elseif ($value === null) {
                    $paramType = PDO::PARAM_NULL;
                }
                $stmt->bindValue($name, $value, $paramType);
            }
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                echo json_encode([
                    'success' => true,
                    'asunto_rendered' => $asunto,
                    'body_html_rendered' => $bodyHtml,
                    'sample_row' => null,
                    'missing_tokens' => $this->extractTokens($asunto . ' ' . $bodyHtml),
                    'note' => 'El reporte no devolvió registros — no hay datos de muestra para el preview.',
                ]);
                exit;
            }

            [$asuntoOut, $missA] = $this->renderTemplate($asunto, $row);
            [$bodyOut, $missB] = $this->renderTemplate($bodyHtml, $row);

            echo json_encode([
                'success' => true,
                'asunto_rendered' => $asuntoOut,
                'body_html_rendered' => $bodyOut,
                'sample_row' => $row,
                'missing_tokens' => array_values(array_unique(array_merge($missA, $missB))),
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
            error_log('TemplateController::previewRender error: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Error interno al renderizar preview: ' . $e->getMessage(),
                'kind' => 'server',
            ]);
            exit;
        }
    }

    // ──────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ──────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function readFormPayload(): array
    {
        $reportId = trim((string) ($_POST['report_id'] ?? ''));
        return [
            'nombre' => trim((string) ($_POST['nombre'] ?? '')),
            'descripcion' => trim((string) ($_POST['descripcion'] ?? '')) ?: null,
            'report_id' => ($reportId === '' ? null : (int) $reportId),
            'asunto' => trim((string) ($_POST['asunto'] ?? '')),
            'body_html' => (string) ($_POST['body_html'] ?? ''),
            'available_vars_json' => trim((string) ($_POST['available_vars_json'] ?? '')) ?: null,
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
            $errors['nombre'] = 'Ya existe una plantilla con ese nombre.';
        }

        if (empty($payload['asunto'])) {
            $errors['asunto'] = 'El asunto es obligatorio.';
        } elseif (mb_strlen((string) $payload['asunto']) > 255) {
            $errors['asunto'] = 'El asunto no puede superar los 255 caracteres.';
        }

        if (empty(trim(strip_tags((string) $payload['body_html'])))) {
            $errors['body_html'] = 'El contenido HTML no puede estar vacío.';
        }

        if (!empty($payload['report_id'])) {
            $report = $this->repo->findReportByIdForEmpresa((int) $payload['report_id'], $empresaId);
            if (!$report) {
                $errors['report_id'] = 'El reporte seleccionado no existe o no pertenece a esta empresa.';
            }
        }

        return $errors;
    }

    /**
     * Extrae todos los tokens `{{Entity.field}}` presentes en un texto.
     * @return list<string>
     */
    private function extractTokens(string $text): array
    {
        $matches = [];
        if (preg_match_all('/\{\{\s*([A-Za-z0-9_]+)\.([A-Za-z0-9_]+)\s*\}\}/', $text, $matches)) {
            $tokens = [];
            for ($i = 0, $n = count($matches[1]); $i < $n; $i++) {
                $tokens[] = $matches[1][$i] . '.' . $matches[2][$i];
            }
            return array_values(array_unique($tokens));
        }
        return [];
    }

    /**
     * Reemplaza `{{Entity.field}}` por `$row['Entity_field']` (el alias de columna
     * que produce el QueryBuilder). Devuelve [string renderizado, tokens faltantes].
     *
     * @param array<string, mixed> $row
     * @return array{0: string, 1: list<string>}
     */
    private function renderTemplate(string $template, array $row): array
    {
        $missing = [];
        $rendered = preg_replace_callback(
            '/\{\{\s*([A-Za-z0-9_]+)\.([A-Za-z0-9_]+)\s*\}\}/',
            function (array $m) use ($row, &$missing): string {
                $entity = $m[1];
                $field = $m[2];
                $alias = $entity . '_' . $field;
                if (!array_key_exists($alias, $row) || $row[$alias] === null) {
                    $missing[] = $entity . '.' . $field;
                    return '';
                }
                return (string) $row[$alias];
            },
            $template
        );
        return [(string) $rendered, array_values(array_unique($missing))];
    }

    private function defaultBodyHtml(): string
    {
        return '<p>Hola,</p>' . "\n"
            . '<p>Escribí acá el contenido de tu mail. Usá los botones de variables '
            . 'del panel lateral para insertar datos dinámicos como '
            . '<code>{{CrmClientes.razon_social}}</code>.</p>' . "\n"
            . '<p>Saludos,<br>El equipo.</p>';
    }
}
