<?php

declare(strict_types=1);

namespace App\Modules\RxnSync;

use App\Core\Controller;
use App\Core\Context;
use App\Core\View;
use App\Modules\EmpresaConfig\EmpresaConfigRepository;
use RuntimeException;

class RxnSyncController extends Controller
{
    private RxnSyncService $service;

    public function __construct()
    {
        $this->service = new RxnSyncService();
    }

    /**
     * Muestra la Consola Centralizada de RXN - Sync
     */
    public function index(): void
    {
        $empresaId = Context::getEmpresaId();
        $area = $this->resolveArea();
        $config = EmpresaConfigRepository::forArea($area)->findByEmpresaId((int) $empresaId);
        $articulosStatus = $this->service->getPivotStatus((int) $empresaId, 'articulo');
        $articulosVinculados = count(array_filter($articulosStatus, static function (array $row): bool {
            return ($row['estado'] ?? '') === 'vinculado' && !empty($row['tango_id']);
        }));

        $listasReady = !empty($config->lista_precio_1) || !empty($config->lista_precio_2);
        $depositoReady = !empty($config->deposito_codigo);

        View::render('app/modules/RxnSync/views/index.php', [
            'empresaId' => $empresaId,
            'syncCircuit' => [
                'articulos_total' => count($articulosStatus),
                'articulos_vinculados' => $articulosVinculados,
                'articulos_ready' => $articulosVinculados > 0,
                'listas_ready' => $listasReady,
                'deposito_ready' => $depositoReady,
                'precios_ready' => $articulosVinculados > 0 && $listasReady,
                'stock_ready' => $articulosVinculados > 0 && $depositoReady,
                'config_path' => $area === 'crm' ? '/mi-empresa/crm/configuracion' : '/mi-empresa/configuracion',
                'sync_articulos_path' => $area === 'crm' ? '/mi-empresa/crm/sync/articulos' : '/mi-empresa/sync/articulos',
                'sync_precios_path' => $area === 'crm' ? '/mi-empresa/crm/sync/precios' : '/mi-empresa/sync/precios',
                'sync_stock_path' => $area === 'crm' ? '/mi-empresa/crm/sync/stock' : '/mi-empresa/sync/stock',
            ],
        ]);
    }

    public function listClientes(): void
    {
        $empresaId = Context::getEmpresaId();
        $advancedFilters = is_array($_GET['f'] ?? null) ? $_GET['f'] : [];
        $registros = $this->service->getPivotStatus($empresaId, 'cliente', $advancedFilters);

        View::render('app/modules/RxnSync/views/tabs/clientes.php', [
            'registros' => $registros
        ], true);
    }

    public function listArticulos(): void
    {
        $empresaId = Context::getEmpresaId();
        $advancedFilters = is_array($_GET['f'] ?? null) ? $_GET['f'] : [];
        $registros = $this->service->getPivotStatus($empresaId, 'articulo', $advancedFilters);

        View::render('app/modules/RxnSync/views/tabs/articulos.php', [
            'registros' => $registros
        ], true);
    }

