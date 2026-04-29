<?php

declare(strict_types=1);

namespace App\Modules\Drafts;

use App\Core\Context;
use App\Core\Controller;
use App\Core\View;
use App\Modules\Auth\AuthService;

class DraftsController extends Controller
{
    private const ALLOWED_MODULOS = ['pds', 'presupuesto'];
    private const MAX_PAYLOAD_BYTES = 1048576; // 1 MB

    private DraftsRepository $repo;

    public function __construct()
    {
        $this->repo = new DraftsRepository();
    }

    private function ctx(): array
    {
        AuthService::requireLogin();
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $empresaId = (int) Context::getEmpresaId();
        if ($userId <= 0 || $empresaId <= 0) {
            $this->json(['ok' => false, 'error' => 'sin_contexto'], 401);
        }
        return [$userId, $empresaId];
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function parseModuloRef(): array
    {
        $modulo = trim((string) ($_REQUEST['modulo'] ?? ''));
        $ref = trim((string) ($_REQUEST['ref'] ?? 'new'));
        if (!in_array($modulo, self::ALLOWED_MODULOS, true)) {
            $this->json(['ok' => false, 'error' => 'modulo_invalido'], 400);
        }
        if ($ref === '' || strlen($ref) > 64 || !preg_match('/^[A-Za-z0-9_\-]+$/', $ref)) {
            $this->json(['ok' => false, 'error' => 'ref_invalida'], 400);
        }
        return [$modulo, $ref];
    }

    public function get(): void
    {
        [$userId, $empresaId] = $this->ctx();
        [$modulo, $ref] = $this->parseModuloRef();

        $row = $this->repo->find($userId, $empresaId, $modulo, $ref);
        if ($row === null) {
            $this->json(['ok' => true, 'draft' => null]);
        }

        $payload = json_decode((string) $row['payload_json'], true);
        $this->json([
            'ok' => true,
            'draft' => [
                'modulo' => $row['modulo'],
                'ref' => $row['ref_key'],
                'payload' => is_array($payload) ? $payload : null,
                'updated_at' => $row['updated_at'],
            ],
        ]);
    }

    public function save(): void
    {
        $this->verifyCsrfOrAbort();
        [$userId, $empresaId] = $this->ctx();
        [$modulo, $ref] = $this->parseModuloRef();

        $payloadRaw = (string) ($_POST['payload'] ?? '');
        if ($payloadRaw === '' || strlen($payloadRaw) > self::MAX_PAYLOAD_BYTES) {
            $this->json(['ok' => false, 'error' => 'payload_invalido'], 400);
        }
        $decoded = json_decode($payloadRaw, true);
        if (!is_array($decoded)) {
            $this->json(['ok' => false, 'error' => 'payload_no_json'], 400);
        }

        $this->repo->upsert($userId, $empresaId, $modulo, $ref, $payloadRaw);
        $this->json(['ok' => true, 'saved_at' => date('Y-m-d H:i:s')]);
    }

    public function discard(): void
    {
        $this->verifyCsrfOrAbort();
        [$userId, $empresaId] = $this->ctx();
        [$modulo, $ref] = $this->parseModuloRef();

        $this->repo->delete($userId, $empresaId, $modulo, $ref);
        $this->json(['ok' => true]);
    }

    /**
     * GET /mi-perfil/borradores
     * Panel "Mis borradores": vista HTML con todos los drafts del usuario.
     */
    public function index(): void
    {
        AuthService::requireLogin();
        [$userId, $empresaId] = $this->ctx();

        $rows = $this->repo->findAllByUser($userId, $empresaId);
        $drafts = array_map([$this, 'decorateDraftRow'], $rows);

        View::render('app/modules/Drafts/views/index.php', [
            'drafts' => $drafts,
        ]);
    }

    /**
     * Enriquece una fila plana de DB con los campos que la vista necesita:
     * label legible del módulo, URL para retomar el borrador, y URL del listado
     * del módulo (fallback). NO carga el payload — eso lo hace el form al abrirse.
     */
    private function decorateDraftRow(array $row): array
    {
        $modulo = (string) ($row['modulo'] ?? '');
        $ref = (string) ($row['ref_key'] ?? 'new');

        return [
            'modulo' => $modulo,
            'ref_key' => $ref,
            'modulo_label' => self::moduloLabel($modulo),
            'modulo_icon' => self::moduloIcon($modulo),
            'resume_url' => self::resumeUrl($modulo, $ref),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'payload_bytes' => (int) ($row['payload_bytes'] ?? 0),
        ];
    }

    public static function moduloLabel(string $modulo): string
    {
        return match ($modulo) {
            'pds' => 'Pedido de Servicio',
            'presupuesto' => 'Presupuesto',
            default => ucfirst($modulo),
        };
    }

    public static function moduloIcon(string $modulo): string
    {
        return match ($modulo) {
            'pds' => 'bi-tools',
            'presupuesto' => 'bi-file-earmark-text',
            default => 'bi-arrow-counterclockwise',
        };
    }

    /**
     * Resuelve a qué URL navegar para retomar un borrador. La regla es ir al
     * form correspondiente — el JS rxn-draft-autosave detecta el draft existente
     * y muestra el banner "Retomar/Descartar" automáticamente.
     */
    public static function resumeUrl(string $modulo, string $ref): string
    {
        switch ($modulo) {
            case 'pds':
                return $ref === 'new'
                    ? '/mi-empresa/crm/pedidos-servicio/nuevo'
                    : '/mi-empresa/crm/pedidos-servicio/' . rawurlencode($ref) . '/editar';
            case 'presupuesto':
                return $ref === 'new'
                    ? '/mi-empresa/crm/presupuestos/nuevo'
                    : '/mi-empresa/crm/presupuestos/' . rawurlencode($ref) . '/editar';
            default:
                return '/';
        }
    }
}