    /**
     * AJAX: Dispara el Pull completo de artículos desde Tango.
     * Hace Match Suave por código externo y popula rxn_sync_status.
     */
    public function auditarArticulos(): void
    {
        $empresaId = Context::getEmpresaId();
        header('Content-Type: application/json');
        set_time_limit(120);

        try {
            $result = $this->service->auditarArticulos($empresaId);
            echo json_encode([
                'success' => true,
                'message' => "Auditoría completada. Vinculados: {$result['vinculados']} | Pendientes: {$result['pendientes']}",
                'stats'   => $result,
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }


    /**
     * AJAX: Push individual de un registro hacia Tango.
     * Recibe POST['id'] = local ID, POST['entidad'].
     */
    public function pushToTango(): void
    {
        $id        = (int)($_POST['id'] ?? 0);
        $entidad   = $_POST['entidad'] ?? '';
        $empresaId = Context::getEmpresaId();

        header('Content-Type: application/json');

        if (!$id || !in_array($entidad, ['cliente', 'articulo'])) {
            echo json_encode(['success' => false, 'message' => 'Parámetros inválidos.']);
            return;
        }

        try {
            $result = $this->service->pushToTangoByLocalId($empresaId, $id, $entidad);
            echo json_encode([
                'success' => true,
                'message' => 'Push a Tango completado.',
                'payload' => $result,
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Retorna el último snapshot Tango guardado para auditoría.
     * GET ?entidad=articulo|cliente&id={localId}
     */
    public function getPayload(): void
    {
        $id        = (int)($_GET['id'] ?? 0);
        $entidad   = $_GET['entidad'] ?? '';
        $empresaId = Context::getEmpresaId();

        header('Content-Type: application/json');

        if (!$id || !in_array($entidad, ['cliente', 'articulo'])) {
            echo json_encode(['success' => false, 'message' => 'Parámetros inválidos.']);
            return;
        }

        $db   = \App\Core\Database::getConnection();
        $stmt = $db->prepare(
            "SELECT tango_id, estado, direccion_ultima_sync, resultado_ultima_sync,
                    fecha_ultima_sync, mensaje_error
             FROM rxn_sync_status
             WHERE empresa_id = ? AND entidad = ? AND local_id = ?
             LIMIT 1"
        );
        $stmt->execute([$empresaId, $entidad, $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Sin historial de sincronización para este registro.']);
            return;
        }

        $snapshot = null;
        $stmtLog = $db->prepare(
            "SELECT payload_resumen
             FROM rxn_sync_log
             WHERE empresa_id = ? AND entidad = ? AND local_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT 1"
        );
        $stmtLog->execute([$empresaId, $entidad, $id]);
        $logRow = $stmtLog->fetch(\PDO::FETCH_ASSOC);
        if (!empty($logRow['payload_resumen'])) {
            $snapshot = json_decode((string) $logRow['payload_resumen'], true);
        }

        echo json_encode([
            'success'  => true,
            'meta'     => [
                'tango_id'    => $row['tango_id'],
                'estado'      => $row['estado'],
                'direccion'   => $row['direccion_ultima_sync'],
                'resultado'   => $row['resultado_ultima_sync'],
                'fecha'       => $row['fecha_ultima_sync'],
                'error'       => $row['mensaje_error'],
            ],
            'snapshot' => $snapshot,
        ]);
    }

    /**
     * AJAX: Pull individual de un registro desde Tango.
     * Recibe POST['id'] = local ID, POST['entidad'].
     */
    public function pullSingle(): void
    {
        $id      = (int)($_POST['id'] ?? 0);
        $entidad = $_POST['entidad'] ?? '';
        $empresaId = Context::getEmpresaId();

        header('Content-Type: application/json');

        if (!$id || !in_array($entidad, ['cliente', 'articulo'])) {
            echo json_encode(['success' => false, 'message' => 'Parámetros inválidos.']);
            return;
        }

        try {
            $result = $this->service->pullFromTangoByLocalId($empresaId, $id, $entidad);
            echo json_encode([
                'success' => true,
                'message' => 'Pull desde Tango completado. Datos locales actualizados.',
                'payload' => $result,
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }



    public function auditarClientes(): void
    {
        $empresaId = Context::getEmpresaId();
        header('Content-Type: application/json');
        set_time_limit(120);

        try {
            $result = $this->service->auditarClientes($empresaId);
            echo json_encode([
                'success' => true,
                'message' => "Auditoría completada. Vinculados: {$result['vinculados']} | Pendientes: {$result['pendientes']}",
                'stats'   => $result,
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    private function resolveArea(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        return str_contains($uri, '/mi-empresa/crm/') ? 'crm' : 'tiendas';
    }

    /**
     * AJAX: Push masivo de registros seleccionados hacia Tango.
     * Recibe POST['ids'] = JSON array de local IDs, POST['entidad'].
     */
    public function pushMasivo(): void
    {
        $empresaId = Context::getEmpresaId();
        header('Content-Type: application/json');
        set_time_limit(180);

        $entidad = $_POST['entidad'] ?? 'articulo';
        $ids     = json_decode($_POST['ids'] ?? '[]', true);

        if (!is_array($ids) || empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'No se recibieron IDs.']);
            exit;
        }

        $ok = 0; $err = 0; $errors = [];
        foreach ($ids as $localId) {
            try {
                $this->service->pushToTangoByLocalId($empresaId, (int)$localId, $entidad);
                $ok++;
            } catch (\Exception $e) {
                $err++;
                $errors[] = "ID {$localId}: " . $e->getMessage();
            }
        }

        $msg = "Push completado. OK: {$ok} | Errores: {$err}";
        echo json_encode([
            'success' => $err === 0,
            'message' => $msg,
            'details' => $errors,
        ]);
        exit;
    }

    /**
     * AJAX: Pull masivo — trae datos de Tango y actualiza registros locales.
     * Recibe POST['ids'] = JSON array de local IDs, POST['entidad'].
     */
    public function pullMasivo(): void
    {
        $empresaId = Context::getEmpresaId();
        header('Content-Type: application/json');
        set_time_limit(180);

        $entidad = $_POST['entidad'] ?? 'articulo';
        $ids     = json_decode($_POST['ids'] ?? '[]', true);

        if (!is_array($ids) || empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'No se recibieron IDs.']);
            exit;
        }

        $ok = 0; $err = 0; $errors = [];
        foreach ($ids as $localId) {
            try {
                $this->service->pullFromTangoByLocalId($empresaId, (int)$localId, $entidad);
                $ok++;
            } catch (\Exception $e) {
                $err++;
                $errors[] = "ID {$localId}: " . $e->getMessage();
            }
        }

        $msg = "Pull completado. Actualizados: {$ok} | Errores: {$err}";
        echo json_encode([
            'success' => $err === 0,
            'message' => $msg,
            'details' => $errors,
        ]);
        exit;
    }
}
